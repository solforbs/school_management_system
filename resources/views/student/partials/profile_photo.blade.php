{{-- Profile Photo --}}
@php
    $currentPhoto = $student->studentUserDetails?->profile_photo;
    
@endphp
<div class="flex justify-center mb-6">
    <div class="relative">
        @if ($currentPhoto)
            <img src="{{ Storage::disk('public')->url($currentPhoto) }}" alt="{{ $student->name }}" translate="no"
                class="h-32 w-32 rounded-full object-cover border-4 border-gray-200">
        @else
            <div class="h-32 w-32 rounded-full bg-gray-100 border-4 border-gray-200 flex items-center justify-center">
                <span class="text-3xl font-semibold text-gray-400"><span translate="no">{{ strtoupper(substr($student->name, 0, 1)) }}</span></span>
            </div>
        @endif
        <label for="profile_image"
            class="absolute bottom-0 right-0 bg-blue-600 rounded-full p-2 cursor-pointer hover:bg-blue-700">
            <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
        </label>
        {{-- Deliberately a plain native input, not <flux:input type="file">: Flux's
        file variant renders its own "Choose file" button widget and treats
        class="hidden" as hiding that whole widget (including the real
        underlying input), which breaks triggering it via this external
        pencil-icon label. A plain input composes correctly with a custom
        label[for] trigger. --}}
        <input type="file" name="profile_image" id="profile_image" accept="image/*" class="hidden">
        <flux:error name="profile_image" class="mt-1 text-sm text-red-600" />
    </div>
</div>
