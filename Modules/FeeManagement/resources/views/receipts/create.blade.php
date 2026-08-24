<x-layouts::app :title="__('Scan Receipt')">
    <div class="flex flex-col gap-6 w-full max-w-3xl mx-auto p-4 sm:p-6 lg:p-8" x-data="receiptScanner()">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-6 mb-2">
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex size-12 shrink-0 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/10 ring-1 ring-purple-500/20">
                    <flux:icon icon="sparkles" class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">AI Receipt Scanner</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-1 leading-relaxed">
                        Upload an M-Pesa message, bank slip, or receipt. Our OCR will extract the data instantly.
                    </flux:text>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('feemanagement.index') }}" variant="subtle" icon="arrow-left" class="hidden sm:flex">
                    Back to Fees
                </flux:button>
            </div>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200/50 bg-red-50/50 dark:bg-red-500/10 dark:border-red-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="exclamation-circle" class="size-5 text-red-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-red-800 dark:text-red-400">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-zinc-900 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-3xl overflow-hidden relative group transition-all duration-500">
            
            {{-- Decorative background element --}}
            <div class="absolute top-0 right-0 p-8 opacity-5 transition-opacity duration-1000 pointer-events-none" :class="isScanning ? 'animate-pulse opacity-20 text-purple-500' : 'group-hover:opacity-10'">
                <flux:icon icon="document-magnifying-glass" class="size-48" />
            </div>

            <form action="{{ route('feemanagement.receipts.extract') }}" method="POST" enctype="multipart/form-data" class="relative z-10" id="scanForm" @submit="startScan">
                @csrf
                <div class="p-6 sm:p-10">
                    
                    {{-- File Drop Zone --}}
                    <div class="relative w-full rounded-2xl border-2 border-dashed transition-all duration-300 flex flex-col items-center justify-center p-8 sm:p-12 text-center"
                         :class="{
                             'border-purple-400 dark:border-purple-500/50 bg-purple-50/50 dark:bg-purple-500/5': isDragging,
                             'border-zinc-300 dark:border-zinc-700 hover:border-purple-400 dark:hover:border-purple-500/50 hover:bg-zinc-50 dark:hover:bg-zinc-800/50': !isDragging && !filePreview,
                             'border-transparent bg-zinc-100 dark:bg-zinc-900/50 p-2': filePreview
                         }"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleDrop($event)"
                         @click="!isScanning && $refs.fileInput.click()">
                        
                        <input type="file" name="receipt" x-ref="fileInput" class="hidden" accept="image/*,application/pdf" @change="handleFileSelect" required :disabled="isScanning">
                        
                        {{-- Empty State --}}
                        <div x-show="!filePreview" class="flex flex-col items-center justify-center space-y-4 cursor-pointer" x-transition>
                            <div class="size-16 rounded-full bg-purple-100 dark:bg-purple-500/20 flex items-center justify-center transition-transform group-hover:scale-110 duration-300">
                                <flux:icon icon="cloud-arrow-up" class="size-8 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Drag and drop your receipt</h3>
                                <p class="text-sm text-zinc-500 mt-1">or click to browse from your device</p>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-zinc-400 mt-4">
                                <flux:icon icon="photo" class="size-4" /> JPEG, PNG, or PDF up to 10MB
                            </div>
                        </div>

                        {{-- Preview State --}}
                        <div x-show="filePreview" style="display: none;" class="w-full relative rounded-xl overflow-hidden shadow-inner group/preview" x-transition>
                            
                            {{-- Image Preview --}}
                            <img :src="filePreview" x-show="isImage" class="w-full max-h-[500px] object-contain bg-zinc-200/50 dark:bg-zinc-800 rounded-xl" />
                            
                            {{-- PDF Preview Placeholder --}}
                            <div x-show="!isImage" class="w-full h-64 bg-zinc-200/50 dark:bg-zinc-800 flex flex-col items-center justify-center rounded-xl">
                                <flux:icon icon="document-text" class="size-16 text-zinc-400" />
                                <p class="text-zinc-600 dark:text-zinc-300 font-medium mt-3" x-text="fileName"></p>
                            </div>

                            {{-- The Scanning Laser Animation --}}
                            <div x-show="isScanning" x-transition.opacity class="absolute inset-0 pointer-events-none rounded-xl overflow-hidden z-20">
                                <div class="w-full h-[3px] bg-purple-500 shadow-[0_0_15px_3px_rgba(168,85,247,0.8)] absolute top-0 animate-scan"></div>
                                <div class="absolute inset-0 bg-purple-500/10 animate-pulse"></div>
                            </div>

                            {{-- Clear Button --}}
                            <button type="button" @click.stop="clearFile" x-show="!isScanning" class="absolute top-4 right-4 size-8 bg-zinc-900/50 hover:bg-red-500/90 text-white rounded-full flex items-center justify-center backdrop-blur-sm transition-colors opacity-0 group-hover/preview:opacity-100">
                                <flux:icon icon="x-mark" class="size-5" />
                            </button>
                        </div>

                    </div>
                    @error('receipt')
                        <p class="mt-3 text-sm font-medium text-red-600 dark:text-red-400 text-center">{{ $message }}</p>
                    @enderror

                    {{-- Scanning Status Text --}}
                    <div x-show="isScanning" style="display: none;" class="mt-8 text-center" x-transition>
                        <div class="inline-flex items-center justify-center gap-3 px-4 py-2 bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-400 rounded-full font-medium text-sm border border-purple-200 dark:border-purple-500/20">
                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                            <span x-text="scanStatus">Initializing Neural Engine...</span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-zinc-200 dark:border-zinc-800 px-6 sm:px-10 py-5 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col-reverse sm:flex-row justify-end gap-3 items-center">
                    <flux:button href="{{ route('feemanagement.index') }}" variant="subtle" class="w-full sm:w-auto hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors" x-bind:disabled="isScanning">
                        Cancel
                    </flux:button>
                    <flux:button type="submit" icon="cpu-chip" variant="primary" class="w-full sm:w-auto px-8" x-bind:disabled="isScanning || !filePreview">
                        <span x-text="isScanning ? 'Processing...' : 'Analyze Receipt'"></span>
                    </flux:button>
                </div>
            </form>
        </div>
    </div>

    <style>
        @keyframes scan {
            0% { top: 0%; box-shadow: 0 0 15px 3px rgba(168,85,247,0.8), 0 50px 50px -20px rgba(168,85,247,0.2) inset; }
            50% { top: 100%; box-shadow: 0 0 15px 3px rgba(168,85,247,0.8), 0 -50px 50px -20px rgba(168,85,247,0.2) inset; }
            100% { top: 0%; box-shadow: 0 0 15px 3px rgba(168,85,247,0.8), 0 50px 50px -20px rgba(168,85,247,0.2) inset; }
        }
        .animate-scan {
            animation: scan 3s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('receiptScanner', () => ({
                isDragging: false,
                isScanning: false,
                filePreview: null,
                isImage: false,
                fileName: '',
                scanStatus: 'Initializing Neural Engine...',
                statusMessages: [
                    'Scanning financial data...',
                    'Extracting transaction amounts...',
                    'Isolating reference numbers...',
                    'Cross-referencing admission database...',
                    'Finalizing OCR results...'
                ],
                
                handleDrop(e) {
                    this.isDragging = false;
                    if (this.isScanning) return;
                    
                    const dt = e.dataTransfer;
                    if (dt.files && dt.files.length) {
                        this.$refs.fileInput.files = dt.files;
                        this.processFile(dt.files[0]);
                    }
                },
                
                handleFileSelect(e) {
                    if (e.target.files && e.target.files.length) {
                        this.processFile(e.target.files[0]);
                    }
                },
                
                processFile(file) {
                    this.fileName = file.name;
                    this.isImage = file.type.startsWith('image/');
                    
                    if (this.isImage) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.filePreview = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        // For PDF or other files
                        this.filePreview = 'pdf_placeholder';
                    }
                },
                
                clearFile() {
                    this.filePreview = null;
                    this.isImage = false;
                    this.fileName = '';
                    this.$refs.fileInput.value = '';
                },
                
                startScan(e) {
                    if (!this.filePreview) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Allow the form to submit naturally to the server, but trigger our scanning UI!
                    this.isScanning = true;
                    
                    // Cycle through fake status messages while the server processes the file in the background
                    let msgIndex = 0;
                    setInterval(() => {
                        this.scanStatus = this.statusMessages[msgIndex];
                        msgIndex = (msgIndex + 1) % this.statusMessages.length;
                    }, 1200);
                }
            }))
        });
    </script>
</x-layouts::app>
