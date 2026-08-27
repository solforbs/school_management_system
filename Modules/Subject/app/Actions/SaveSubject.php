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
