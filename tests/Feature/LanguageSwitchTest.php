<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function directorWithInstitutionLocale(string $locale): User
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Lugha Academy',
        'code' => 'LUGHA',
        'type' => 'School',
        'email' => 'info@lugha.test',
        'phone' => '+254700000001',
        'is_approved' => true,
        'locale' => $locale,
    ]);

    $director->forceFill(['active_institution_id' => $institution->id])->save();

    return $director;
}

test('only english, arabic, french and swahili are offered', function () {
    expect(array_keys(config('locales.available')))->toBe(['en', 'ar', 'fr', 'sw']);
});

test('every language has all the switcher phrases', function () {
    $phrases = array_keys(config('locales.available.en.switcher'));

    foreach (config('locales.available') as $code => $language) {
        expect(array_keys($language['switcher']))->toBe($phrases, "{$code} is missing switcher phrases");
    }
});

test('guests see the app in english by default', function () {
    $this->get(route('login'))->assertOk()->assertSee('<html lang="en" dir="ltr"', false);
});

test('a person switching language gets that language and an undo offer', function () {
    $this->from(route('login'))
        ->post(route('language.update'), ['locale' => 'fr'])
        ->assertRedirect(route('login'))
        ->assertCookie('locale', 'fr')
        ->assertSessionHas('locale_previous', 'en');
});

test('the chosen language is used on the next page', function () {
    $this->withCookie('locale', 'ar')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false);
});

test('right after a switch the undo banner speaks the previous language', function () {
    $this->withCookie('locale', 'ar')
        ->withSession(['locale_previous' => 'en'])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('data-test="language-undo-banner"', false)
        ->assertSee('Go back to English');
});

test('undo does not offer another undo', function () {
    $this->withCookie('locale', 'fr')
        ->from(route('login'))
        ->post(route('language.update'), ['locale' => 'en', 'undo' => '1'])
        ->assertSessionMissing('locale_previous');
});

test('an unknown language is refused', function () {
    $this->from(route('login'))
        ->post(route('language.update'), ['locale' => 'de'])
        ->assertSessionHasErrors('locale')
        ->assertCookieMissing('locale');
});

test('lang=en in the address always brings the app back to english', function () {
    $this->withCookie('locale', 'ar')
        ->get(route('login', ['lang' => 'en']))
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr"', false);
});

test('an unknown lang in the address is ignored', function () {
    $this->withCookie('locale', 'sw')
        ->get(route('login', ['lang' => 'xx']))
        ->assertOk()
        ->assertSee('<html lang="sw" dir="ltr"', false);
});

test('signed-in users get their institution language', function () {
    $this->actingAs(directorWithInstitutionLocale('sw'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<html lang="sw"', false);
});

test('a personal choice overrides the institution language', function () {
    $this->actingAs(directorWithInstitutionLocale('sw'))
        ->withCookie('locale', 'fr')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<html lang="fr"', false);
});

test('choosing the institution language clears the personal choice', function () {
    $this->actingAs(directorWithInstitutionLocale('sw'))
        ->withCookie('locale', 'fr')
        ->from(route('dashboard'))
        ->post(route('language.update'), ['locale' => 'sw'])
        ->assertCookieExpired('locale');
});

test('updating an institution without a language keeps its language', function () {
    $director = directorWithInstitutionLocale('sw');
    $institution = Institution::first();

    $this->actingAs($director)
        ->put(route('institution.update', $institution->id), [
            'name' => 'Renamed Academy',
            'code' => $institution->code,
            'type' => 'School',
            'email' => $institution->email,
            'timezone' => 'Africa/Nairobi',
            'is_active' => 1,
        ])
        ->assertSessionHasNoErrors();

    expect($institution->refresh()->name)->toBe('Renamed Academy')
        ->and($institution->locale)->toBe('sw');
});

test('an institution can not be saved with a language outside the four', function () {
    $director = directorWithInstitutionLocale('en');
    $institution = Institution::first();

    $this->actingAs($director)
        ->put(route('institution.update', $institution->id), [
            'name' => $institution->name,
            'code' => $institution->code,
            'type' => 'School',
            'email' => $institution->email,
            'timezone' => 'Africa/Nairobi',
            'locale' => 'de',
        ])
        ->assertSessionHasErrors('locale');
});
