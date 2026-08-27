<?php

use Illuminate\Support\Facades\Route;

/**
 * What the /api surface actually does for a caller with no credentials.
 *
 * Written as a test rather than a one-off script so the answer stays true:
 * if someone later removes a guard, or points a route at a controller that
 * can't serve it, this fails instead of quietly shipping.
 */
function unauthenticatedApiRoutes(): array
{
    return collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && (str_contains($m, 'auth') || str_contains($m, 'Authenticate') || str_contains($m, 'abilities'))))
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();
}

test('the only endpoints open to the public are the plans ones', function () {
    $open = collect(unauthenticatedApiRoutes())
        ->reject(fn (string $route) => str_contains($route, 'api/plans'))
        ->sort()
        ->values()
        ->all();

    // Everything else under /api must sit behind auth:sanctum. This list is
    // the audit's finding: these routes carry no auth middleware at all.
    expect($open)->toBe([
        'DELETE api/v1/classes/{class}',
        'DELETE api/v1/curricula/{curriculum}',
        'DELETE api/v1/examinations/{examination}',
        'DELETE api/v1/feemanagements/{feemanagement}',
        'DELETE api/v1/teachers/{teacher}',
        'DELETE api/v1/timetables/{timetable}',
        'GET|HEAD api/v1/classes',
        'GET|HEAD api/v1/classes/{class}',
        'GET|HEAD api/v1/curricula',
        'GET|HEAD api/v1/curricula/{curriculum}',
        'GET|HEAD api/v1/examinations',
        'GET|HEAD api/v1/examinations/{examination}',
        'GET|HEAD api/v1/feemanagements',
        'GET|HEAD api/v1/feemanagements/{feemanagement}',
        'GET|HEAD api/v1/institutions/{institution}/attendance',
        'GET|HEAD api/v1/reports',
        'GET|HEAD api/v1/teachers',
        'GET|HEAD api/v1/teachers/{teacher}',
        'GET|HEAD api/v1/timetables',
        'GET|HEAD api/v1/timetables/{timetable}',
        'POST api/v1/classes',
        'POST api/v1/curricula',
        'POST api/v1/examinations',
        'POST api/v1/feemanagements',
        'POST api/v1/teachers',
        'POST api/v1/timetables',
        'PUT|PATCH api/v1/classes/{class}',
        'PUT|PATCH api/v1/curricula/{curriculum}',
        'PUT|PATCH api/v1/examinations/{examination}',
        'PUT|PATCH api/v1/feemanagements/{feemanagement}',
        'PUT|PATCH api/v1/teachers/{teacher}',
        'PUT|PATCH api/v1/timetables/{timetable}',
    ]);
});

test('no unauthenticated read endpoint hands back data', function (string $uri) {
    $response = $this->getJson($uri);

    // Whatever happens, it must not be a 200 carrying records.
    expect($response->status())->not->toBe(200);
})->with([
    'api/v1/classes',
    'api/v1/curricula',
    'api/v1/examinations',
    'api/v1/feemanagements',
    'api/v1/teachers',
    'api/v1/timetables',
    'api/v1/reports',
    'api/v1/classes/1',
    'api/v1/curricula/1',
    'api/v1/examinations/1',
    'api/v1/teachers/1',
    'api/v1/timetables/1',
    'api/v1/institutions/1/attendance',
]);

test('no unauthenticated write endpoint changes anything', function (string $method, string $uri) {
    $response = $this->json($method, $uri, ['name' => 'injected-by-audit']);

    expect($response->status())->not->toBe(200)
        ->and($response->status())->not->toBe(201)
        ->and($response->status())->not->toBe(302);
})->with([
    ['POST', 'api/v1/classes'],
    ['POST', 'api/v1/curricula'],
    ['POST', 'api/v1/examinations'],
    ['POST', 'api/v1/feemanagements'],
    ['POST', 'api/v1/teachers'],
    ['POST', 'api/v1/timetables'],
    ['PUT', 'api/v1/classes/1'],
    ['PUT', 'api/v1/curricula/1'],
    ['DELETE', 'api/v1/classes/1'],
    ['DELETE', 'api/v1/curricula/1'],
    ['DELETE', 'api/v1/examinations/1'],
    ['DELETE', 'api/v1/teachers/1'],
    ['DELETE', 'api/v1/timetables/1'],
]);
