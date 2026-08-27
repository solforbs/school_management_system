<x-layouts::app :title="__('Edit Lesson')">
    <div class="p-4">

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="border-b border-gray-200 px-6 py-4 bg-gray-50 rounded-t-lg">
                <h4 class="text-lg font-semibold text-gray-900 mb-0">Edit Lesson Attendance</h4>
                <small class="text-sm text-gray-500">
                    {{ $lesson->timetableEntry?->subject }} &middot; {{ $lesson->lesson_date->format('l, d M Y') }}
                </small>
            </div>

            @if ($errors->any())
                <div class="mx-6 mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('lesson.update', $lesson->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select name="status" label="Status">
                        <flux:select.option value="attended" :selected="old('status', $lesson->status) === 'attended'">
                            Attended</flux:select.option>
                        <flux:select.option value="not_attended"
                            :selected="old('status', $lesson->status) === 'not_attended'">
                            Not Attended</flux:select.option>
                        <flux:select.option value="recovered"
                            :selected="old('status', $lesson->status) === 'recovered'">
                            Recovered</flux:select.option>
                    </flux:select>

                    <div class="md:col-span-2">
                        <flux:textarea name="remarks" rows="3" label="Remarks">{{ old('remarks', $lesson->remarks) }}</flux:textarea>
                    </div>
                </div>

                <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
                    <a href="{{ route('lesson.show', $lesson->id) }}"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50" wire:navigate>
                        Cancel
                    </a>
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
