<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

/**
 * One-off migration for the switch from local disk to Cloudinary: uploads
 * every institution logo and student profile photo still sitting on the
 * local "public" disk to Cloudinary, reusing the exact same relative path
 * as the storage key - so no database column needs updating, only the
 * backend the path resolves against.
 */
#[Signature('app:migrate-images-to-cloudinary
    {--delete-local : Remove the local copy after a successful upload}')]
#[Description('Upload existing local institution logos and student profile photos to Cloudinary')]
class MigrateImagesToCloudinary extends Command
{
    public function handle(): int
    {
        $deleteLocal = $this->option('delete-local');
        $local = Storage::disk('public');
        $cloudinary = Storage::disk('cloudinary');

        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $migrateOne = function (?string $path) use ($local, $cloudinary, $deleteLocal, &$migrated, &$skipped, &$failed): void {
            if (! $path) {
                return;
            }

            if (! $local->exists($path)) {
                $skipped++;

                return;
            }

            try {
                // The Cloudinary adapter uploads via a real file path, not
                // raw byte content, so point it at the local file directly
                // rather than passing $local->get($path) through put().
                $cloudinary->putFileAs(dirname($path), new File($local->path($path)), basename($path));
                $migrated++;

                if ($deleteLocal) {
                    $local->delete($path);
                }

                $this->line("Migrated: {$path}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed: {$path} - {$e->getMessage()}");
            }
        };

        $this->info('Migrating institution logos...');
        Institution::whereNotNull('logo')->each(fn (Institution $institution) => $migrateOne($institution->logo));

        $this->info('Migrating student profile photos...');
        StudentDetails::whereNotNull('profile_photo')->each(fn (StudentDetails $student) => $migrateOne($student->profile_photo));

        $this->newLine();
        $this->info("Done. Migrated: {$migrated}, Already on Cloudinary/skipped: {$skipped}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
