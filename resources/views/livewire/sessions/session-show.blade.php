<div class="mx-auto max-w-6xl space-y-6" @if ($session->isActive()) wire:poll.5s @endif>
    <div class="card flex flex-wrap items-center gap-4 p-6">
        <div class="min-w-0 flex-1">
            <p class="text-sm text-zinc-500">{{ $moduleDef?->name() ?? $session->module_key }} · {{ $session->prodi?->namaLengkap() }}</p>
            <h2 class="text-xl font-semibold">{{ $session->title ?: 'Sesi #'.$session->id }}</h2>
            <p class="text-xs text-zinc-500">Dibuat {{ $session->created_at->translatedFormat('d M Y H:i') }} oleh {{ $session->user->name }} · Model {{ $session->model ?? '—' }}</p>
        </div>
        <x-session-status :status="$session->status" />
        @if (! $readOnly && $session->isActive())
            <button wire:click="cancel" wire:confirm="Batalkan proses yang masih antre?" class="btn-secondary">Batalkan antrean</button>
        @endif
    </div>

    @if ($readOnly)
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">Mode baca (admin). Akses ini tercatat di audit log.</div>
    @endif

    <div class="card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                <tr><th class="px-4 py-3">Proses</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Langkah</th><th class="px-4 py-3">Dokumen</th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($session->tasks as $task)
                    <tr wire:key="t-{{ $task->id }}">
                        <td class="px-4 py-3"><p class="font-medium">{{ $task->label }}</p>@if ($task->error)<p class="text-xs text-red-600">{{ $task->error }}</p>@endif</td>
                        <td class="px-4 py-3"><x-session-status :status="$task->status" /></td>
                        <td class="px-4 py-3">{{ $task->progressLabel() }}</td>
                        <td class="px-4 py-3">
                            @foreach ($session->documents->where('generation_task_id', $task->id)->unique('doc_type') as $doc)
                                <div class="flex items-center gap-2">
                                    <x-session-status :status="$doc->status" />
                                    @if ($doc->path)
                                        <a href="{{ route('documents.download', $doc) }}" class="text-marun-700 hover:underline dark:text-krem-200">{{ $doc->filename }}</a>
                                    @else
                                        <span>{{ $doc->filename ?? $doc->doc_type }}</span>
                                    @endif
                                    <span class="text-xs text-zinc-500">v{{ $doc->version }}</span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">Belum ada proses.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
