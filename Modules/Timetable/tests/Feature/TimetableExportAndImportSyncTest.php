<?php

namespace Modules\Timetable\Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableExportAndImportSyncTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected User $director;

    protected Institution $institution;

    protected SchoolClass $class;

    protected Subject $subject;

    protected TimetableStructure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->director = User::factory()->create();
        $this->director->assignRole('Director');

        $this->institution = Institution::create([
            'user_id' => $this->director->id,
            'name' => 'Sync Academy '.uniqid(),
            'code' => 'SNC-'.uniqid(),
            'type' => 'School',
            'is_active' => true,
            'status' => 'active',
            'is_approved' => true,
        ]);

        $this->director->update(['active_institution_id' => $this->institution->id]);

        Role::findByName('Director')->givePermissionTo([
            'view timetable',
            'create timetable',
            'edit timetable',
            'delete timetable',
            'export timetable',
        ]);

        $this->class = $this->makeClass('Grade 8');
        $this->subject = $this->makeSubject('Mathematics');

        $this->structure = $this->savedStructure(4);
        $this->structure->update(['is_default' => true]);
    }

    public function test_export_as_pdf_returns_a4_landscape_visual_weekly_grid(): void
    {
        $teacher = $this->makeTeacher('Mr. Omondi');

        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Mathematics',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'room' => 'Room 101',
            'notes' => '[Double Lesson Part 1/2]',
        ]);

        $response = $this->actingAs($this->director)->get(route('timetable.export', [
            'class_id' => $this->class->id,
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('timetable-', $response->headers->get('Content-Disposition'));
    }

    public function test_printable_notice_board_pdf_format_downloads_visual_grid(): void
    {
        $teacher = $this->makeTeacher('Mrs. Njeri');

        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Science',
            'day_of_week' => 'Tuesday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        $response = $this->actingAs($this->director)->get(route('timetable.print', [
            'class_id' => $this->class->id,
            'format' => 'pdf',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_csv_import_resolves_teacher_by_human_name(): void
    {
        $teacher = $this->makeTeacher('Jane Atieno');

        $csvContent = "Day,Start Time,End Time,Subject,Teacher,Room,Notes\n".
            "Monday,08:00,08:40,Mathematics,{$teacher->name},Room 101,[Double Lesson Part 1/2]\n";

        $file = UploadedFile::fake()->createWithContent('grade8_schedule.csv', $csvContent);

        $response = $this->actingAs($this->director)->post(route('timetable.import.store'), [
            'class_id' => $this->class->id,
            'file' => $file,
        ]);

        $response->assertRedirect(route('timetable.index', ['class_id' => $this->class->id]));

        $entry = TimetableEntry::where('class_id', $this->class->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals('Mathematics', $entry->subject);
        $this->assertEquals($teacher->id, $entry->teacher_id);
        $this->assertEquals('[Double Lesson Part 1/2]', $entry->notes);
    }

    public function test_csv_template_download_includes_notes_column(): void
    {
        $response = $this->actingAs($this->director)->get(route('timetable.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Notes', $content);
        $this->assertStringContainsString('[Double Lesson Part 1/2]', $content);
    }

    public function test_printable_and_pdf_use_theme_blue_and_resolve_logo(): void
    {
        $response = $this->actingAs($this->director)->get(route('timetable.print', [
            'class_id' => $this->class->id,
        ]));

        $response->assertOk();
        $content = $response->getContent();

        // Must use Solforbs theme blue #0047FF rather than pitch black at the top
        $this->assertStringContainsString('#0047FF', $content);
        $this->assertStringContainsString('border-[#0047FF]', $content);
        $this->assertStringContainsString('bg-[#0047FF]', $content);

        // Logo fallback or data URI must be present, never an empty/broken placeholder
        $this->assertTrue(
            str_contains($content, 'data:image/') || str_contains($content, 'solforbs-logo.png')
        );
    }
}
