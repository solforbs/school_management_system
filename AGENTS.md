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

- Attendance Intelligence Suite: Biometric Logs Rate Fix, Class Teacher Authority & Absence SMS Confirmation (resolved the misleading 100% attendance rate bug on `attendance.index` by returning 0% when zero logs are recorded; engineered intelligent context-aware attendance banner in `attendance.register` that detects biometric gate check-in time windows [e.g. "between 8:05 AM and 8:45 AM through Main Gate Scanner"] and guides the teacher to confirm desk presence, alerts teachers to manually conduct roll-call if no biometric scans occurred or devices are offline, and clearly displays recorded timestamp, staff name, and class teacher authority once saved; added Homeroom Class Teacher authority verification badge; empowered Director dashboard with unified attendance analytics combining gate logs and classroom roll-call with live class completion ratios; implemented interactive Parent Absence Notification preview & confirmation modal preventing accidental SMS dispatches and allowing customized wording before sending via `SmsService`; added comprehensive Pest test suite `ClassAttendanceRegisterTest` passing 100% with 54 assertions).
- Teacher Classroom Roll-Call Register Mobile Responsiveness & Sticky Action Suite (resolved mobile layout crowding on `attendance.register`; converted rigid 3-column KPI strip into responsive 2-column mobile layout with full-width bottom Attendance Rate card featuring real-time Alpine progress indicator bar; eliminated "ATTENDANCE RATE" text overflow and icon collision bugs; transformed Switch Class bar from narrow lateral flex split into vertical responsive stack with equal-width touch-friendly buttons; upgraded roll-call table headers with responsive icons on small screens [`<flux:icon icon="check">` / `<flux:icon icon="x-mark">`] preventing "Present" / "Absent" text clipping; enlarged mobile tick buttons to `size-7` [28px] with `active:scale-95` tap feedback for rapid thumb roll-call; integrated fixed mobile bottom sticky bar with live Present/Absent counters and 1-tap "Save attendance" button eliminating endless scrolling past 40+ learners).
- Teacher Classroom Attendance Register & Biometric Gate Sync Integration (engineered unified daily roll-call architecture bridging hardware ZKTeco biometric terminals with teacher manual roll-call fallback; created `attendances` table with institution, class, student, date, status, source [`biometric`, `manual`, `verified`, `override`], device_id, scanned_at, recorded_by, parent_notified, and notes; implemented `ClassAttendanceService` handling student roster resolution, gate scan detection, automatic pre-ticking of students who scanned at gate, verified vs override source assignment, and transactional persistence; built Livewire Volt component `⚡register.blade.php` adapted directly from Kurasa workspace UI into Solforbs enterprise brand-blue theme: split two-column desktop register [responsive single column on mobile] with `#`, Student Name, Gate Scan indicator badge [`⚡ Gate 7:35 AM`], brand-blue Present checkmark `✓` [`bg-blue-600`], and clean rose Absent `✕`; live KPI summary header cards for Present, Absent, and Attendance rate [%]; 1-click "Mark all present" and "Clear all" fast actions; right sidebar with dynamic "Absent today" list featuring individual "Parent notified" toggle switches, character-counted daily Notes [0/200], and full-width Save action with loading spinner and alert banner; connected `attendance.register` route with seamless tab switching between Roll-Call Register and Biometric Stream, integrated directly into Homeroom Workspace card on Teacher Dashboard and sidebar navigation; added comprehensive Pest test suite `ClassAttendanceRegisterTest.php` passing 100% with 31 assertions).
- CBC Band Entry Interactive State, Uncheck Toggle & Live Indicators (resolved band invisibility where already-recorded marks showed unselected white pills; replaced brittle CSS peer-checked radio inputs with single-source Alpine state `bands` preventing duplicate desktop/mobile POST input collisions; built toggle-to-uncheck pill pickers allowing teachers to click an active band to deselect it back to empty; added 1-click `×` clear buttons and live colored Current Evaluation badges `[● BE1 · Minimal]` with band-specific badges [EE: emerald, ME: blue, AE: amber, BE: rose]; added live header summary counters showing Recorded vs Pending learners; enhanced `BandEntryController::store` with safe deletion of cleared records when teachers uncheck a student's mark to correct mistakes; added comprehensive Pest feature tests passing 100%).
- Student Directory Zero-Overflow Table & Instant Filter Bar Redesign (eliminated awkward stepped line-breaks, wrapping dropdowns, and clumsy "Apply Filters" button from the student directory filter toolbar; built a clean, single-row instant filter bar on a white card using debounced Livewire bindings `wire:model.live.debounce.300ms="search"` and instant dropdown filters for Class, Status, and Gender with 1-click active scope chips and clear button; removed out-of-place "Bulk Spreadsheet Entry" button from directory; eliminated horizontal page scrollbars by locking the table to `table-fixed w-full overflow-hidden` with balanced column width ratios [4/12, 3/12, 3/12, 2/12]; added `whitespace-nowrap` on class names and phone numbers to eliminate awkward line breaks like `Form 1` / `East` and `+254` / `784071517`; strictly applied clean white/zinc UI eliminating blue visual clutter while preserving fluid mobile stacked card presentation).
- Teacher Scoping, Homeroom Workspace & Teaching Allocations Hub (eliminated global class/subject clutter for teachers across formative assessment and results workflows; scoped class & subject dropdowns to `ResultAccessService::selectableClasses` and `selectableSubjects` so instructors only see classes and learning areas they actually teach; added server-side `canGrade` validation in `FormativeAssessmentController::store`; engineered role-scoped `AnalyticsService::teacherStats` delivering designated Homeroom Class details, student counts strictly scoped to taught classes, and individual `SubjectTeacher` allocations; transformed teacher dashboard `_teacher.blade.php` with prominent Homeroom Class Teacher Workspace card for roll-call, roster, and remarks, paired with responsive My Teaching Allocations grid offering 1-click access to CBC band marksheets and 8-4-4 marks entry while keeping global sidebar completely intact; added comprehensive Pest test suite `TeacherScopingTest` passing with 100% assertions).
- CBC Eight-Point Scale, Senior School Career Pathways & Multi-Year Cumulative SBA (aligned Competency-Based Assessment with official KNEC guidelines; added 8 achievement sub-levels EE1–BE2 with points 8 to 1 alongside 4 broad bands EE–BE in ResultGrader; engineered `CbcPathwayService` providing automated Senior School career pathway guidance into STEM, Social Sciences, and Arts & Sports Science based on learner cluster achievement without norm-referenced ranking; engineered `CbcCumulativeSbaService` aggregating multi-year 60% continuous internal SBA across Grades 7, 8, 9 and 40% Grade 9 KJSEA national summative exam for KNEC portal reporting; enhanced `report-card.blade.php` PDF template with criterion-referenced summary and Senior School Career Pathways Guidance table; added comprehensive Pest test suite `CbcEightPointAndPathwayTest` with zero regressions on 8-4-4 or IGCSE).
- CBC Assessment Framework & Formative Direct Band Architecture (engineered full Competency Based Curriculum assessment pipeline accommodating both CBC and 8-4-4/standard curricula with zero breaking changes; added `assessment_category` to `exam_sittings` with `classroom_assessment`, `sba`, and `summative` modes; added `cbc_band` and relaxed `marks_obtained` on `results` for direct EE/ME/AE/BE band entries; added CBC `learning_area`, `strand`, and `sub_strand` structures to `subjects` with conditional rendering in `⚡form.blade.php`; built `FormativeAssessmentController` and Volt screen `⚡formative.blade.php` for lightweight assessment creation skipping paper fanout; built `BandEntryController` and Volt sheet `⚡band-entry.blade.php` with responsive pill pickers; enhanced `ResultGrader::fromBand()` and `ReportSheetBuilder` with `cbcRows()` predominant-band resolution and CBC report card PDF presentation).
- Biometric & Offline Sync Devices mobile responsiveness & empty state fix (eliminated table-trapped empty state bug where `Campus / Institution` and `Network IP` headers floated awkwardly over clipped text; extracted `<x-empty-state>` to a clean full-width card; engineered 2-column mobile KPI grid with full-width bottom cards cutting vertical header bloat in half; implemented adaptive dual-mode presentation with touch-friendly stacked cards on mobile and high-density tables on desktop across `devices.index` and `sync-devices.index`).
- Header action buttons wrapping & mobile 2-column KPI optimization (eliminated horizontal button row overflow on mobile across FeeManagement, Examinations, Curriculum, Subject, Classes, and ReportCard by converting rigid flex rows to responsive `flex-wrap gap-2 sm:gap-3 w-full sm:w-auto`; refactored executive KPI cards from single-column vertical stacks to responsive 2-column mobile grids with full-width bottom cards, cutting mobile vertical scrolling in half).
- Attendance stream date range breakout fix & compact mobile dashboard (eliminated browser-enforced `<input type="date">` container breakout bug where side-by-side date inputs overflowed the right card boundary on mobile screens; engineered responsive vertical stacking on phones with 100% width inputs and dedicated labels, 2-column layout on tablets, and inline row on desktop; converted Attendance filter controls into a collapsible Alpine mobile drawer with active badge dot; restructured KPI metric cards into a 2x2 grid on mobile cutting header vertical bloat in half).
- Universal Filter Toolbar mobile collapsible drawer (eliminated ~300px vertical screen hogging across Student, Staff, Subject, ReportCard, and Fee directories; engineered single-row search bar with touch-friendly `Filters` toggle button and active filter indicator badge on mobile, revealing collapsible filter controls on demand while preserving the unified single-row desktop layout).
- Mobile global viewport lock & 3-column aside grid elimination (eliminated the horizontal drift/zoom-out bug that created an empty ~40% white/gray gutter on mobile across all pages; scoped desktop grid-template-areas with `aside` to `@media (min-width: 1024px)`; established fluid flex-column single-width layout on mobile with `overflow-x: clip` and locked `maximum-scale=1.0, user-scalable=no`).
- People & Community Show dossiers & universal stepper form mobile responsiveness (engineered adaptive mobile stepper pill strip across all Create & Edit forms eliminating vertical step list bloat; refactored Show dossiers across Staff, Teacher, Student, and Parent with compact 2-column key-value specification grids cutting vertical scrolling in half; added dual-mode mobile cards for payroll disbursements with zero horizontal scrollbars).
- People & Community high-density modern SaaS mobile directory redesign (eliminated cramped 2-column splitting, badge line wrapping like `STF-` / `2004`, truncated names and job titles; engineered high-density full-width context hierarchy with avatar, non-wrapping identifier badges, status pills, dedicated role/class sublines, one-tap phone/email links, and streamlined action footers across Student, Teacher, Staff, Parent, and Attendance views, shrinking card heights by ~40% and fitting 3-4 records per mobile screen).
- People & Community mobile responsiveness & adaptive card architecture (eliminated horizontal table clipping and overflow across Student, Teacher, Staff, Parent, and Attendance directories; implemented adaptive dual-mode presentation with touch-friendly stacked cards on mobile [`block sm:hidden`] and high-density multi-column data tables on desktop [`hidden sm:block min-w-[720px]`]; fluid filter toolbars, touch-friendly action buttons, and scrollable preset pill strips)
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
