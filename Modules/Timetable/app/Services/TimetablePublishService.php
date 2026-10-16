<?php

namespace Modules\Timetable\Services;

use Illuminate\Support\Facades\DB;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;

class TimetablePublishService
{
    /**
     * Publish draft entries to the live timetable_entries table safely.
     * Preserves IDs of historical entries linked to student lesson logs.
     *
     * @return array<string, mixed> Result metrics
     */
    public function publish(TimetableDraft $draft, ?int $userId = null): array
    {
        abort_unless($draft->isDraft(), 422, 'Only active drafts can be published.');

        $institutionId = $draft->institution_id;
        $draftEntries = $draft->draftEntries()->with(['schoolClass', 'subject'])->get();
        abort_if($draftEntries->isEmpty(), 422, 'Cannot publish an empty draft timetable.');

        // Determine which classes are affected by this draft
        $affectedClassIds = $draftEntries->pluck('class_id')->unique()->values()->all();

        $metrics = [
            'total_draft_entries' => $draftEntries->count(),
            'updated_in_place' => 0,
            'created_new' => 0,
            'historical_preserved' => 0,
            'stale_deleted' => 0,
        ];

        DB::transaction(function () use ($draft, $institutionId, $draftEntries, $affectedClassIds, &$metrics, $userId) {
            // 1. Fetch all currently active timetable entries for affected classes
            $existingEntries = TimetableEntry::where('institution_id', $institutionId)
                ->whereIn('class_id', $affectedClassIds)
                ->withCount('lessons')
                ->get();

            // Track which draft entries have been mapped to existing entries
            $claimedDraftEntryIds = [];
            $preservedExistingIds = [];

            // Group draft entries for O(1) lookup
            $draftEntriesByKey = [];
            $draftEntriesByNameKey = [];
            foreach ($draftEntries as $de) {
                $key = "{$de->class_id}_{$de->subject_id}_{$de->day_of_week}";
                $draftEntriesByKey[$key][] = $de;

                $subjectName = strtolower(trim($de->subject?->name ?? $de->subject_title ?? ''));
                if ($subjectName !== '') {
                    $nameKey = "{$de->class_id}_{$subjectName}_{$de->day_of_week}";
                    $draftEntriesByNameKey[$nameKey][] = $de;
                }
            }

            // 2. First pass: for existing entries that have recorded student attendance lessons
            foreach ($existingEntries as $existing) {
                if ($existing->lessons_count > 0) {
                    $matchedDraft = null;
                    $candidates = [];

                    if ($existing->subject_id) {
                        $key = "{$existing->class_id}_{$existing->subject_id}_{$existing->day_of_week}";
                        $candidates = $draftEntriesByKey[$key] ?? [];
                    }

                    if (empty($candidates) && filled($existing->subject)) {
                        $normName = strtolower(trim($existing->subject));
                        $nameKey = "{$existing->class_id}_{$normName}_{$existing->day_of_week}";
                        $candidates = $draftEntriesByNameKey[$nameKey] ?? [];
                    }

                    foreach ($candidates as $candidate) {
                        if (! in_array($candidate->id, $claimedDraftEntryIds, true)) {
                            $matchedDraft = $candidate;
                            break;
                        }
                    }

                    if ($matchedDraft) {
                        // Update in-place to preserve primary key and existing foreign key relations
                        $existing->update([
                            'teacher_id' => $matchedDraft->teacher_id,
                            'subject_id' => $matchedDraft->subject_id ?? $existing->subject_id,
                            'subject' => $matchedDraft->subject?->name ?? $existing->subject,
                            'start_time' => $matchedDraft->start_time->format('H:i:s'),
                            'end_time' => $matchedDraft->end_time->format('H:i:s'),
                            'room' => $matchedDraft->room,
                            'notes' => $matchedDraft->notes,
                        ]);
                        $claimedDraftEntryIds[] = $matchedDraft->id;
                        $preservedExistingIds[] = $existing->id;
                        $metrics['updated_in_place']++;
                    } else {
                        // Keep this historical entry to prevent cascading deletion of student attendance records!
                        $preservedExistingIds[] = $existing->id;
                        $metrics['historical_preserved']++;
                    }
                }
            }

            // 3. Second pass: Remove unpreserved existing entries in a single bulk query
            $staleIds = $existingEntries->pluck('id')->diff($preservedExistingIds)->values()->all();
            if (! empty($staleIds)) {
                TimetableEntry::whereIn('id', $staleIds)->delete();
                $metrics['stale_deleted'] = count($staleIds);
            }

            // 4. Third pass: Create remaining draft entries in high-speed bulk batches
            $claimedSet = array_flip($claimedDraftEntryIds);
            $newRows = [];
            $now = now();

            foreach ($draftEntries as $de) {
                if (isset($claimedSet[$de->id])) {
                    continue;
                }

                $newRows[] = [
                    'institution_id' => $institutionId,
                    'class_id' => $de->class_id,
                    'class_name' => $de->schoolClass?->name ?? 'Class #'.$de->class_id,
                    'teacher_id' => $de->teacher_id,
                    'subject_id' => $de->subject_id,
                    'subject' => $de->subject?->name ?? $de->subject_title ?? ('Subject #'.$de->subject_id),
                    'day_of_week' => $de->day_of_week,
                    'start_time' => $de->start_time->format('H:i:s'),
                    'end_time' => $de->end_time->format('H:i:s'),
                    'room' => $de->room,
                    'notes' => $de->notes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($newRows)) {
                foreach (array_chunk($newRows, 100) as $chunk) {
                    TimetableEntry::insert($chunk);
                }
                $metrics['created_new'] = count($newRows);
            }

            // 5. Mark Draft as published and promote structure to default
            $draft->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $userId ?? auth()->id(),
            ]);

            if ($draft->structure_id) {
                TimetableStructure::where('institution_id', $institutionId)->update(['is_default' => false]);
                TimetableStructure::where('id', $draft->structure_id)->update(['is_default' => true]);
            }
        });

        return $metrics;
    }
}
