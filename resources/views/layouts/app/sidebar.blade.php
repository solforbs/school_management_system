<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" >

<head>
    @include('partials.head')
</head>
@php
    use Nwidart\Modules\Facades\Module;
@endphp

<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200/80 bg-white shadow-[1px_0_4px_0_rgba(0,0,0,0.02)] dark:border-zinc-800 dark:bg-zinc-950">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            @if (currentInstitution())
                <div class="px-2 pt-1 pb-2">
                    <a href="{{ route('institution.index') }}" class="group flex items-center justify-between p-2 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 hover:border-zinc-300 transition shadow-2xs" title="Switch or Manage Campuses">
                        <div class="min-w-0 flex-1">
                            <flux:text size="sm" class="font-bold text-zinc-900 dark:text-zinc-100 truncate block group-hover:text-blue-600 transition">
                                {{ currentInstitution()->name }}
                            </flux:text>
                            <span class="text-[11px] text-zinc-400 font-mono block mt-0.5">Active Campus</span>
                        </div>
                        <flux:icon icon="chevron-up-down" class="size-4 text-zinc-400 group-hover:text-zinc-600 shrink-0 ml-1.5" />
                    </a>
                </div>
            @endif

            {{-- 1. Executive & Overview Group (Always visible, compact) --}}
            <flux:sidebar.group :heading="__('Executive')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @if (hasInstitutions() && !isAdmin() && institutionApproved() && currentInstitution())
                    @if (isDirector() || auth()->user()->can('view institution'))
                        <flux:sidebar.item icon="building-office-2" :href="route('institution.index')"
                            :current="request()->routeIs('institution.*')" wire:navigate>
                            {{ __('Institutions') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Report') && (auth()->user()->can('view report') || auth()->user()->can('create report') || auth()->user()->can('export report')))
                        <flux:sidebar.item icon="chart-bar" :href="route('report.index')"
                            :current="request()->routeIs('report.*')" wire:navigate>
                            {{ __('Reports & Analytics') }}
                        </flux:sidebar.item>
                    @endif
                @endif
            </flux:sidebar.group>

            {{-- Admin Only Area --}}
            @hasrole('Admin')
                <flux:sidebar.group icon="rectangle-stack" :heading="__('Administration')" class="grid" expandable
                    :expanded="request()->routeIs('admin.*')">
                    <flux:sidebar.item icon="eye" :href="route('admin.modules.index')"
                        :current="request()->routeIs('admin.modules.index')" wire:navigate>
                        {{ __('View Modules') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="plus" :href="route('admin.modules.create')"
                        :current="request()->routeIs('admin.modules.create')" wire:navigate>
                        {{ __('Create Module') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="home-modern" :href="route('institution.index')"
                        :current="request()->routeIs('institution.index')" wire:navigate>
                        {{ __('Institutions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('admin.plans.index')"
                        :current="request()->routeIs('admin.plans.*')" wire:navigate>
                        {{ __('Plans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="envelope" :href="route('messages.index')"
                        :current="request()->routeIs('messages.*')" wire:navigate>
                        {{ __('Messages') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.settings.edit')"
                        :current="request()->routeIs('admin.settings.*')" wire:navigate>
                        {{ __('Site Settings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endhasrole

            @if (hasInstitutions() && !isAdmin() && institutionApproved() && currentInstitution())
                {{-- 2. Academic Delivery Domain (Expandable) --}}
                <flux:sidebar.group icon="academic-cap" :heading="__('Academics')" class="grid" expandable
                    :expanded="request()->routeIs('classes.*') || request()->routeIs('curriculum.*') || request()->routeIs('subject.*') || request()->routeIs('timetable.*') || request()->routeIs('lesson.*') || request()->routeIs('examinations.*') || request()->routeIs('result.*') || request()->routeIs('reportcard.*') || request()->routeIs('selections.*')">
                    @hasrole('Student')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('selections.index')"
                            :current="request()->routeIs('selections.*')" wire:navigate>
                            {{ __('Selections') }}
                        </flux:sidebar.item>
                    @endhasrole

                    @if (institutionHasModule('Classes') && (auth()->user()->can('view classes') || auth()->user()->can('edit classes') || auth()->user()->can('create classes')))
                        <flux:sidebar.item icon="rectangle-group" :href="route('classes.index')"
                            :current="request()->routeIs('classes.*')" wire:navigate>
                            {{ __('Classes & Streams') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Curriculum') && (auth()->user()->can('view curriculum') || auth()->user()->can('edit curriculum') || auth()->user()->can('create curriculum')))
                        <flux:sidebar.item icon="queue-list" :href="route('curriculum.index')"
                            :current="request()->routeIs('curriculum.*')" wire:navigate>
                            {{ __('Curricula') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Subject') && (auth()->user()->can('view subject') || auth()->user()->can('edit subject') || auth()->user()->can('create subject')))
                        <flux:sidebar.item icon="book-open" :href="route('subject.index')"
                            :current="request()->routeIs('subject.*')" wire:navigate>
                            {{ __('Subjects') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Timetable') && (auth()->user()->can('view timetable') || auth()->user()->can('edit timetable') || auth()->user()->can('create timetable')))
                        <flux:sidebar.item icon="calendar-days" :href="route('timetable.index')"
                            :current="request()->routeIs('timetable.*')" wire:navigate>
                            {{ __('Timetables') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Lesson') && (auth()->user()->can('view lesson') || auth()->user()->can('edit lesson') || auth()->user()->can('create lesson')))
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('lesson.index')"
                            :current="request()->routeIs('lesson.*')" wire:navigate>
                            {{ __('Lesson Attendance') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Examinations') && (auth()->user()->can('view examination') || auth()->user()->can('edit examination') || auth()->user()->can('create examination')))
                        <flux:sidebar.item icon="pencil-square" :href="route('examinations.index')"
                            :current="request()->routeIs('examinations.*')" wire:navigate>
                            {{ __('Examinations') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Result') && (auth()->user()->can('view result') || auth()->user()->can('edit result') || auth()->user()->can('create result')))
                        <flux:sidebar.item icon="chart-bar-square" :href="route('result.index')"
                            :current="request()->routeIs('result.*')" wire:navigate>
                            {{ __('Exam Results') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('ReportCard') && (auth()->user()->can('view reportcard') || auth()->user()->can('edit reportcard') || auth()->user()->can('create reportcard')))
                        <flux:sidebar.item icon="document-chart-bar" :href="route('reportcard.index')"
                            :current="request()->routeIs('reportcard.*')" wire:navigate>
                            {{ __('Report Cards') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                {{-- 3. People & Attendance Domain (Expandable) --}}
                <flux:sidebar.group icon="users" :heading="__('People & Community')" class="grid" expandable
                    :expanded="request()->routeIs('student.*') || request()->routeIs('teacher.*') || request()->routeIs('staff.*') || request()->routeIs('parent.*') || request()->routeIs('attendance.*')">
                    @if (institutionHasModule('Student') && (auth()->user()->can('view student') || auth()->user()->can('edit student') || auth()->user()->can('create student')))
                        <flux:sidebar.item icon="user-group" :href="route('student.index')"
                            :current="request()->routeIs('student.*')" wire:navigate>
                            {{ __('Students') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Teacher') && (auth()->user()->can('view teacher') || auth()->user()->can('edit teacher') || auth()->user()->can('create teacher')))
                        <flux:sidebar.item icon="academic-cap" :href="route('teacher.index')"
                            :current="request()->routeIs('teacher.*')" wire:navigate>
                            {{ __('Teachers & Faculty') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Staff') && (auth()->user()->can('view staff') || auth()->user()->can('edit staff') || auth()->user()->can('create staff')))
                        <flux:sidebar.item icon="identification" :href="route('staff.index')"
                            :current="request()->routeIs('staff.index') || request()->routeIs('staff.show') || request()->routeIs('staff.create') || request()->routeIs('staff.edit')" wire:navigate>
                            {{ __('Staff Directory') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Parent') && (auth()->user()->can('view parent') || auth()->user()->can('edit parent') || auth()->user()->can('create parent')))
                        <flux:sidebar.item icon="user" :href="route('parent.index')"
                            :current="request()->routeIs('parent.*')" wire:navigate>
                            {{ __('Parents & Guardians') }}
                        </flux:sidebar.item>
                    @endif

                    @if (institutionHasModule('Attendance') && (auth()->user()->can('view attendance') || auth()->user()->can('edit attendance') || auth()->user()->can('create attendance')))
                        <flux:sidebar.item icon="clock" :href="route('attendance.index')"
                            :current="request()->routeIs('attendance.*')" wire:navigate>
                            {{ __('Daily Attendance') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                {{-- 4. Financials & Infrastructure Domain (Expandable) --}}
                <flux:sidebar.group icon="banknotes" :heading="__('Finance & Systems')" class="grid" expandable
                    :expanded="request()->routeIs('feemanagement.*') || request()->routeIs('staff.payments.*') || request()->routeIs('devices.*') || request()->routeIs('sync-devices.*') || request()->routeIs('billing.*')">
                    @if (institutionHasModule('FeeManagement') && (auth()->user()->can('view feemanagement') || auth()->user()->can('edit feemanagement') || auth()->user()->can('create feemanagement')))
                        <flux:sidebar.item icon="banknotes" :href="route('feemanagement.index')"
                            :current="request()->routeIs('feemanagement.*')" wire:navigate>
                            {{ __('Fee Collections') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->can('view payroll') && institutionHasModule('Staff'))
                        <flux:sidebar.item icon="credit-card" :href="route('staff.payments.index')"
                            :current="request()->routeIs('staff.payments.*')" wire:navigate>
                            {{ __('Staff Payroll') }}
                        </flux:sidebar.item>
                    @endif

                    @if (isDirector())
                        <flux:sidebar.item icon="device-phone-mobile" :href="route('devices.index')"
                            :current="request()->routeIs('devices.*')" wire:navigate>
                            {{ __('Biometric Devices') }}
                        </flux:sidebar.item>
                        @can('view syncdevice')
                            <flux:sidebar.item icon="signal" :href="route('sync-devices.index')"
                                :current="request()->routeIs('sync-devices.*')" wire:navigate>
                                {{ __('Sync Devices') }}
                            </flux:sidebar.item>
                        @endcan
                        @if (institutionCanUpgrade())
                            <flux:sidebar.item icon="arrow-trending-up" :href="route('billing.show')"
                                :current="request()->routeIs('billing.*')" wire:navigate>
                                {{ currentInstitution()->hasActiveSubscription() ? __('Upgrade Plan') : __('Choose Plan') }}
                            </flux:sidebar.item>
                        @endif
                    @endif
                </flux:sidebar.group>
            @endif
        </flux:sidebar.nav>

        <flux:spacer />



        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
    @stack('scripts')
</body>

</html>
