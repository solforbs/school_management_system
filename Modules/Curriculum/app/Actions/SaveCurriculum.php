<?php

namespace Modules\Curriculum\Actions;

use Modules\Curriculum\Models\Curriculum;

/**
 * Creating and editing a curriculum, in one place - shared by the Livewire
 * screen and the controller endpoint.
 */
class SaveCurriculum
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            // The name is free text; the system is what the grading scale is
            // actually chosen by.
            'system' => 'required|in:844,cbc',
            'status' => 'required|in:active,dismissed',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, int $institutionId, ?Curriculum $curriculum = null): Curriculum
    {
        $payload = [
            'institution_id' => $institutionId,
            'name' => $data['name'],
            'system' => $data['system'],
            'status' => $data['status'],
        ];

        if ($curriculum) {
            $curriculum->update($payload);

            return $curriculum;
        }

        return Curriculum::create($payload);
    }
}
