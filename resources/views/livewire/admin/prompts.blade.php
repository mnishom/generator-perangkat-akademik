<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <div class="card flex flex-wrap gap-3 p-4">
            <div><label class="label">Modul</label><select wire:model.live="module" class="input">@foreach ($modules as $k => $m)<option value="{{ $k }}">{{ $m->name() }}</option>@endforeach</select></div>
            <div><label class="label">Prompt</label><select wire:model.live="key" class="input">@foreach ($prompts as $p)<option value="{{ $p }}">{{ $defs[$p]['label'] ?? $p }}</option>@endforeach</select></div>
        </div>
        <form wire:submit="save" class="card space-y-3 p-6">
            @if ($analysis['missing'])
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">⚠ Variabel penting hilang: @foreach ($analysis['missing'] as $v)<x-var :name="$v" />@endforeach</div>
            @endif
            <textarea wire:model.live.debounce.600ms="body" rows="18" class="input font-mono text-xs"></textarea>
            @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1"><label class="label">Catatan versi (opsional)</label><input wire:model="note" class="input"></div>
                <button class="btn-primary">Simpan sebagai versi baru</button>
            </div>
            <p class="text-xs text-zinc-500">Perubahan berlaku untuk sesi baru. Sesi yang sudah berjalan memakai prompt yang tersimpan di sesinya. Instruksi sistem tetap tidak dapat diubah di sini.</p>
        </form>
    </div>
    <div class="card self-start">
        <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800"><h3 class="font-semibold">Riwayat versi</h3></div>
        <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
            @forelse ($history as $h)
                <li class="flex items-center gap-2 px-4 py-2" wire:key="v-{{ $h->id }}">
                    <div class="min-w-0 flex-1"><p class="font-medium">Versi {{ $h->version }} @if ($h->is_active)<span class="badge bg-emerald-100 text-emerald-800">aktif</span>@endif</p>
                        <p class="text-xs text-zinc-500">{{ $h->created_at->translatedFormat('d M Y H:i') }} · {{ $h->creator?->name ?? 'bawaan' }}{{ $h->note ? ' · '.$h->note : '' }}</p></div>
                    @unless ($h->is_active)<button wire:click="activate({{ $h->version }})" wire:confirm="Aktifkan kembali versi {{ $h->version }}?" class="text-xs text-marun-700 hover:underline">Kembalikan</button>@endunless
                </li>
            @empty
                <li class="px-4 py-4 text-zinc-500">Masih memakai prompt bawaan aplikasi.</li>
            @endforelse
        </ul>
    </div>
</div>
