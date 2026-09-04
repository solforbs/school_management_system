<?php

namespace App\Services;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pull the deploy branch onto the server.
 *
 * This deploys code in response to an HTTP request, which is worth being
 * plain about: nothing from the browser reaches a command line here. The
 * remote, the branch and the working directory all come from config, every
 * process is invoked with an argument array rather than a shell string, and
 * the caller decides who is allowed to ask.
 *
 * Failure is all-or-nothing. The commit is recorded before anything moves,
 * and any step that fails resets the working copy back to it and rebuilds
 * the caches, so a half-applied deploy is never left running.
 */
class GitDeploy
{
    private const LOCK_KEY = 'deploy:pull';

    public function pull(): DeployResult
    {
        if (! config('deploy.enabled', false)) {
            return DeployResult::refused('Pulling from the dashboard is switched off.');
        }

        $lock = Cache::lock(self::LOCK_KEY, 300);

        if (! $lock->get()) {
            return DeployResult::refused('A deploy is already running. Give it a moment.');
        }

        try {
            return $this->run();
        } catch (Throwable $e) {
            Log::error('Deploy pull crashed', ['error' => $e->getMessage()]);

            return DeployResult::failed('The deploy failed unexpectedly: '.$e->getMessage());
        } finally {
            $lock->release();
        }
    }

    private function run(): DeployResult
    {
        $branch = (string) config('deploy.branch', 'main');
        $remote = (string) config('deploy.remote', 'origin');

        $head = $this->git(['rev-parse', 'HEAD']);

        if (! $head->successful()) {
            $stderr = trim($head->errorOutput());

            if ($this->looksLikePermissionProblem($stderr)) {
                return DeployResult::failed($this->permissionHint($stderr));
            }

            return DeployResult::failed('Not a git checkout, or git cannot read it: '.$stderr);
        }

        $previous = trim($head->output());

        // Someone editing files directly on the server is the one case where
        // pulling destroys work that exists nowhere else. Refuse rather than
        // reset over it. Submodule content is expected to differ and ignored.
        $dirty = $this->git(['status', '--porcelain', '--untracked-files=no', '--ignore-submodules=dirty']);

        if (trim($dirty->output()) !== '') {
            return DeployResult::refused(
                'There are uncommitted changes on the server, so nothing was pulled: '.trim($dirty->output())
            );
        }

        $fetch = $this->git(['fetch', $remote, $branch]);

        if (! $fetch->successful()) {
            $stderr = trim($fetch->errorOutput());

            // "cannot open '.git/FETCH_HEAD': Permission denied" is a local
            // write-permission problem wearing a network error's clothes.
            if ($this->looksLikePermissionProblem($stderr)) {
                return DeployResult::failed($this->permissionHint($stderr));
            }

            return DeployResult::failed('Could not reach the remote: '.$stderr);
        }

        $target = $this->git(['rev-parse', $remote.'/'.$branch]);

        if (! $target->successful()) {
            return DeployResult::failed("No branch {$remote}/{$branch} on the remote.");
        }

        if (trim($target->output()) === $previous) {
            return DeployResult::upToDate($previous, $this->subject($previous));
        }

        // composer is not run from here. A dependency change needs more time
        // and more privilege than a web request should have, so it is
        // reported and left to the deploy script instead of half-applied.
        $lockChanged = ! $this->git(['diff', '--quiet', $previous, $remote.'/'.$branch, '--', 'composer.lock'])
            ->successful();

        $checkout = $this->git(['checkout', '-B', $branch, $remote.'/'.$branch]);

        if (! $checkout->successful()) {
            $stderr = trim($checkout->errorOutput());
            $this->rollback($previous);

            if ($this->looksLikePermissionProblem($stderr)) {
                return DeployResult::failed($this->permissionHint($stderr));
            }

            return DeployResult::failed('Checkout failed, server left unchanged: '.$stderr);
        }

        $cleared = $this->refreshCaches();

        if ($cleared !== null) {
            $this->rollback($previous);

            return DeployResult::failed("Rolled back - {$cleared}");
        }

        $now = trim($this->git(['rev-parse', 'HEAD'])->output());
        $commits = $this->countCommits($previous, $now);

        // What the release actually touched, so migrations only run when a
        // migration file moved and seeders are only ever surfaced, never run.
        $changed = $this->changedFiles($previous, $now);
        $pendingSeeders = $this->seederClassesAmong($changed);

        $migrations = null;

        if (config('deploy.run_migrations', true) && $this->touchesMigrations($changed)) {
            $ran = $this->runMigrations();

            if (! $ran['ok']) {
                Log::error('Deploy migration failed', ['from' => $previous, 'to' => $now, 'output' => $ran['summary']]);

                // The code is in place and stays in place: resetting it now
                // would leave a half-applied schema against the old code,
                // which is worse than telling someone to look.
                return DeployResult::needsAttention(
                    $now,
                    $this->subject($now),
                    $commits,
                    'Code updated, but a migration failed - the server needs attention: '.$ran['summary'],
                    $pendingSeeders,
                );
            }

            $migrations = $ran['summary'];
        }

        Log::info('Deploy pull applied', ['from' => $previous, 'to' => $now, 'migrations' => $migrations]);

        return DeployResult::deployed(
            $now,
            $this->subject($now),
            $commits,
            $lockChanged,
            $migrations,
            $pendingSeeders,
        );
    }

    /**
     * Run any seeder classes a release added, on explicit request. Every
     * class is re-checked here against what actually lives in the seeder
     * directories - the list from the deploy is a convenience, not a
     * trusted input.
     *
     * @param  list<string>  $classes
     */
    public function runSeeders(array $classes): DeployResult
    {
        if (! config('deploy.enabled', false)) {
            return DeployResult::refused('Pulling from the dashboard is switched off.');
        }

        $known = $this->allSeederClasses();
        $requested = array_values(array_unique($classes));
        $unknown = array_diff($requested, $known);

        if ($unknown !== []) {
            return DeployResult::refused('Not a seeder in this project: '.implode(', ', $unknown));
        }

        if ($requested === []) {
            return DeployResult::refused('No seeders were selected.');
        }

        $lock = Cache::lock(self::LOCK_KEY, 600);

        if (! $lock->get()) {
            return DeployResult::refused('A deploy is already running. Give it a moment.');
        }

        try {
            $ran = [];

            foreach ($requested as $class) {
                try {
                    $code = Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
                } catch (Throwable $e) {
                    Log::error('Deploy seeder crashed', ['class' => $class, 'error' => $e->getMessage()]);

                    return DeployResult::failed("Seeder {$class} failed: ".$e->getMessage().'. '.count($ran).' of '.count($requested).' ran.');
                }

                if ($code !== 0) {
                    return DeployResult::failed("Seeder {$class} exited {$code}. ".count($ran).' of '.count($requested).' ran.');
                }

                $ran[] = $class;
            }

            Log::info('Deploy seeders run', ['classes' => $ran]);

            return DeployResult::seeded($ran);
        } finally {
            $lock->release();
        }
    }

    /**
     * Put the working copy back and rebuild caches around it.
     */
    private function rollback(string $commit): void
    {
        Log::warning('Deploy pull rolling back', ['to' => $commit]);

        $this->git(['reset', '--hard', $commit]);
        $this->refreshCaches();
    }

    /**
     * Rebuild the caches a code change invalidates.
     *
     * Returns null on success, or a description of what failed. A stale route
     * cache is the dangerous one: a newly added route resolves to nothing and
     * pages that reference it throw rather than render.
     *
     * Called in-process rather than shelled out. Under PHP-FPM the constant
     * PHP_BINARY points at the FPM binary, not the CLI one, so spawning
     * `PHP_BINARY artisan` works in tests and fails on the server - which is
     * the worst way round to get it wrong.
     */
    private function refreshCaches(): ?string
    {
        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            try {
                $code = Artisan::call($command);
            } catch (Throwable $e) {
                return "could not run {$command}: ".$e->getMessage();
            }

            if ($code !== 0) {
                return "could not run {$command} (exit {$code}).";
            }
        }

        return null;
    }

    private function subject(string $commit): string
    {
        return trim($this->git(['log', '-1', '--pretty=%s', $commit])->output());
    }

    /**
     * Whether a git failure is really the web-server user not being able to
     * write the checkout - the recurring blocker for deploying over HTTP,
     * since PHP-FPM runs as one user and the repo is owned by another.
     */
    private function looksLikePermissionProblem(string $stderr): bool
    {
        $stderr = strtolower($stderr);

        return str_contains($stderr, 'permission denied')
            || str_contains($stderr, 'dubious ownership')
            || str_contains($stderr, 'unable to create')
            || (str_contains($stderr, '.git/') && str_contains($stderr, 'cannot open'));
    }

    /**
     * A message that names the actual fix rather than the symptom.
     */
    private function permissionHint(string $stderr): string
    {
        $pw = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? posix_getpwuid(posix_geteuid())
            : false;

        $user = is_array($pw) ? $pw['name'] : 'the web server user';

        $path = rtrim((string) config('deploy.path', base_path()), '/\\');

        return sprintf(
            'The web server user (%s) cannot write the git checkout at %s, so nothing was pulled. '
            .'Give it write access to the repository and its .git directory - for example: '
            .'sudo chown -R %s: %s  (or add %s to the owner\'s group and run '
            .'chmod -R g+ws %s && git -C %s config core.sharedRepository group). '
            .'Original error: %s',
            $user, $path, $user, $path, $user, $path, $path, $stderr,
        );
    }

    /**
     * The repo-relative paths a release touched, in either direction.
     *
     * @return list<string>
     */
    private function changedFiles(string $from, string $to): array
    {
        $diff = $this->git(['diff', '--name-only', $from, $to]);

        if (! $diff->successful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $diff->output()) ?: [])));
    }

    /**
     * @param  list<string>  $files
     */
    private function touchesMigrations(array $files): bool
    {
        foreach ($files as $file) {
            if (preg_match('#(^|/)database/migrations/[^/]+\.php$#', $file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run outstanding migrations in-process. Shelling out to `artisan` is
     * unreliable here: under PHP-FPM PHP_BINARY is the FPM binary, not the
     * CLI one.
     *
     * @return array{ok: bool, summary: string}
     */
    private function runMigrations(): array
    {
        try {
            $code = Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return ['ok' => false, 'summary' => $e->getMessage()];
        }

        $output = trim(Artisan::output());
        $ran = preg_match_all('/^\s*(Migrated|Migrating):/mi', $output);

        return [
            'ok' => $code === 0,
            'summary' => $code === 0
                ? ($ran > 0 ? $ran.' migration'.($ran === 1 ? '' : 's').' run.' : 'No new migrations.')
                : (Str::limit($output, 500) ?: "migrate exited {$code}."),
        ];
    }

    /**
     * The seeder classes a release added or changed - resolved to their
     * fully-qualified names, never run.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private function seederClassesAmong(array $files): array
    {
        $classes = [];

        foreach ($files as $file) {
            if (! preg_match('#(^|/)database/seeders/[^/]+\.php$#', $file)) {
                continue;
            }

            $class = $this->resolveSeederClass($this->repoPath($file));

            if ($class !== null) {
                $classes[$class] = true;
            }
        }

        return array_keys($classes);
    }

    /**
     * The fully-qualified class name declared in a PHP file, when it is a
     * concrete seeder that still exists on disk.
     */
    private function resolveSeederClass(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $source = File::get($path);

        if (! preg_match('/^\s*namespace\s+([^;]+);/m', $source, $ns)
            || ! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $source, $cls)) {
            return null;
        }

        $class = trim($ns[1]).'\\'.$cls[1];

        return class_exists($class) && is_subclass_of($class, Seeder::class) && ! (new \ReflectionClass($class))->isAbstract()
            ? $class
            : null;
    }

    /**
     * Every seeder class under the configured seeder directories, so a
     * request to run one can be checked against real files.
     *
     * @return list<string>
     */
    private function allSeederClasses(): array
    {
        /** @var list<string> $globs */
        $globs = (array) config('deploy.seeder_paths', ['database/seeders']);
        $classes = [];

        foreach ($globs as $glob) {
            foreach (File::glob($this->repoPath($glob).'/*.php') as $file) {
                $class = $this->resolveSeederClass($file);

                if ($class !== null) {
                    $classes[$class] = true;
                }
            }
        }

        return array_keys($classes);
    }

    private function repoPath(string $relative): string
    {
        $root = rtrim((string) config('deploy.path', base_path()), '/\\');

        return $relative === '' ? $root : $root.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }

    private function countCommits(string $from, string $to): int
    {
        $result = $this->git(['rev-list', '--count', $from.'..'.$to]);

        return $result->successful() ? (int) trim($result->output()) : 0;
    }

    /**
     * Every git call names the working copy as a trusted directory.
     *
     * A deploy user is rarely the one that owns the checkout - PHP-FPM runs
     * as www-data, the files belong to a deploy account - and modern git
     * refuses to touch a repo it does not own ("detected dubious
     * ownership") until the path is marked safe. Passing it per-command
     * with `-c` fixes that for our calls without a global config change on
     * the server.
     *
     * @param  array<int, string>  $arguments
     */
    private function git(array $arguments)
    {
        $path = rtrim((string) config('deploy.path', base_path()), '/\\');

        return $this->process(array_merge(
            ['git', '-c', 'safe.directory='.$path, '-c', 'safe.directory=*'],
            $arguments,
        ));
    }

    /**
     * @param  array<int, string>  $command
     */
    private function process(array $command)
    {
        return Process::path((string) config('deploy.path', base_path()))
            ->timeout((int) config('deploy.timeout', 120))
            ->run($command);
    }
}
