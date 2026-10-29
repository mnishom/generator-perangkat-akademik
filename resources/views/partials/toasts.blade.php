{{-- Toast notifikasi: dipicu oleh flash session atau event `toast` dari Livewire ($this->dispatch('toast', type: 'success', message: '...')). --}}
<div
    x-data="{
        items: [],
        push(detail) {
            const d = Array.isArray(detail) ? detail[0] : detail;
            const id = Date.now() + Math.random();
            this.items.push({ id, type: d.type ?? 'info', message: d.message });
            setTimeout(() => this.items = this.items.filter(i => i.id !== id), d.timeout ?? 5000);
        }
    }"
    x-init="
        @foreach (['success', 'error', 'warning', 'info'] as $type)
            @if (session($type)) push({ type: '{{ $type }}', message: @js(session($type)) }); @endif
        @endforeach
    "
    @toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:right-4 sm:left-auto"
    aria-live="polite"
>
    <template x-for="item in items" :key="item.id">
        <div
            x-transition.opacity
            class="pointer-events-auto w-full max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg"
            :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100': item.type === 'success',
                'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100': item.type === 'error',
                'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100': item.type === 'warning',
                'border-zinc-200 bg-white text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100': item.type === 'info',
            }"
        >
            <div class="flex items-start gap-3">
                <p class="flex-1" x-text="item.message"></p>
                <button type="button" class="opacity-60 hover:opacity-100" @click="items = items.filter(i => i.id !== item.id)" aria-label="Tutup">&times;</button>
            </div>
        </div>
    </template>
</div>
