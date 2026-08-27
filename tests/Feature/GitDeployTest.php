<?php

use App\Models\User;
use App\Services\GitDeploy;
use Database\Seeders\PermissionSeeder;
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
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse origin/main*' => Process::result('abc123'),
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
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse origin/main*' => Process::result('def456'),
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
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(output: '', errorOutput: 'Could not resolve host', exitCode: 1),
    ]);

    $result = app(GitDeploy::class)->pull();

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('Could not reach the remote');

    Process::assertDidntRun(commandContains('checkout'));
});

it('flags a release that changes dependencies instead of running composer', function () {
    // Stub Artisan: a real view:clear deletes Livewire's compiled
    // components and breaks whatever test runs next.
    Artisan::shouldReceive('call')->andReturn(0);

    Process::fake([
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse origin/main*' => Process::result('def456'),
        // Non-zero from `git diff --quiet` means composer.lock differs.
        '*diff --quiet*' => Process::result(output: '', errorOutput: '', exitCode: 1),
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
 * throws instead of rendering. Artisan is called in-process, so this spies
 * on the facade rather than looking for a subprocess.
 */
it('rebuilds the route cache, which a new route depends on', function () {
    Process::fake([
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse origin/main*' => Process::result('def456'),
        '*rev-list*' => Process::result('1'),
        '*log*' => Process::result('a release'),
        '*' => Process::result(''),
    ]);

    Artisan::shouldReceive('call')->andReturn(0);

    app(GitDeploy::class)->pull();

    Artisan::shouldHaveReceived('call')->with('route:clear');
    Artisan::shouldHaveReceived('call')->with('config:clear');
    Artisan::shouldHaveReceived('call')->with('view:clear');
});

it('rolls back when a cache rebuild fails', function () {
    Process::fake([
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse origin/main*' => Process::result('def456'),
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
        '*rev-parse HEAD*' => Process::result('abc123'),
        '*status*' => Process::result(''),
        '*fetch*' => Process::result(''),
        '*rev-parse origin/main*' => Process::result('def456'),
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
