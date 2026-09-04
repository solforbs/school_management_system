<?php

use App\Models\User;
use App\Services\GitDeploy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

/*
 * The Pull button deploys code in response to an HTTP request, so what
 * matters most here is what it refuses to do: run while disabled, run for
 * anyone but an Admin, run twice at once, pull over someone's uncommitted
 * work, or leave a half-applied deploy behind when a step fails.
 */

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    config()->set('deploy.enabled', true);
    config()->set('deploy.remote', 'origin');
    config()->set('deploy.branch', 'main');
});

function admin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    return $user;
}

/**
 * Commands are invoked as argument arrays rather than shell strings - that
 * is what stops anything from the browser reaching a shell - so the fake
 * records an array, not a string.
 */
function commandContains(string $needle): Closure
{
    return function ($process) use ($needle) {
        $command = $process->command;

        return str_contains(is_array($command) ? implode(' ', $command) : $command, $needle);
    };
}

it('does nothing at all while switched off', function () {
    config()->set('deploy.enabled', false);
    Process::fake();

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('refused');
    Process::assertNothingRan();
});

it('refuses to pull over uncommitted work on the server', function () {
    Process::fake([
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(' M app/Http/Controllers/DevicesController.php'),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('refused')
        ->and($result->message)->toContain('uncommitted changes');

    // Crucially it stopped before fetching, so nothing moved.
    Process::assertDidntRun(commandContains('fetch'));
});

it('reports being up to date without touching anything', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('abc123'),
        '*log*' => Process::result('an earlier commit'),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('up_to_date');
    Process::assertDidntRun(commandContains('checkout'));
});

it('resets to the recorded commit when a step fails', function () {
    // Stub Artisan: a real view:clear deletes Livewire's compiled
    // components and breaks whatever test runs next.
    Artisan::shouldReceive('call')->andReturn(0);

    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('def456'),
        '*diff*' => Process::result(''),
        '*checkout*' => Process::result(output: '', errorOutput: 'cannot lock ref', exitCode: 1),
        '*' => Process::result(''),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('Checkout failed');

    // The reset must name the commit recorded before anything moved - not
    // whatever HEAD happens to be after a partial checkout.
    Process::assertRan(commandContains('reset --hard abc123'));
});

it('reports a remote it cannot reach without changing anything', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(output: '', errorOutput: 'Could not resolve host', exitCode: 1),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('Could not reach the remote');

    Process::assertDidntRun(commandContains('checkout'));
});

it('explains a missing credential instead of the generic network error', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(
            output: '',
            errorOutput: "fatal: could not read Username for 'https://github.com': No such device or address",
            exitCode: 1,
        ),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('DEPLOY_TOKEN')
        ->and($result->message)->not->toContain('Could not reach the remote');
});

it('passes a configured token to the fetch as an auth header, scoped to the remote host', function () {
    config()->set('deploy.token', 'ghp_secrettoken');

    Process::fake([
        '*remote get-url*' => Process::result('https://github.com/solforbs/school_management_system.git'),
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('abc123'),
        '*log*' => Process::result('a commit'),
        '*' => Process::result(''),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('up_to_date');

    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'fetch')
            && str_contains($command, 'http.https://github.com/.extraheader=AUTHORIZATION: basic ')
            && str_contains($command, base64_encode('x-access-token:ghp_secrettoken'))
            // The raw token itself is never on the command line.
            && ! str_contains($command, 'ghp_secrettoken:');
    });
});

it('names the permission problem when the web user cannot write .git', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(
            output: '',
            errorOutput: "error: cannot open '.git/FETCH_HEAD': Permission denied",
            exitCode: 1,
        ),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('cannot write the git checkout')
        ->and($result->message)->toContain('chown')
        // Not the misleading network message.
        ->and($result->message)->not->toContain('Could not reach the remote');

    Process::assertDidntRun(commandContains('checkout'));
});

it('flags a release that changes dependencies instead of running composer', function () {
    // Stub Artisan: a real view:clear deletes Livewire's compiled
    // components and breaks whatever test runs next.
    Artisan::shouldReceive('call')->andReturn(0);

    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('def456'),
        // Non-zero from `git diff --quiet` means composer.lock differs.
        '*diff*' => Process::result(output: '', errorOutput: '', exitCode: 1),
        '*checkout*' => Process::result(''),
        '*rev-list*' => Process::result('3'),
        '*log*' => Process::result('some release'),
        '*' => Process::result(''),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('deployed')
        ->and($result->dependenciesChanged)->toBeTrue();

    // composer is deliberately never invoked from a web request. Matching on
    // "composer" alone would catch `git diff -- composer.lock`, which is the
    // very command that detects the change.
    Process::assertDidntRun(commandContains('composer install'));
});

/*
 * A stale route cache is what breaks a deploy that otherwise looked fine:
 * a newly added route resolves to nothing and every page referencing it
 * throws instead of rendering. Those clears run in-process, so this spies
 * on the facade rather than looking for a subprocess. view:clear is
 * deliberately not here - it is deferred past the response so it cannot
 * delete the compiled view this request is still rendering from.
 */
it('rebuilds the config and route caches, which a new route depends on', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('def456'),
        '*rev-list*' => Process::result('1'),
        '*log*' => Process::result('a release'),
        '*' => Process::result(''),
    ]);

    Artisan::shouldReceive('call')->andReturn(0);

    app(GitDeploy::class)->pull();

    Artisan::shouldHaveReceived('call')->with('route:clear');
    Artisan::shouldHaveReceived('call')->with('config:clear');
});

it('rolls back when a cache rebuild fails', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('def456'),
        '*' => Process::result(''),
    ]);

    Artisan::shouldReceive('call')->andReturn(1);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('Rolled back');

    Process::assertRan(commandContains('reset --hard abc123'));
});

// ------------------------------------------------------------------
// The button
// ------------------------------------------------------------------

it('is refused to a Director', function () {
    Process::fake();

    $director = User::factory()->create();
    $director->assignRole('Director');

    Livewire::actingAs($director)
        ->test('pull-updates')
        ->call('pullUpdates')
        ->assertForbidden();

    Process::assertNothingRan();
});

it('is refused to a guest', function () {
    Process::fake();

    Livewire::test('pull-updates')
        ->call('pullUpdates')
        ->assertForbidden();

    Process::assertNothingRan();
});

it('runs for an Admin and reports back on the page', function () {
    // Stub Artisan: a real view:clear deletes Livewire's compiled
    // components and breaks whatever test runs next.
    Artisan::shouldReceive('call')->andReturn(0);

    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('def456'),
        '*rev-list*' => Process::result('2'),
        '*log*' => Process::result('set the terminal clock from the server'),
        '*' => Process::result(''),
    ]);

    Livewire::actingAs(admin())
        ->test('pull-updates')
        ->call('pullUpdates')
        ->assertSet('status', 'deployed')
        ->assertSee('2 changes pulled');
});

/*
 * DashboardController sends an Admin to the report page, so that - not
 * dashboard.blade.php, which they never see - is where the card belongs.
 */
it('shows the card on the page an Admin actually lands on', function () {
    config()->set('deploy.branch', 'main');

    $this->actingAs(admin())
        ->get(route('report.index'))
        ->assertOk()
        ->assertSee('Pull updates')
        ->assertSee('Application updates');
});

it('renders nothing at all for a Director', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    Livewire::actingAs($director)
        ->test('pull-updates')
        ->assertDontSee('Pull updates')
        ->assertDontSee('Application updates');
});

it('hides the card entirely while pulling is switched off', function () {
    config()->set('deploy.enabled', false);

    $this->actingAs(admin())
        ->get(route('report.index'))
        ->assertOk()
        ->assertDontSee('Pull updates');
});

// ------------------------------------------------------------------
// Migrations and seeders
// ------------------------------------------------------------------

/**
 * A diff that carries a migration file, and the catch-all for everything
 * else a pull runs.
 *
 * @param  list<string>  $changed
 */
function deployFake(array $changed = []): void
{
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('def456'),
        '*diff --name-only*' => Process::result(implode("\n", $changed)),
        '*rev-list*' => Process::result('1'),
        '*log*' => Process::result('a release'),
        '*' => Process::result(''),
    ]);
}

it('runs migrations when a release adds a migration file', function () {
    deployFake(['database/migrations/2099_01_01_000000_add_a_column.php']);

    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function ($command) use (&$calls) {
        $calls[] = $command;

        return 0;
    });
    Artisan::shouldReceive('output')->andReturn('INFO  Running migrations.  Migrated: 2099_01_01_000000_add_a_column');

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('deployed')
        ->and($result->migrations)->toContain('migration')
        ->and($calls)->toContain('migrate');
});

it('does not run migrations when no migration file changed', function () {
    deployFake(['app/Services/GitDeploy.php']);

    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function ($command) use (&$calls) {
        $calls[] = $command;

        return 0;
    });
    Artisan::shouldReceive('output')->andReturn('');

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('deployed')
        ->and($calls)->not->toContain('migrate');
});

it('leaves the code in place and asks for a look when a migration fails', function () {
    deployFake(['database/migrations/2099_01_01_000000_add_a_column.php']);

    Artisan::shouldReceive('call')->andReturnUsing(fn ($command) => $command === 'migrate' ? 1 : 0);
    Artisan::shouldReceive('output')->andReturn('SQLSTATE[42S21]: Column already exists');

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('needs_attention')
        ->and($result->message)->toContain('migration failed');

    // No rollback: a half-applied schema under the old code is worse.
    Process::assertDidntRun(commandContains('reset --hard'));
});

it('lists the seeder classes a release changed without running them', function () {
    Artisan::shouldReceive('call')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    deployFake(['database/seeders/SettingSeeder.php']);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('deployed')
        ->and($result->pendingSeeders)->toContain(SettingSeeder::class);

    Process::assertDidntRun(commandContains('db:seed'));
});

it('runs a listed seeder only on explicit request', function () {
    $calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function ($command, $params = []) use (&$calls) {
        $calls[] = [$command, $params];

        return 0;
    });

    $result = app(GitDeploy::class)->runSeeders([SettingSeeder::class]);

    expect($result->ok())->toBeTrue()
        ->and($result->message)->toContain('SettingSeeder')
        ->and($calls)->toContain(['db:seed', ['--class' => SettingSeeder::class, '--force' => true]]);
});

it('refuses to run anything that is not a real seeder', function () {
    Process::fake();

    $result = app(GitDeploy::class)->runSeeders(['App\\Services\\GitDeploy', 'Nope\\NotReal']);

    expect($result->status)->toBe('refused')
        ->and($result->message)->toContain('Not a seeder');

    Process::assertNothingRan();
});

it('the deploy:pull command reports an up-to-date server and exits zero', function () {
    Process::fake([
        '*rev-parse*HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse*origin*' => Process::result('abc123'),
        '*log*' => Process::result('a commit'),
        '*' => Process::result(''),
    ]);

    $this->artisan('deploy:pull')
        ->expectsOutputToContain('Already up to date')
        ->assertExitCode(0);
});

it('the deploy:pull command exits non-zero when the pull is refused', function () {
    config()->set('deploy.enabled', false);
    Process::fake();

    $this->artisan('deploy:pull')
        ->expectsOutputToContain('switched off')
        ->assertExitCode(1);

    Process::assertNothingRan();
});
