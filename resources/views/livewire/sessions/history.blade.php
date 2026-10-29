<div class="mx-auto max-w-6xl space-y-4">
    <div class="card grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-6">
        <div><label class="label">Modul</label><select wire:model.live="module" class="input"><option value="">Semua</option>@foreach ($modules as $k => $m)<option value="{{ $k }}">{{ $m->name() }}</option>@endforeach</select></div>
        <div><label class="label">Prodi</label><select wire:model.live="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->id }}">{{ $p->namaLengkap() }}</option>@endforeach</select></div>
        <div><label class="label">Tahun akademik</label><select wire:model.live="tahun" class="input"><option value="">Semua</option>@foreach ($years as $y)<option>{{ $y }}</option>@endforeach</select></div>
        <div><label class="label">Status</label><select wire:model.live="status" class="input"><option value="">Semua</option>
            @foreach (['draft' => 'Draf', 'queued' => 'Antre', 'running' => 'Berjalan', 'done' => 'Selesai', 'attention' => 'Perlu Perhatian', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select></div>
        <div><label class="label">Dari tanggal</label><input type="date" wire:model.live="from" class="input"></div>
        <div><label class="label">Sampai</label><input type="date" wire:model.live="to" class="input"></div>
        @if ($isAdmin)
            <label class="flex items-center gap-2 text-sm sm:col-span-3 lg:col-span-6"><input type="checkbox" wire:model.live="all" class="rounded text-marun-700"> Tampilkan riwayat seluruh pengguna (hanya baca; tercatat di audit log)</label>
        @endif
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                <tr><th class="px-4 py-3">Sesi</th>@if ($showAll)<th class="px-4 py-3">Pengguna</th>@endif<th class="px-4 py-3">Modul / Prodi</th><th class="px-4 py-3">TA</th><th class="px-4 py-3">Dokumen</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($sessions as $s)
                    <tr wire:key="s-{{ $s->id }}">
                        <td class="px-4 py-3"><p class="font-medium">{{ $s->title ?: 'Sesi #'.$s->id }}</p><p class="text-xs text-zinc-500">{{ $s->created_at->translatedFormat('d M Y H:i') }}</p></td>
                        @if ($showAll)<td class="px-4 py-3">{{ $s->user->name }}</td>@endif
                        <td class="px-4 py-3"><p>{{ ($modules[$s->module_key] ?? null)?->name() ?? $s->module_key }}</p><p class="text-xs text-zinc-500">{{ $s->prodi?->namaLengkap() }}</p></td>
                        <td class="px-4 py-3">{{ $s->tahun_akademik ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $s->documents_count }} @if ($s->attention_count)<span class="text-xs text-amber-700">({{ $s->attention_count }} perlu perhatian)</span>@endif<p class="text-xs text-zinc-500">{{ $s->selected_count }} MK dipilih</p></td>
                        <td class="px-4 py-3"><x-session-status :status="$s->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($s->status === 'draft' && $s->user_id === auth()->id())
                                <a href="{{ route('modules.wizard', [$s->module_key, $s]) }}" class="btn-secondary px-3 py-1.5" wire:navigate>Lanjutkan</a>
                            @else
                                <a href="{{ route('sessions.show', $s) }}" class="btn-secondary px-3 py-1.5" wire:navigate>Rincian</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-zinc-500">Belum ada riwayat.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $sessions->links() }}</div>
    </div>
</div>
