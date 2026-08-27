<?php

namespace Modules\Classes\Actions;

use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;

/**
 * Creating and editing a class, in one place - shared by the Livewire
 * screen and the controller endpoint.
 */
class SaveSchoolClass
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'level' => 'nullable|string|max:100',
            'curriculum_id' => 'nullable|exists:curricula,id',
            'class_teacher_id' => 'nullable|exists:users,id',
            'capacity' => 'nullable|integer|min:1',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, int $institutionId, ?SchoolClass $schoolClass = null): SchoolClass
    {
        // Which curriculum a class runs on decides the scale its results are
        // graded against, so it can't be borrowed from another school -
        // 'exists' alone would allow exactly that.
        if (! empty($data['curriculum_id'])) {
            $curriculum = Curriculum::findOrFail($data['curriculum_id']);
            abort_unless($curriculum->institution_id === $institutionId, 403);
        }

        $payload = [
            'institution_id' => $institutionId,
            'name' => $data['name'],
            'level' => $data['level'] ?? null,
            'curriculum_id' => $data['curriculum_id'] ?: null,
            'class_teacher_id' => $data['class_teacher_id'] ?: null,
            'capacity' => $data['capacity'] ?: null,
        ];

        if ($schoolClass) {
            $schoolClass->update($payload);

            return $schoolClass;
        }

        return SchoolClass::create($payload);
    }
}
