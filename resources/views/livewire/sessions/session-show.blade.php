<div class="mx-auto max-w-6xl space-y-6" @if ($session->isActive()) wire:poll.5s @endif>
    {{-- Kepala sesi --}}
    <div class="card flex flex-wrap items-center gap-4 p-6">
        <div class="min-w-0 flex-1">
            <p class="text-sm text-zinc-500">{{ $moduleDef?->name() ?? $session->module_key }} · {{ $session->prodi?->namaLengkap() }} · {{ $session->tahun_akademik ?: '—' }}</p>
            <h2 class="text-xl font-semibold">{{ $session->title ?: 'Sesi #'.$session->id }}</h2>
            <p class="text-xs text-zinc-500">Dibuat {{ $session->created_at->translatedFormat('d M Y H:i') }} oleh {{ $session->user->name }} · Model {{ $session->model ?? '—' }}
                @if ($session->finished_at) · selesai {{ $session->finished_at->translatedFormat('d M Y H:i') }} @endif</p>
        </div>
        <x-session-status :status="$session->status" />
        <div class="flex flex-wrap gap-2">
            @if (! $readOnly && ! $session->option('wizard_done') && $moduleDef)
                <a href="{{ route('modules.wizard', [$session->module_key, $session]) }}" class="btn-secondary" wire:navigate>Lanjutkan wizard</a>
            @endif
            @if (! $readOnly && $session->isActive())
                <button wire:click="cancel" wire:confirm="Batalkan proses yang masih antre?" class="btn-secondary">Batalkan antrean</button>
            @endif
            @if ($latest->whereNotNull('path')->isNotEmpty())
                <a href="{{ route('sessions.zip', $session) }}" class="btn-primary">⬇ Unduh semua (ZIP)</a>
            @endif
        </div>
    </div>

    @if ($readOnly)
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">Mode baca (admin). Akses ini tercatat di audit log.</div>
    @endif

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
        <strong>Pengingat:</strong> dokumen hasil AI wajib ditinjau dosen dan prodi sebelum disahkan, terutama pustaka yang ditandai "perlu verifikasi".
    </div>

    @php
        $actions = function ($doc) use ($readOnly, $canAutofix) {
            return ['doc' => $doc, 'readOnly' => $readOnly, 'autofix' => $canAutofix($doc)];
        };
    @endphp

    {{-- Dokumen tingkat sesi (mis. Silabus Prodi) --}}
    @if ($sessionDocs->isNotEmpty() || $sessionTasks->isNotEmpty())
        <section class="card space-y-3 p-6">
            <h3 class="font-semibold">Dokumen tingkat sesi</h3>
            @foreach ($sessionTasks as $task)
                <div class="flex flex-wrap items-center gap-2 text-sm" wire:key="st-{{ $task->id }}">
                    <span class="font-medium">{{ $task->label }}</span>
                    <x-session-status :status="$task->status" />
                    <span class="text-zinc-500">tahap {{ $task->progressLabel() }}</span>
                    @if ($task->status === 'running')<span class="text-xs text-zinc-500">· {{ $task->jobSteps()[$task->current_step]->label ?? '' }}</span>@endif
                    @if ($task->error)<span class="text-xs text-red-600">{{ $task->error }}</span>@endif
                </div>
            @endforeach
            @foreach ($sessionDocs as $doc)
                <x-document-result :doc="$doc" :label="$docTypes[$doc->doc_type]->label ?? $doc->doc_type" wire:key="sd-{{ $doc->id }}">
                    @include('livewire.sessions.partials.doc-actions', $actions($doc))
                </x-document-result>
            @endforeach
        </section>
    @endif

    {{-- Per butir (mis. per MK) --}}
    @if ($items->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="border-b border-zinc-100 px-6 py-4 dark:border-zinc-800"><h3 class="font-semibold">Per mata kuliah <span class="text-sm font-normal text-zinc-500">({{ $items->count() }})</span></h3></div>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($items as $item)
                    @php($tasks = $tasksByItem->get($item->id, collect())->sortByDesc('id'))
                    @php($task = $tasks->first())
                    @php($itemDocs = $latest->where('generation_item_id', $item->id))
                    <li class="space-y-3 px-6 py-4" wire:key="item-{{ $item->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-zinc-500">{{ $item->ref_code }}</span>
                            <span class="font-medium">{{ $item->ref_label }}</span>
                            @if ($task)
                                <x-session-status :status="$task->status" />
                                <span class="text-xs text-zinc-500">langkah {{ $task->progressLabel() }}{{ $task->status === 'running' ? ' · '.($task->jobSteps()[$task->current_step]->label ?? '') : '' }}</span>
                                @if ($task->input_tokens)<span class="text-xs text-zinc-400">· {{ number_format($task->input_tokens + $task->output_tokens, 0, ',', '.') }} token</span>@endif
                            @else
                                <span class="badge bg-zinc-100 text-zinc-500 dark:bg-zinc-800">Belum diproses</span>
                            @endif
                            @if ($itemDocs->whereNotNull('path')->isNotEmpty())
                                <a href="{{ route('sessions.zip.item', [$session, $item]) }}" class="ml-auto text-sm text-marun-700 hover:underline dark:text-krem-200">⬇ ZIP MK ini</a>
                            @endif
                        </div>
                        @if ($task?->error)<p class="text-sm text-red-600">{{ $task->error }}</p>@endif
                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach ($itemDocs as $doc)
                                <x-document-result :doc="$doc" :label="$docTypes[$doc->doc_type]->label ?? $doc->doc_type" wire:key="d-{{ $doc->id }}">
                                    @include('livewire.sessions.partials.doc-actions', $actions($doc))
                                </x-document-result>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Modal generate ulang --}}
    @if ($regenId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <form wire:submit="regenerate" class="card w-full max-w-lg space-y-4 p-6">
                <h3 class="text-lg font-semibold">Generate ulang dengan instruksi tambahan</h3>
                <p class="text-sm text-zinc-500">Dijalankan di container dan percakapan yang sama. Hasilnya menjadi versi baru; versi lama tetap tersimpan.</p>
                <textarea wire:model="instruction" rows="4" class="input" placeholder="mis. ubah bobot UTS menjadi 25%"></textarea>
                @error('instruction')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2"><button type="button" wire:click="$set('regenId', null)" class="btn-secondary">Batal</button><button class="btn-primary">Generate ulang</button></div>
            </form>
        </div>
    @endif

    {{-- Modal riwayat versi --}}
    @if ($history->isNotEmpty())
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="card my-8 w-full max-w-3xl space-y-3 p-6" @click.outside="$wire.showHistory(null)">
                <div class="flex items-center"><h3 class="flex-1 text-lg font-semibold">Riwayat versi</h3><button wire:click="showHistory(null)" class="text-2xl leading-none text-zinc-500">&times;</button></div>
                @foreach ($history as $doc)
                    <x-document-result :doc="$doc" :label="(($docTypes[$doc->doc_type] ?? null)?->short() ?? $doc->doc_type).' · '.$doc->created_at->translatedFormat('d M Y H:i')" wire:key="h-{{ $doc->id }}" />
                @endforeach
            </div>
        </div>
    @endif
</div>
