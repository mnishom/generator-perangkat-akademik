<div class="mx-auto max-w-6xl space-y-6">
    <div class="card flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-48 flex-1">
            <label class="label">Cari</label>
            <input type="search" wire:model.live.debounce.400ms="q" class="input" placeholder="Nama berkas, kode, atau nama MK">
        </div>
        <div>
            <label class="label">Modul</label>
            <select wire:model.live="module" class="input">
                <option value="">Semua modul</option>
                @foreach ($modules as $key => $m)<option value="{{ $key }}">{{ $m->name() }}</option>@endforeach
            </select>
        </div>
    </div>
    <p class="text-sm text-zinc-500">Berisi dokumen hasil yang sudah Anda tandai <strong>final</strong> (milik Anda atau prodi Anda). Dokumen ini dapat dipakai ulang sebagai masukan modul lain.</p>

    @forelse ($grouped as $moduleKey => $types)
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">{{ $modules[$moduleKey]?->name() ?? $moduleKey }}</h2>
            @foreach ($types as $type => $docs)
                <div class="card">
                    <div class="border-b border-zinc-100 px-4 py-3 text-sm font-semibold dark:border-zinc-800">{{ $typeLabel($moduleKey, $type) }} <span class="font-normal text-zinc-500">({{ $docs->count() }})</span></div>
                    <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                        @foreach ($docs as $d)
                            <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="d-{{ $d->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ $d->filename }}</p>
                                    <p class="text-xs text-zinc-500">{{ $d->ref_code }} {{ $d->item?->ref_label }} · v{{ $d->version }} · {{ $d->updated_at->translatedFormat('d M Y') }} · {{ $d->user->name }}</p>
                                </div>
                                <x-session-status :status="$d->status" />
                                <a href="{{ route('sessions.show', $d->generation_session_id) }}" class="text-zinc-600 hover:underline dark:text-zinc-300" wire:navigate>Sesi</a>
                                <a href="{{ route('documents.download', $d) }}" class="text-marun-700 hover:underline dark:text-krem-200">Unduh</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </section>
    @empty
        <div class="card p-8 text-center text-sm text-zinc-500">Belum ada dokumen final. Tandai dokumen sebagai final dari halaman rincian sesi.</div>
    @endforelse
</div>
