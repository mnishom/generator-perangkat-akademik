@props(['model', 'accept' => '.docx,.pdf', 'hint' => null])
{{-- Area unggah drag & drop dengan indikator progres (Livewire upload). --}}
<div
    x-data="{ over: false, progress: 0, uploading: false,
        send(file) {
            if (!file) return;
            this.uploading = true; this.progress = 0;
            $wire.upload('{{ $model }}', file,
                () => { this.uploading = false },
                () => { this.uploading = false; window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Unggahan gagal.' } })) },
                (e) => { this.progress = e.detail.progress });
        } }"
    @dragover.prevent="over = true" @dragleave.prevent="over = false"
    @drop.prevent="over = false; send($event.dataTransfer.files[0])"
    :class="over ? 'border-marun-500 bg-marun-50 dark:bg-marun-950/40' : 'border-zinc-300 dark:border-zinc-700'"
    {{ $attributes->class('relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 text-center transition') }}
>
    <svg class="h-8 w-8 text-marun-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
    <p class="mt-2 text-sm"><label class="cursor-pointer font-medium text-marun-700 hover:underline dark:text-krem-200">Pilih berkas<input type="file" class="sr-only" accept="{{ $accept }}" @change="send($event.target.files[0]); $event.target.value = ''"></label> atau seret ke sini</p>
    <p class="mt-1 text-xs text-zinc-500">{{ $hint ?? strtoupper(str_replace(['.', ','], ['', ', '], $accept)) }}</p>
    <div x-show="uploading" x-cloak class="mt-3 h-2 w-full max-w-xs overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div class="h-full bg-marun-600 transition-all" :style="`width: ${progress}%`"></div>
    </div>
</div>
