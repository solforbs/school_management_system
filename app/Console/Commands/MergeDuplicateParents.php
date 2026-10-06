<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

class MergeDuplicateParents extends Command
{
    protected $signature = 'parents:merge-duplicates 
                            {--institution= : Limit merging to a specific institution ID}
                            {--dry-run : Simulate the merge without making changes to the database}';

    protected $description = 'Merge duplicate parent accounts sharing the same name or phone within an institution and reassign children.';

    public function handle(): int
    {
        $institutionId = $this->option('institution');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be committed to the database.');
        }

        $institutions = Institution::query()
            ->when($institutionId, fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($institutions->isEmpty()) {
            $this->error('No institutions found matching the criteria.');

            return self::FAILURE;
        }

        $totalMerged = 0;
        $totalReassigned = 0;

        foreach ($institutions as $institution) {
            $this->line("Processing institution: {$institution->name} (ID: {$institution->id})...");

            // Find all Parent users that belong to this institution via parent_details or linked children
            $parentUsers = User::role('Parent')
                ->where(function ($query) use ($institution) {
                    $query->whereHas('parent', fn ($pq) => $pq->where('institution_id', $institution->id))
                        ->orWhereHas('children', fn ($cq) => $cq->where('institution_id', $institution->id));
                })
                ->with(['parent', 'children'])
                ->get();

            // Group by normalized name
            $groupedByName = $parentUsers->groupBy(function (User $user) {
                return strtolower(trim($user->name));
            });

            foreach ($groupedByName as $name => $users) {
                if ($users->count() <= 1 || empty($name)) {
                    continue;
                }

                // We have 2 or more parent accounts with the exact same name
                // Sort to pick the best primary / keeper parent account
                $sorted = $users->sort(function (User $a, User $b) {
                    $aIsGenerated = str_ends_with($a->email, '@parents.generated') || str_ends_with($a->email, '@example.com');
                    $bIsGenerated = str_ends_with($b->email, '@parents.generated') || str_ends_with($b->email, '@example.com');

                    // 1. Real email beats generated email
                    if ($aIsGenerated !== $bIsGenerated) {
                        return $aIsGenerated ? 1 : -1;
                    }

                    // 2. Verified email beats unverified
                    if (($a->email_verified_at !== null) !== ($b->email_verified_at !== null)) {
                        return $a->email_verified_at ? -1 : 1;
                    }

                    // 3. Having phone recorded beats not having phone
                    $aHasPhone = ! empty($a->parent->parent_phone);
                    $bHasPhone = ! empty($b->parent->parent_phone);
                    if ($aHasPhone !== $bHasPhone) {
                        return $aHasPhone ? -1 : 1;
                    }

                    // 4. More linked children beats fewer
                    if ($a->children->count() !== $b->children->count()) {
                        return $b->children->count() - $a->children->count();
                    }

                    // 5. Earliest created (lowest ID)
                    return $a->id - $b->id;
                })->values();

                /** @var User $keeper */
                $keeper = $sorted->first();
                $duplicates = $sorted->slice(1);

                $this->info("Merging duplicate group for '{$keeper->name}': Keeper ID #{$keeper->id} ({$keeper->email}), duplicates: [{$duplicates->pluck('id')->join(', ')}]");

                if ($isDryRun) {
                    $dupChildrenCount = StudentDetails::whereIn('parent_id', $duplicates->pluck('id'))->count();
                    $totalReassigned += $dupChildrenCount;
                    $totalMerged += $duplicates->count();

                    continue;
                }

                DB::transaction(function () use ($keeper, $duplicates, $institution, &$totalMerged, &$totalReassigned) {
                    $keeperDetails = ParentDetails::firstOrNew(['parent_id' => $keeper->id]);
                    $keeperDetails->institution_id ??= $institution->id;

                    foreach ($duplicates as $dup) {
                        /** @var User $dup */
                        // If keeper is missing phone or occupation, adopt from duplicate
                        if (empty($keeperDetails->parent_phone) && ! empty($dup->parent->parent_phone)) {
                            $keeperDetails->parent_phone = $dup->parent->parent_phone;
                        }
                        if (empty($keeperDetails->parent_occupation) && ! empty($dup->parent->parent_occupation)) {
                            $keeperDetails->parent_occupation = $dup->parent->parent_occupation;
                        }

                        // Reassign student_details.parent_id
                        $reassigned = StudentDetails::where('parent_id', $dup->id)
                            ->update(['parent_id' => $keeper->id]);
                        $totalReassigned += $reassigned;

                        // Delete duplicate parent_details and User
                        ParentDetails::where('parent_id', $dup->id)->delete();
                        $dup->delete();

                        $totalMerged++;
                    }

                    $keeperDetails->save();
                });
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->info("Dry run complete: {$totalMerged} duplicate parent account(s) would be merged and {$totalReassigned} student(s) reassigned.");
        } else {
            $this->info("Successfully merged {$totalMerged} duplicate parent account(s) and reassigned {$totalReassigned} student(s).");
        }

        return self::SUCCESS;
    }
}
