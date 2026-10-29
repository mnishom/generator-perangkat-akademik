<div class="space-y-4">
    <div class="card space-y-4 p-6">
        <h3 class="text-lg font-semibold">Generate massal</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50"><dt class="text-zinc-500">MK yang diproses</dt><dd class="text-2xl font-semibold">{{ $hasRps ? $pending->count() : 0 }}</dd>
                <dd class="text-xs text-zinc-500">{{ $hasRps ? 'RPS'.($hasKontrak ? ' → Kontrak Perkuliahan' : '').' per MK' : 'Tanpa RPS' }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50"><dt class="text-zinc-500">Silabus Prodi</dt><dd class="text-2xl font-semibold">{{ $silabus ? 'Ya' : 'Tidak' }}</dd>
                <dd class="text-xs text-zinc-500">{{ $silabus ? $allCount.' MK, bertahap per semester' : '' }}</dd></div>
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/50"><dt class="text-zinc-500">Model</dt><dd class="font-mono text-sm font-semibold">{{ $model }}</dd>
                <dd class="text-xs text-zinc-500">maks. {{ $concurrency }} proses bersamaan</dd></div>
        </dl>
        @if ($skipped->isNotEmpty())
            <p class="text-sm text-zinc-500">Tidak di-generate ulang (sudah selesai di uji coba): {{ $skipped->pluck('ref_code')->implode(', ') }}.</p>
        @endif
        <p class="text-sm text-zinc-500">Setiap MK diproses sebagai satu Job: RPS disusun lebih dulu, lalu Kontrak Perkuliahan diturunkan dari RPS tersebut di container yang sama. Dokumen yang gagal pemeriksaan diberi status <em>Perlu Perhatian</em> dan dapat diperbaiki otomatis.</p>
    </div>

    <div class="flex justify-between">
        <button wire:click="back" class="btn-secondary">&larr; Kembali</button>
        <button wire:click="start" wire:loading.attr="disabled" class="btn-primary" @disabled(($hasRps ? $pending->count() : 0) === 0 && ! $silabus)>🚀 Mulai generate</button>
    </div>
</div>
