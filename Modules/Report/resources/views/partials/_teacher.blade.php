{{-- Teacher Analytics Partial --}}
<div>
    @if (!$stats['institution'])
        <flux:callout variant="warning" icon="information-circle">
            <flux:callout.heading>Not yet assigned to a school</flux:callout.heading>
            <flux:callout.text>Ask your Director to add you to a campus roster.</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi-card title="School" icon="building-office-2" color="indigo" :count="$stats['institution']->name" subtext="currently assigned" />
            <x-kpi-card title="Students" icon="user-group" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}" subtext="in your assigned classes" />
            <x-kpi-card title="Attendance Today" icon="clock" color="emerald" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="biometric check-ins" />
        </div>
    @endif
</div>
