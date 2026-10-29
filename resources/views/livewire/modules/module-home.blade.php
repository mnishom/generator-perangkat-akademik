<div class="mx-auto max-w-6xl space-y-6">
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-4 bg-gradient-to-r from-marun-700 to-marun-900 px-6 py-6 text-krem-100">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-krem-200 text-marun-800"><x-icon :name="$moduleDef->icon()" class="h-7 w-7" /></div>
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-semibold text-white">{{ $moduleDef->name() }}</h2>
                <p class="text-sm">{{ $moduleDef->description() }}</p>
            </div>
            @if ($generateOpen && $canGenerate)
                <button wire:click="start" class="btn bg-krem-200 text-marun-800 hover:bg-white">+ Sesi generate baru</button>
            @endif
        </div>
        <div class="flex flex-wrap gap-2 px-6 py-3 text-xs">
            <span class="text-zinc-500">Menghasilkan:</span>
            @foreach ($moduleDef->documentTypes() as $type)
                <span class="badge bg-krem-100 text-marun-800 dark:bg-marun-950 dark:text-krem-200">{{ $type->label }} ({{ strtoupper($type->format) }})</span>
            @endforeach
        </div>
    </div>

    @unless ($generateOpen)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Fitur generate untuk modul ini sedang ditutup sementara oleh admin. Riwayat tetap dapat dibuka dan diunduh.</div>
    @endunless

    <div class="card">
        <div class="border-b border-zinc-100 p-4 dark:border-zinc-800"><h3 class="font-semibold">Sesi terakhir Anda</h3></div>
        <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
            @forelse ($sessions as $s)
                <li class="flex flex-wrap items-center gap-3 p-4" wire:key="s-{{ $s->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $s->title ?: 'Sesi #'.$s->id }}</p>
                        <p class="text-xs text-zinc-500">{{ $s->created_at->translatedFormat('d M Y H:i') }} · {{ $s->tahun_akademik ?: '—' }} · {{ $s->items_count }} butir</p>
                    </div>
                    <x-session-status :status="$s->status" />
                    @if ($s->status === \App\Models\GenerationSession::STATUS_DRAFT)
                        <a href="{{ route('modules.wizard', [$moduleDef->key(), $s]) }}" class="btn-secondary px-3 py-1.5" wire:navigate>Lanjutkan</a>
                    @else
                        <a href="{{ route('sessions.show', $s) }}" class="btn-secondary px-3 py-1.5" wire:navigate>Rincian</a>
                    @endif
                </li>
            @empty
                <li class="p-6 text-center text-zinc-500">Belum ada sesi. Mulai dengan menekan "Sesi generate baru".</li>
            @endforelse
        </ul>
    </div>
</div>
