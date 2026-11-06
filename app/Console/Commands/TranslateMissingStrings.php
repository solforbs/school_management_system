<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fills untranslated entries in lang/{locale}.json by machine translation.
 *
 * An entry counts as untranslated while its value still equals its English
 * key - which is how `translatable:export` adds new strings. Placeholders
 * such as :name are shielded so they survive translation unchanged.
 *
 * Workflow after wrapping new text in __():
 *   php artisan translatable:export <locales>
 *   php artisan lang:translate-missing
 */
class TranslateMissingStrings extends Command
{
    protected $signature = 'lang:translate-missing
        {locales?* : Locales to fill (defaults to every language in config/locales.php)}
        {--dry-run : Show how many strings would be translated without calling the API}';

    protected $description = 'Machine-translate untranslated strings in lang/*.json';

    private const BATCH_SIZE = 40;

    public function handle(): int
    {
        $requested = $this->argument('locales') ?: array_keys(config('locales.available'));

        foreach ($requested as $locale) {
            $locale = (string) $locale;

            if ($locale === 'en') {
                continue;
            }

            $path = lang_path("{$locale}.json");

            if (! is_file($path)) {
                $this->warn("{$locale}: lang/{$locale}.json not found, skipped.");

                continue;
            }

            /** @var array<string, string> $strings */
            $strings = json_decode((string) file_get_contents($path), true) ?: [];

            $pending = array_keys(array_filter(
                $strings,
                fn (string $value, string $key): bool => $value === $key && preg_match('/[A-Za-z]/', $key) === 1,
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($pending === [] || $this->option('dry-run')) {
                $this->line("{$locale}: ".count($pending).' untranslated');

                continue;
            }

            $translated = 0;

            foreach (array_chunk($pending, self::BATCH_SIZE) as $batch) {
                foreach ($this->translateBatch($batch, $this->googleCode($locale)) as $key => $value) {
                    $strings[$key] = $value;
                    $translated++;
                }
            }

            ksort($strings, SORT_NATURAL | SORT_FLAG_CASE);

            file_put_contents(
                $path,
                json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            );

            $this->info("{$locale}: translated {$translated} of ".count($pending));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string> English key => translation (failures omitted)
     */
    private function translateBatch(array $keys, string $target): array
    {
        $shielded = [];
        $placeholders = [];

        foreach ($keys as $index => $key) {
            [$shielded[$index], $placeholders[$index]] = $this->shieldPlaceholders($key);
        }

        try {
            $results = config('services.google_translate.key')
                ? $this->translateWithCloudApi($shielded, $target)
                : $this->translateWithPublicEndpoint($shielded, $target);
        } catch (Throwable $exception) {
            $this->warn("  {$target}: batch failed ({$exception->getMessage()})");

            return [];
        }

        $translations = [];

        foreach ($keys as $index => $key) {
            $value = $this->restorePlaceholders($results[$index] ?? '', $placeholders[$index]);

            if ($value !== null && trim($value) !== '') {
                $translations[$key] = $value;
            }
        }

        return $translations;
    }

    /**
     * Official Google Cloud Translation v2 - one request per batch.
     *
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function translateWithCloudApi(array $texts, string $target): array
    {
        $response = Http::asForm()->retry(3, 1000)->post('https://translation.googleapis.com/language/translate/v2', [
            'key' => config('services.google_translate.key'),
            'source' => 'en',
            'target' => $target,
            'format' => 'text',
            'q' => array_values($texts),
        ])->throw();

        return array_map(
            fn (array $item): string => html_entity_decode($item['translatedText'], ENT_QUOTES),
            $response->json('data.translations', []),
        );
    }

    /**
     * Keyless public endpoint - one request per string, lightly throttled.
     *
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function translateWithPublicEndpoint(array $texts, string $target): array
    {
        $results = [];

        foreach ($texts as $index => $text) {
            $response = Http::retry(3, 2000)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => 'en',
                'tl' => $target,
                'dt' => 't',
                'q' => $text,
            ])->throw();

            /** @var list<array{0: string}> $segments */
            $segments = $response->json('0', []);

            $results[$index] = implode('', array_column($segments, 0));

            usleep(150_000);
        }

        return $results;
    }

    /**
     * Swaps :placeholders for numbered tokens Google leaves alone.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function shieldPlaceholders(string $text): array
    {
        $placeholders = [];

        $shielded = (string) preg_replace_callback('/:[A-Za-z_]+/', function (array $match) use (&$placeholders): string {
            $placeholders[] = $match[0];

            return '[['.(count($placeholders) - 1).']]';
        }, $text);

        return [$shielded, $placeholders];
    }

    /**
     * @param  list<string>  $placeholders
     */
    private function restorePlaceholders(string $text, array $placeholders): ?string
    {
        foreach ($placeholders as $index => $placeholder) {
            $pattern = '/\[\s*\[\s*'.$index.'\s*\]\s*\]/';

            if (preg_match($pattern, $text) !== 1) {
                return null;
            }

            $text = (string) preg_replace($pattern, $placeholder, $text, 1);
        }

        return $text;
    }

    private function googleCode(string $locale): string
    {
        $overrides = config('locales.machine_translation_codes', []);

        return $overrides[$locale] ?? str_replace('_', '-', $locale);
    }
}
