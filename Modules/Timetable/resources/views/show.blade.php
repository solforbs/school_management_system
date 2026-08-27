<x-layouts::app :title="__('Timetable Entry')">
    <div class="p-4">

        @if (session('success'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="border-b border-gray-200 px-6 py-4 bg-gray-50 rounded-t-lg">
                <h4 class="text-lg font-semibold text-gray-900 mb-0">
                    {{ $entry->schoolClass?->name ?? $entry->class_name }} &mdash; {{ $entry->subject }}
                </h4>
                <small class="text-sm text-gray-500">{{ $entry->institution?->name }}</small>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Day</p>
                    <p class="text-sm text-gray-900">{{ $entry->day_of_week }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Time</p>
                    <p class="text-sm text-gray-900">
                        {{ $entry->start_time?->format('H:i') }} &ndash; {{ $entry->end_time?->format('H:i') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Room</p>
                    <p class="text-sm text-gray-900">{{ $entry->room ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Teacher</p>
                    <p class="text-sm text-gray-900">{{ $entry->teacher?->name ?? 'Unassigned' }}</p>
                </div>
                @if ($entry->notes)
                    <div class="md:col-span-3">
                        <p class="text-xs text-gray-500 uppercase">Notes</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $entry->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
                <a href="{{ route('timetable.index') }}"
                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" wire:navigate>
                    Back to List
                </a>
                @can('edit timetable')
                    <a href="{{ route('timetable.edit', $entry->id) }}"
                        class="px-4 py-2 bg-yellow-500 border border-transparent rounded-md text-sm font-medium text-white hover:bg-yellow-600" wire:navigate>
                        Edit
                    </a>
                @endcan
                @can('delete timetable')
                    <form action="{{ route('timetable.destroy', $entry->id) }}" method="POST"
                        onsubmit="return confirm('Remove this timetable entry?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-layouts::app>
