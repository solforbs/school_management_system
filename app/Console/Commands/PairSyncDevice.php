<?php

namespace App\Console\Commands;

use App\Models\SyncSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Modules\Institution\Models\Institution;
use Throwable;

/**
 * Pairs a freshly installed offline client with the central server.
 *
 * Run once, while the device still has a connection. Afterwards the
 * device can be used with none.
 */
class PairSyncDevice extends Command
{
    protected $signature = 'sync:pair
                            {--server= : Base URL of the central server, e.g. https://solforbs.com}
                            {--token= : The device token issued at Dashboard > Sync Devices}
                            {--passcode= : Offline passcode for this device (prompted when omitted)}';

    protected $description = 'Pair this device with the server and set up its offline login';

    public function handle(): int
    {
        if (! syncClientMode()) {
            $this->error('This is not a client build. Set TETHER_CLIENT_MODE=true first.');

            return self::FAILURE;
        }

        $server = rtrim((string) ($this->option('server') ?: $this->ask('Server URL')), '/');
        $token = (string) ($this->option('token') ?: $this->secret('Device token'));

        if ($server === '' || $token === '') {
            $this->error('A server URL and a device token are both required.');

            return self::FAILURE;
        }

        try {
            $response = Http::asJson()
                ->withToken($token)
                ->get($server.'/tether/device/profile');
        } catch (Throwable $e) {
            $this->error('Could not reach the server: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->status() === 401 || $response->status() === 403) {
            $this->error('The server rejected this token. It may have been revoked - check Sync Devices.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Pairing failed: HTTP '.$response->status());

            return self::FAILURE;
        }

        $profile = $response->json();

        // The passcode unlocks this device only. It is never sent anywhere,
        // so the account's real password stays on the server.
        $passcode = (string) ($this->option('passcode') ?: $this->secret('Choose an offline passcode for this device'));

        if (strlen($passcode) < 8) {
            $this->error('The passcode must be at least 8 characters.');

            return self::FAILURE;
        }

        $this->establishLocalAccount($profile, $passcode);

        SyncSetting::put('server_url', $server);
        SyncSetting::put('device_token', $token);
        SyncSetting::put('paired_at', now()->toIso8601String());

        // Config was resolved at boot, before any of the above existed, so
        // the sync below would otherwise post to an empty URL.
        config([
            'tether-client.server_routes.push' => $server.'/tether/push',
            'tether-client.server_routes.pull' => $server.'/tether/pull',
            'sync.device_token' => $token,
        ]);

        // The first pull has to happen here, not later: active_institution_id
        // is a foreign key, and the institution row only exists on the device
        // once it has been synced.
        $this->info('Fetching this school\'s records...');

        if ($this->call('tether:sync') !== self::SUCCESS) {
            $this->error('Paired, but the first sync failed. Run `php artisan tether:sync` once you have a connection.');

            return self::FAILURE;
        }

        $this->attachToInstitution($profile);

        $this->info('Paired with '.($profile['institution']['name'] ?? 'the server').'.');
        $this->line('Sign in offline as '.$profile['user']['email'].' using the passcode you just set.');

        return self::SUCCESS;
    }

    /**
     * Build the local account the offline app authenticates against.
     *
     * The roles and permissions themselves are defined in code, so they're
     * seeded locally rather than fetched - only which roles this person
     * holds comes from the server.
     *
     * @param  array<string, mixed>  $profile
     */
    private function establishLocalAccount(array $profile, string $passcode): void
    {
        $this->callSilent('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

        $user = User::query()->find($profile['user']['id']);

        if (! $user instanceof User) {
            $user = new User;
        }

        // forceFill, because the id is not fillable - and it has to be the
        // server's id, or institution scoping and record ownership would
        // mean different things on the two sides.
        // active_institution_id is deliberately left unset here - see
        // attachToInstitution(), which runs once the row it points at has
        // actually arrived.
        $user->forceFill([
            'id' => $profile['user']['id'],
            'name' => $profile['user']['name'],
            'email' => $profile['user']['email'],
            'password' => Hash::make($passcode),
            'email_verified_at' => now(),
        ])->save();

        $user->syncRoles($profile['user']['roles'] ?? []);
    }

    /**
     * Point the local account at its school, now that the first sync has
     * brought the institution across.
     *
     * @param  array<string, mixed>  $profile
     */
    private function attachToInstitution(array $profile): void
    {
        $institutionId = $profile['institution']['id'] ?? null;

        if (! $institutionId || ! Institution::whereKey($institutionId)->exists()) {
            $this->warn('The school record did not arrive in the first sync; sign-in may be limited until the next one.');

            return;
        }

        User::whereKey($profile['user']['id'])->update(['active_institution_id' => $institutionId]);
    }
}
