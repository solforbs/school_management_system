<?php

namespace App\Console\Commands;

use App\Services\SitemapGenerator;
use Illuminate\Console\Command;

/**
 * Rebuilds public/sitemap.xml from the fixed list of public pages in
 * SitemapGenerator. Scheduled hourly in routes/console.php and also
 * runnable on demand from the admin Site Settings page.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Regenerate public/sitemap.xml from the public-facing pages';

    public function handle(SitemapGenerator $generator): int
    {
        $result = $generator->generate();

        if (! $result->ok) {
            $this->error($result->message);

            return self::FAILURE;
        }

        $this->info($result->message);

        return self::SUCCESS;
    }
}
