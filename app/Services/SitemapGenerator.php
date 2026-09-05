<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Throwable;

/**
 * Builds public/sitemap.xml for search engines (submitted through Google
 * Search Console) and rebuilds it on the hourly schedule in
 * routes/console.php, or on demand from the admin Site Settings page.
 *
 * The page list is a fixed allowlist, not a scan of every registered
 * route: everything under /dashboard (attendance, fees, results, billing,
 * admin/*, every module) is a Director's or Admin's data and must never
 * appear in a document served to anyone who asks for it. Naming the public
 * marketing pages one by one means a new dashboard route is invisible to
 * the sitemap by default, rather than needing to be remembered and
 * excluded.
 */
class SitemapGenerator
{
    private const LOCK_KEY = 'sitemap:generate';

    private const LAST_GENERATED_KEY = 'sitemap_last_generated_at';

    public function generate(): SitemapResult
    {
        $lock = Cache::lock(self::LOCK_KEY, 120);

        if (! $lock->get()) {
            return SitemapResult::refused('A sitemap generation is already running. Give it a moment.');
        }

        try {
            $sitemap = Sitemap::create();

            foreach ($this->urls() as $url) {
                $sitemap->add($url);
            }

            $sitemap->writeToFile(public_path('sitemap.xml'));
            $this->writeRobotsTxt();

            $urlCount = count($this->urls());

            Setting::put(self::LAST_GENERATED_KEY, now()->toIso8601String());

            Log::info('Sitemap generated', ['urls' => $urlCount]);

            return SitemapResult::success($urlCount);
        } catch (Throwable $e) {
            Log::error('Sitemap generation failed', ['error' => $e->getMessage()]);

            return SitemapResult::failed('Sitemap generation failed: '.$e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public static function lastGeneratedAt(): ?string
    {
        return Setting::value(self::LAST_GENERATED_KEY);
    }

    /**
     * The public, unauthenticated pages - deliberately listed by hand
     * rather than derived from the route list. See class docblock.
     *
     * @return list<Url>
     */
    private function urls(): array
    {
        return [
            Url::create(route('home'))
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY),
            Url::create(route('services'))
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY),
            Url::create(route('plans'))
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY),
            Url::create(route('about'))
                ->setPriority(0.6)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY),
            Url::create(route('contact'))
                ->setPriority(0.5)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY),
        ];
    }

    /**
     * Kept in step with sitemap.xml rather than hand-maintained: the
     * Sitemap: line needs the app's actual absolute URL, which is only
     * known at generation time (APP_URL differs between local, staging and
     * production), and the Disallow rules mirror the same allowlist this
     * class already enforces - the dashboard and every module under it
     * never gets crawled, sitemap or not.
     */
    private function writeRobotsTxt(): void
    {
        $lines = [
            'User-agent: *',
            'Disallow: /dashboard',
            'Disallow: /settings',
            'Disallow: /billing',
            'Disallow: /.well-known/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        file_put_contents(public_path('robots.txt'), implode("\n", $lines));
    }
}
