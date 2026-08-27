<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
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
            return DeployResult::failed('Not a git checkout, or git cannot read it: '.trim($head->errorOutput()));
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
            return DeployResult::failed('Could not reach the remote: '.trim($fetch->errorOutput()));
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
            $this->rollback($previous);

            return DeployResult::failed('Checkout failed, server left unchanged: '.trim($checkout->errorOutput()));
        }

        $cleared = $this->refreshCaches();

        if ($cleared !== null) {
            $this->rollback($previous);

            return DeployResult::failed("Rolled back - {$cleared}");
        }

        $now = trim($this->git(['rev-parse', 'HEAD'])->output());

        Log::info('Deploy pull applied', ['from' => $previous, 'to' => $now]);

        return DeployResult::deployed(
            $now,
            $this->subject($now),
            $this->countCommits($previous, $now),
            $lockChanged,
        );
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

    private function countCommits(string $from, string $to): int
    {
        $result = $this->git(['rev-list', '--count', $from.'..'.$to]);

        return $result->successful() ? (int) trim($result->output()) : 0;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function git(array $arguments)
    {
        return $this->process(array_merge(['git'], $arguments));
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
