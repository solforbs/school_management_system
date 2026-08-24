            @if (!$stats['institution'])
                <flux:callout variant="warning" icon="information-circle">
                    <flux:callout.heading>Not yet assigned to a school</flux:callout.heading>
                    <flux:callout.text>Ask your Director to add you as a teacher.</flux:callout.text>
                </flux:callout>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {{-- School --}}
                    <x-kpi-card title="School" icon="building-office-2" color="indigo" :count="$stats['institution']->name" subtext="currently assigned" />

                    {{-- Students --}}
                    <x-kpi-card title="Students" icon="user-group" color="sky" :count="$stats['students_count']" href="{{ route('student.index') }}" subtext="in your classes" />

                    {{-- Attendance --}}
                    <x-kpi-card title="Attendance Today" icon="clock" color="violet" :count="$stats['attendance_today_count']" href="{{ route('attendance.index') }}" subtext="check-ins" />
                </div>
            @endif
