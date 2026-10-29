<div class="space-y-4">
    <div class="card p-4">
        <label class="label">Tindakan</label>
        <select wire:model.live="action" class="input max-w-xs">
            <option value="">Semua</option>
            @foreach ($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
        </select>
    </div>
    <div class="card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                <tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Admin</th><th class="px-4 py-3">Tindakan</th><th class="px-4 py-3">Sasaran</th><th class="px-4 py-3">Rincian</th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->actor?->name ?? 'sistem' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->target_type ? $log->target_type.' #'.$log->target_id : '—' }}</td>
                        <td class="max-w-md px-4 py-3"><code class="block truncate text-xs text-zinc-500" title="{{ json_encode($log->meta, JSON_UNESCAPED_UNICODE) }}">{{ $log->meta ? json_encode($log->meta, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">Belum ada catatan.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $logs->links() }}</div>
    </div>
</div>
