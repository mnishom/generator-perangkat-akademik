<div class="space-y-6" wire:poll.30s>
    <div class="card flex flex-wrap items-end gap-3 p-4">
        <div><label class="label">Dari</label><input type="date" wire:model.live="from" class="input"></div>
        <div><label class="label">Sampai</label><input type="date" wire:model.live="to" class="input"></div>
        <div><label class="label">Kelompokkan per</label>
            <select wire:model.live="group" class="input"><option value="module">Modul</option><option value="user">Pengguna</option><option value="prodi">Prodi</option><option value="day">Hari</option></select>
        </div>
        <p class="ml-auto text-xs text-zinc-500">Biaya adalah estimasi dari harga per juta token di config/claude.php.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-5"><p class="text-xs text-zinc-500">Generate</p><p class="text-3xl font-semibold">{{ number_format($totals->generates ?? 0, 0, ',', '.') }}</p><p class="text-xs text-zinc-500">+ {{ number_format($totals->extracts ?? 0, 0, ',', '.') }} ekstraksi</p></div>
        <div class="card p-5"><p class="text-xs text-zinc-500">Token input</p><p class="text-3xl font-semibold">{{ number_format($totals->input_tokens ?? 0, 0, ',', '.') }}</p></div>
        <div class="card p-5"><p class="text-xs text-zinc-500">Token output</p><p class="text-3xl font-semibold">{{ number_format($totals->output_tokens ?? 0, 0, ',', '.') }}</p></div>
        <div class="card p-5"><p class="text-xs text-zinc-500">Estimasi biaya</p><p class="text-3xl font-semibold text-marun-700 dark:text-krem-200">${{ number_format($totals->cost ?? 0, 2) }}</p></div>
    </div>

    <div class="card overflow-x-auto">
        @php($max = max(1, (float) $rows->max('cost')))
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                <tr><th class="px-4 py-3">{{ ['module' => 'Modul', 'user' => 'Pengguna', 'prodi' => 'Prodi', 'day' => 'Tanggal'][$group] }}</th><th class="px-4 py-3 text-right">Generate</th><th class="px-4 py-3 text-right">Token input</th><th class="px-4 py-3 text-right">Token output</th><th class="px-4 py-3">Estimasi biaya</th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-4 py-2">{{ $r->label ?: '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($r->generates, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($r->input_tokens, 0, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format($r->output_tokens, 0, ',', '.') }}</td>
                        <td class="px-4 py-2"><div class="flex items-center gap-2"><div class="h-2 rounded-full bg-marun-600" style="width: {{ max(2, round($r->cost / $max * 160)) }}px"></div><span class="tabular-nums">${{ number_format($r->cost, 2) }}</span></div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">Belum ada pemakaian pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="flex items-center gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <h3 class="flex-1 font-semibold">Antrean ({{ $queued->count() }}) <span class="text-xs font-normal text-zinc-500">· {{ $pendingJobs }} job di tabel jobs</span></h3>
                @if ($queued->whereIn('status', ['queued', 'waiting'])->isNotEmpty())
                    <button wire:click="cancelAllQueued" wire:confirm="Batalkan semua Job yang masih antre?" class="text-sm text-red-600 hover:underline">Batalkan semua antre</button>
                @endif
            </div>
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @forelse ($queued as $t)
                    <li class="flex items-center gap-2 px-4 py-2" wire:key="q-{{ $t->id }}">
                        <div class="min-w-0 flex-1"><p class="truncate">{{ $t->label }}</p><p class="text-xs text-zinc-500">{{ $t->session->user->name }} · sesi #{{ $t->generation_session_id }} · {{ $t->progressLabel() }}</p></div>
                        <x-session-status :status="$t->status" />
                        @if ($t->status !== 'running')<button wire:click="cancelTask({{ $t->id }})" class="text-xs text-red-600 hover:underline">Batalkan</button>@endif
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-zinc-500">Antrean kosong.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800"><h3 class="font-semibold">Job gagal/ditolak <span class="text-xs font-normal text-zinc-500">· {{ $failedJobs }} di failed_jobs</span></h3></div>
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @forelse ($failed as $t)
                    <li class="px-4 py-2"><div class="flex items-center gap-2"><a href="{{ route('sessions.show', $t->generation_session_id) }}" class="flex-1 truncate hover:underline">{{ $t->label }}</a><x-session-status :status="$t->status" /></div>
                        <p class="text-xs text-zinc-500">{{ $t->session->user->name }} · {{ $t->updated_at->translatedFormat('d M H:i') }}</p><p class="text-xs text-red-600">{{ \Illuminate\Support\Str::limit($t->error, 200) }}</p></li>
                @empty
                    <li class="px-4 py-6 text-center text-zinc-500">Tidak ada.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
