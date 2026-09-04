<?php

namespace App\Console\Commands;

use App\Services\GitDeploy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The command form of the Deployment card's Pull button.
 *
 * Same code path, same guard rails (config-fixed remote/branch, refuses
 * over uncommitted work, rolls back a failed checkout) - this is the entry
 * point a cron job or a CI webhook uses instead of the browser. Migrations
 * a release adds are run; seeders it changes are only listed, unless
 * --seed is passed.
 */
#[Signature('deploy:pull {--seed : Also run any seeders the release added}')]
#[Description('Pull the deploy branch onto this server and run its migrations')]
class DeployPull extends Command
{
    public function handle(GitDeploy $deploy): int
    {
        $result = $deploy->pull();

        $line = match ($result->status) {
            'deployed' => fn (string $m) => $this->info($m),
            'up_to_date' => fn (string $m) => $this->line($m),
            'refused' => fn (string $m) => $this->warn($m),
            default => fn (string $m) => $this->error($m),
        };

        $line($result->message);

        if ($result->migrations) {
            $this->line('Migrations: '.$result->migrations);
        }

        if ($result->dependenciesChanged) {
            $this->warn('Dependencies changed in this release - run "composer install" on the server.');
        }

        if ($result->pendingSeeders !== []) {
            $this->newLine();
            $this->line('Seeders changed in this release:');

            foreach ($result->pendingSeeders as $class) {
                $this->line('  - '.$class);
            }

            if ($this->option('seed')) {
                $this->newLine();
                $seedResult = $deploy->runSeeders($result->pendingSeeders);
                $seedResult->ok()
                    ? $this->info($seedResult->message)
                    : $this->error($seedResult->message);

                if (! $seedResult->ok()) {
                    return self::FAILURE;
                }
            } else {
                $this->line('Run "php artisan deploy:pull --seed" or use the Deployment card to run them.');
            }
        }

        return $result->ok() ? self::SUCCESS : self::FAILURE;
    }
}
