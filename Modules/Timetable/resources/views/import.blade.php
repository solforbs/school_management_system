<x-layouts::app :title="__('Import Timetable')">
    <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Import
                    Timetable</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Upload a CSV or Excel file to bulk import a weekly schedule for a single class.
                    <br>Existing slots with recorded student attendance are strictly preserved and updated in-place.
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                <flux:button href="{{ route('timetable.index') }}" icon="arrow-left" variant="subtle"
                    class="font-semibold shadow-sm">
                    Back to Timetables
                </flux:button>
            </div>
        </div>

        @if (session('error'))
            <div
                class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-red-800 dark:text-red-400">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm mb-6">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm text-red-800 dark:text-red-400">
                    <p class="font-semibold mb-1">{{ $errors->count() }} error(s) prevented the import:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <form action="{{ route('timetable.import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="p-6 md:p-8 space-y-8">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select name="class_id" label="Target Class" required>
                            <flux:select.option value="">Select a class...</flux:select.option>
                            @foreach ($classes as $schoolClass)
                                <flux:select.option value="{{ $schoolClass->id }}"
                                    :selected="old('class_id') == $schoolClass->id">
                                    <span translate="no">{{ $schoolClass->name }}</span>
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input type="file" name="file" label="CSV / XLS / XLSX File"
                            accept=".csv,.txt,.xls,.xlsx" required />
                    </div>

                    <div
                        class="rounded-xl border border-blue-200/50 bg-blue-50/50 dark:bg-blue-900/10 dark:border-blue-800/30 p-5 shadow-sm">
                        <div class="flex items-start gap-3">
                            <flux:icon icon="information-circle" class="size-5 text-blue-500 mt-0.5 shrink-0" />
                            <div class="text-sm text-blue-800 dark:text-blue-300">
                                <p class="font-semibold mb-2 text-blue-900 dark:text-blue-200">Formatting Instructions:
                                </p>
                                <ul class="list-disc list-inside space-y-1.5 mb-4">
                                    <li><strong>Expected Columns:</strong> Day, Start Time, End Time, Subject, Teacher (Name or Email), Room (optional), Notes (optional).</li>
                                    <li>A header row is required. Column names are flexible and case-insensitive.</li>
                                    <li><strong>Double Lessons:</strong> Mark consecutive periods with <code>[Double Lesson Part 1/2]</code> and <code>[Double Lesson Part 2/2]</code> in the <em>Notes</em> column.</li>
                                    <li>Rest breaks (e.g. "TEA BREAK", "LUNCH", "BREAK") will be skipped automatically.</li>
                                </ul>
                                <a href="{{ route('timetable.import.template') }}"
                                    class="inline-flex items-center gap-1.5 font-semibold hover:text-blue-600 transition-colors bg-white dark:bg-zinc-900 px-3 py-1.5 rounded-md border border-blue-200/50 dark:border-blue-800/30 shadow-sm">
                                    <flux:icon icon="arrow-down-tray" class="size-4" />
                                    Download Blank CSV Template
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                <div
                    class="border-t border-zinc-200 dark:border-zinc-800 px-6 py-4 bg-zinc-50/50 dark:bg-zinc-800/20 flex justify-end gap-3">
                    <flux:button href="{{ route('timetable.index') }}" variant="subtle" class="font-semibold shadow-sm">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray"
                        class="font-semibold shadow-sm px-6">
                        Upload & Merge
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
