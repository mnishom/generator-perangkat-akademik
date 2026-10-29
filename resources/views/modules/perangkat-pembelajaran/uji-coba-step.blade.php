<div class="space-y-4" @if ($task && ! $task->isFinished()) wire:poll.5s @endif>
    <div class="card space-y-4 p-6">
        <div>
            <h3 class="text-lg font-semibold">Uji coba satu MK <span class="text-sm font-normal text-zinc-500">(disarankan)</span></h3>
            <p class="mt-1 text-sm text-zinc-500">Jalankan generate untuk satu MK terlebih dahulu, periksa hasil dan laporan pemeriksaannya, sesuaikan prompt bila perlu, lalu lanjutkan ke generate massal. MK yang lolos uji coba tidak di-generate ulang.</p>
        </div>
        @if (! $hasRps)
            <p class="text-sm text-zinc-500">Tidak ada RPS yang dibuat pada sesi ini (hanya Silabus), jadi uji coba per MK tidak tersedia.</p>
        @elseif ($items->isEmpty())
            <p class="text-sm text-zinc-500">Belum ada MK yang dipilih.</p>
        @else
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-64 flex-1">
                    <label class="label">MK uji coba</label>
                    <select wire:model="itemId" class="input" @disabled($task && ! $task->isFinished())>
                        @foreach ($items as $it)
                            <option value="{{ $it->id }}">{{ $it->ref_code }} — {{ $it->ref_label }} ({{ \App\Core\Curriculum\CurriculumData::JENIS_LABEL[$it->input['jenis'] ?? 'lainnya'] ?? '' }})</option>
                        @endforeach
                    </select>
                </div>
                <button wire:click="run" class="btn-primary" wire:loading.attr="disabled" @disabled($task && ! $task->isFinished())>{{ $task ? 'Jalankan uji coba lagi' : 'Jalankan uji coba' }}</button>
            </div>
        @endif
    </div>

    @if ($task)
        <div class="card space-y-3 p-6">
            <div class="flex items-center gap-3">
                <h4 class="font-semibold">{{ $task->label }}</h4>
                <x-session-status :status="$task->status" />
                <span class="text-sm text-zinc-500">langkah {{ $task->progressLabel() }}</span>
            </div>
            @if (! $task->isFinished())
                <p class="text-sm text-zinc-500">Claude sedang menyusun dokumen; biasanya beberapa menit per dokumen. Halaman diperbarui otomatis.</p>
            @endif
            @if ($task->error)<p class="text-sm text-red-600">{{ $task->error }}</p>@endif
            @foreach ($docs as $doc)
                <x-document-result :doc="$doc" :label="$docTypes[$doc->doc_type]->label ?? $doc->doc_type" />
            @endforeach
            @if ($task->isFinished())
                <p class="text-xs text-zinc-500">Dokumen hasil AI wajib ditinjau dosen dan prodi sebelum disahkan. Bila hasil kurang sesuai, ubah prompt lalu jalankan uji coba lagi.</p>
            @endif
        </div>
    @endif

    <div class="flex flex-wrap justify-between gap-2">
        <button wire:click="editPrompt" class="btn-secondary">&larr; Ubah prompt</button>
        <button wire:click="skip" class="btn-primary">{{ $task ? 'Lanjut ke generate massal' : 'Lewati uji coba' }} &rarr;</button>
    </div>
</div>
