<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Models\ReportTemplate;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array{0: User, 1: Institution}
 */
function rcpSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Preview School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function rcpLoadScale(Institution $institution, ?Curriculum $curriculum, string $system): void
{
    foreach (GradingScaleDefaults::forSystem($system) as $band) {
        GradingBand::create($band + [
            'institution_id' => $institution->id,
            'curriculum_id' => $curriculum?->id,
        ]);
    }
}

function rcpPdfText(string $body): string
{
    $file = tempnam(sys_get_temp_dir(), 'rcp').'.pdf';
    file_put_contents($file, $body);

    $text = (new Parser)->parseFile($file)->getText();
    unlink($file);

    return $text;
}

test('the preview renders a sample through the school\'s own scale and wording', function () {
    [$director, $institution] = rcpSchool();

    rcpLoadScale($institution, null, 'cbc');

    ReportTemplate::create([
        'institution_id' => $institution->id,
        'opening_text' => 'Habari, here is {{student_name}} for {{term}}.',
        'signatory_name' => 'Beatrice Njeri',
        'signatory_title' => 'Head Teacher',
    ]);

    $response = $this->actingAs($director)->get(route('reportcard.settings.preview'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $text = rcpPdfText($response->getContent());

    expect($text)
        // Marked as a sample, so it can never be mistaken for a real report.
        ->toContain('SAMPLE')
        ->toContain('Asha Sample')
        ->toContain($institution->name)
        // The school's own placeholder wording, filled in.
        ->toContain('Habari, here is Asha Sample for Sample Term.')
        ->toContain('Beatrice Njeri')
        // Graded on the CBC scale actually configured: 92% is EE1, 64% ME1.
        ->toContain('EE1')
        ->toContain('ME1')
        // And the key for that scale is printed beneath.
        ->toContain('Exceeding Expectations');
});

test('the preview follows the 8-4-4 scale when that is what the school configured', function () {
    [$director, $institution] = rcpSchool();

    rcpLoadScale($institution, null, '844');

    $text = rcpPdfText(
        $this->actingAs($director)->get(route('reportcard.settings.preview'))->getContent()
    );

    // 92% is an A, 78% an A-, 64% a B-, 45% a C-. No expectation bands.
    expect($text)->toContain('A-')
        ->and($text)->not->toContain('EE1')
        ->and($text)->not->toContain('Exceeding Expectations');
});

test('previewing a curriculum uses that curriculum\'s own bands', function () {
    [$director, $institution] = rcpSchool();

    // School-wide is 8-4-4; this one curriculum is marked on CBC.
    rcpLoadScale($institution, null, '844');
    $cbc = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);
    rcpLoadScale($institution, $cbc, 'cbc');

    $text = rcpPdfText(
        $this->actingAs($director)
            ->get(route('reportcard.settings.preview', ['curriculum_id' => $cbc->id]))
            ->getContent()
    );

    expect($text)->toContain('EE1')->toContain('CBC');
});

test('previewing writes nothing at all', function () {
    [$director, $institution] = rcpSchool();

    rcpLoadScale($institution, null, 'cbc');

    $before = [
        'report_cards' => ReportCard::count(),
        'users' => User::count(),
        'bands' => GradingBand::count(),
    ];

    $this->actingAs($director)->get(route('reportcard.settings.preview'))->assertOk();

    expect(ReportCard::count())->toBe($before['report_cards'])
        ->and(User::count())->toBe($before['users'])
        ->and(GradingBand::count())->toBe($before['bands']);
});

test('a school with no scale yet still gets a preview, ungraded', function () {
    [$director, $institution] = rcpSchool();

    // Nothing configured: the preview is what shows the school it has a
    // scale to load, rather than erroring.
    $response = $this->actingAs($director)->get(route('reportcard.settings.preview'));

    $response->assertOk();

    expect(rcpPdfText($response->getContent()))->toContain('Asha Sample');
});

test('the preview is offered on the settings screen and refused without rights', function () {
    [$director, $institution] = rcpSchool();

    Livewire::actingAs($director)
        ->test('reportcard::settings')
        ->assertSee('Preview report card');

    $student = User::factory()->create();
    $student->assignRole('Student');
    $student->update(['active_institution_id' => $institution->id]);

    $this->actingAs($student)->get(route('reportcard.settings.preview'))->assertForbidden();
});

test('the mean is stated out of the scale in use, not out of a guess at it', function () {
    [$director, $institution] = rcpSchool();

    // CBC bands on the school-wide scale, with no curriculum attached -
    // the case where the ceiling used to fall back to 8-4-4's 12.
    rcpLoadScale($institution, null, 'cbc');

    $text = rcpPdfText(
        $this->actingAs($director)->get(route('reportcard.settings.preview'))->getContent()
    );

    expect($text)->toContain('/ 8')
        ->and($text)->not->toContain('/ 12');
});
