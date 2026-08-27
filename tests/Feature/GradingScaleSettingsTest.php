<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Support\GradingScaleDefaults;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array{0: User, 1: Institution}
 */
function gssSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Scale School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function gssCurriculum(Institution $institution, string $name, string $system): Curriculum
{
    return Curriculum::create([
        'institution_id' => $institution->id,
        'name' => $name,
        'system' => $system,
        'status' => 'active',
    ]);
}

test('both standard scales are offered, on the school-wide scale and on a curriculum alike', function () {
    [$director, $institution] = gssSchool();

    $curriculum = gssCurriculum($institution, 'CBC', 'cbc');

    $component = Livewire::actingAs($director)->test('reportcard::settings');

    // School-wide, with no curriculum picked.
    $component->assertSee('8-4-4 — A to E')
        ->assertSee('CBC — 4 Bands (EE / ME / AE / BE) & 8 KJSEA Levels')
        ->assertSet('defaultScale', GradingScaleDefaults::SCALE_844);

    // Picking a curriculum keeps the same choices, preselected to
    // whatever that curriculum says it grades on.
    $component->set('curriculumId', (string) $curriculum->id)
        ->assertSet('defaultScale', GradingScaleDefaults::SCALE_CBC)
        ->assertSee('8-4-4 — A to E')
        ->assertSee('CBC — 4 Bands (EE / ME / AE / BE) & 8 KJSEA Levels');
});

test('the chosen standard scale is what gets loaded, not the curriculum default', function () {
    [$director, $institution] = gssSchool();

    // A curriculum whose system was guessed wrong on import: named CBC,
    // left grading 8-4-4.
    $curriculum = gssCurriculum($institution, 'C.B.C', '844');

    Livewire::actingAs($director)
        ->test('reportcard::settings')
        ->set('curriculumId', (string) $curriculum->id)
        // Preselects 8-4-4 from the curriculum, but the school overrides it.
        ->assertSet('defaultScale', GradingScaleDefaults::SCALE_844)
        ->set('defaultScale', GradingScaleDefaults::SCALE_CBC)
        ->call('loadDefaults');

    $bands = GradingBand::where('curriculum_id', $curriculum->id)
        ->orderByDesc('min_percentage')
        ->pluck('grade')
        ->all();

    expect($bands)->toBe(['EE1', 'EE2', 'ME1', 'ME2', 'AE1', 'AE2', 'BE1', 'BE2']);
});

test('the school-wide scale loads against no curriculum at all', function () {
    [$director, $institution] = gssSchool();

    Livewire::actingAs($director)
        ->test('reportcard::settings')
        ->set('defaultScale', GradingScaleDefaults::SCALE_844)
        ->call('loadDefaults');

    $bands = GradingBand::whereNull('curriculum_id')->pluck('grade');

    expect($bands)->toContain('A', 'E')
        ->and($bands)->toHaveCount(12)
        ->and(GradingBand::where('institution_id', $institution->id)->count())->toBe(12);
});

test('a curriculum named for one system but grading on the other is called out on screen', function () {
    [$director, $institution] = gssSchool();

    $mismatched = gssCurriculum($institution, 'C.B.C', '844');
    $fine = gssCurriculum($institution, 'CBC Junior', 'cbc');

    Livewire::actingAs($director)
        ->test('reportcard::settings')
        ->set('curriculumId', (string) $mismatched->id)
        ->assertSee('is named for one system but is set to grade on')
        // And the label spells out the grades, so the mismatch is legible.
        ->assertSee('8-4-4 (A to E)')
        ->set('curriculumId', (string) $fine->id)
        ->assertDontSee('is named for one system but is set to grade on');
});

test('loading refuses to run over a scale that already has bands', function () {
    [$director, $institution] = gssSchool();

    $curriculum = gssCurriculum($institution, 'CBC', 'cbc');

    GradingBand::create([
        'institution_id' => $institution->id,
        'curriculum_id' => $curriculum->id,
        'min_percentage' => 0,
        'max_percentage' => 100,
        'grade' => 'HAND-TUNED',
    ]);

    Livewire::actingAs($director)
        ->test('reportcard::settings')
        ->set('curriculumId', (string) $curriculum->id)
        ->set('defaultScale', GradingScaleDefaults::SCALE_CBC)
        ->call('loadDefaults');

    expect(GradingBand::where('curriculum_id', $curriculum->id)->pluck('grade')->all())
        ->toBe(['HAND-TUNED']);
});
