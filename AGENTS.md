=== project state ===

# School Management System — Project State

> This section is the living record of the project. Update it whenever a significant feature is built or a major bug is fixed.

## Application Overview
A multi-tenant SaaS school management system for Kenyan/African schools (solforbs.com). Built with Laravel 13 + Livewire 4 + Flux UI. Uses nwidart/laravel-modules for feature modules.

## Infrastructure
- PHP 8.3, Laravel 13, Livewire 4, Flux UI 2.x
- Spatie permissions, Laravel Socialite (Google OAuth), Laravel Fortify (auth)
- PhpSpreadsheet for Excel import/export
- Tether (offline sync client/server)
- ZKTeco biometric attendance device integration
- Cloudinary for image storage
- Laravel Pao + Pest for testing

## Modules Built (18 total)

| Module | Status | Notes |
|---|---|---|
| Institution | ✅ Complete | Multi-tenant, plans/subscriptions, demo school |
| Student | ✅ Complete | CRUD, bulk upload (xlsx/csv), export, enrollment |
| Teacher | ✅ Complete | CRUD, subject/class assignments, enrollment permission |
| Parent | ✅ Complete | Scoped to own children, fee visibility |
| Staff | ✅ Complete | Payroll/payslips |
| Classes | ✅ Complete | School classes with curriculum links |
| Subject | ✅ Complete | Subjects, compulsory flag, teacher assignment |
| Timetable | ✅ Complete | Per-class timetable grid, subject-based |
| Attendance | ✅ Complete | ZKTeco biometric + manual, parent-visible |
| Lesson | ✅ Complete | Lesson reports, recovery tracking |
| Examinations | ✅ Complete | Exam sittings, per-subject results |
| Result | ✅ Complete | Results entry, subject_id column |
| ReportCard | ✅ Complete | PDF generation, download tokens |
| Report | ✅ Complete | Analytics export (chunked, type-safe) |
| FeeManagement | ✅ Complete | Fee records, payments, Paystack integration |
| Expenditure | ✅ Complete | Categories, expenditure records |
| Curriculum | ✅ Complete | CBC/IGCSE/etc, grading bands |
| Selections | ✅ Complete | Student elective subject selections |

## Auth & Access
- Roles: SuperAdmin, Director, Teacher, Student, Parent, Staff
- Google OAuth ("Continue with Google") via Socialite
- Passkeys support
- Demo school (shared login, restricted tabs — Settings opens Appearance, not Profile)
- Teacher self-enrollment permission (`can_enroll_students` flag on teacher_details)

## Recently Completed (last ~2 weeks)
- Reports & Analytics mobile responsiveness & clean export (eliminated horizontal scrollbars on mobile view across Institution, Parent, Student, and System dashboards by replacing fixed-width tables with fluid stacked responsive list items; wrapped ApexCharts with min-w-0 containers; preserved fast and direct multi-tab Excel export)
- Financial reports workbook export fix & performance optimization (fixed missing download dialog by adding target="_blank" to form so Livewire does not trap the streamed file response; eliminated 1,009 N+1 queries by withCounting children; optimized PhpSpreadsheet column sizing to avoid calculating character metrics on large datasets; capped unfiltered fee rows in workbook to 5,000 to prevent 30-second PHP timeouts & 128MB memory exhaustion)
- Institution module UX, validation alignment & error handling (removed phone trap on update so phone is optional everywhere; added clear success and error alert banners across form and show views; added helper descriptions for electives, code, and timezone; added safe database exception handling for duplicate codes; ensured full mobile responsiveness with touch-friendly sticky action footer)
- Student single enrollment relaxation & unified profile editing (mandatory fields relaxed to just student name; auto-generated emails, passwords, and unique admission numbers; profile photos, parent info, and medical details made optional at creation and editable anytime later; covered teacher self-enrollment in controller and user model synchronization on update)
- Student bulk upload UX & safety suite (streamlined zero-horizontal-overflow table layout, dedicated Excel import tab with drag-and-drop & guide, collapsible roster accordion, class-switch data loss prevention modal with Save & Switch / Discard & Switch, auto-draft restore, friendly duplicate error handling)
- Comprehensive multi-device responsiveness (Director, Accountant, Teacher, Parent, Student dashboards + global CSS helpers)
- Student bulk upload page (xlsx/csv import + spreadsheet grid entry)
- Teacher self-enrollment permission
- Demo school: Settings redirects to Appearance (not 403)
- Analytics export type-safety fix (`lazy()` not `chunk()`)
- Dashboard layout overflow fixes
- PWA installable (manifest, install button)
- Preloader for wire:navigate
- Chatbot: typing indicator, step-by-step code delivery
- Security fixes: parent scoping, IDOR, env-write, session hardening
- Google OAuth: Continue with Google on login page

## Known Gotchas
- `laravel/socialite` vendor package can be missing after a fresh pull — always run `composer install` if tests throw "Class not found" errors before debugging code.
- `test_rel.php` in root is a scratch debug file (UTF-16 encoded, ignore it).
- PHPStan baseline is at `phpstan-baseline.neon` — keep it clean, don't add new entries without good reason.

=== dependency checks ===

# Dependency Health Checks

- When tests fail with `Class "..." not found`, check `vendor/` FIRST before touching any code:
  1. Run `Test-Path vendor/<package-path>` to confirm the package exists locally.
  2. If missing, run `composer install` to sync from `composer.lock`.
  3. Only investigate code after confirming all packages are installed.
- After any `git pull`, proactively run `composer install` and `npm install` to avoid stale vendor/node_modules.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project keeps committed, area-grouped rules in `.ai/rules` (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
