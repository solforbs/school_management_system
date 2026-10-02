<?php

namespace Modules\Subject\Actions;

use Modules\Subject\Models\Subject;

/**
 * Creating and editing a subject, in one place - shared by the Livewire
 * screen and the controller endpoint so both validate and store it the
 * same way.
 */
class SaveSubject
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'learning_area' => 'nullable|string|max:100',
            'strand' => 'nullable|string|max:100',
            'sub_strand' => 'nullable|string|max:100',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, int $institutionId, ?Subject $subject = null): Subject
    {
        $payload = [
            'institution_id' => $institutionId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'learning_area' => $data['learning_area'] ?? null,
            'strand' => $data['strand'] ?? null,
            'sub_strand' => $data['sub_strand'] ?? null,
            'is_compulsory' => (bool) ($data['is_compulsory'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($subject) {
            $subject->update($payload);

            return $subject;
        }

        return Subject::create($payload);
    }
}
