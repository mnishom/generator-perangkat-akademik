<div class="space-y-4" @if ($extraction?->isWorking()) wire:poll.4s @endif>
    <div class="card p-6">
        <h3 class="text-lg font-semibold">Ekstraksi &amp; verifikasi data kurikulum</h3>
        <p class="mt-1 text-sm text-zinc-500">Claude membaca <strong>{{ $file?->original_name }}</strong> dan mengembalikan data terstruktur setiap MK. Periksa dan koreksi data (mis. SKS atau pemetaan CPMK yang salah terbaca) sebelum melanjutkan. Data terverifikasi menjadi sumber data utama untuk seluruh Job generate.</p>
    </div>

    @if (! $extraction)
        <div class="card space-y-4 p-6">
            @if ($reusable)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950">
                    <p>Dokumen kurikulum ini sudah pernah diekstrak dan <strong>diverifikasi</strong> pada {{ $reusable->verified_at?->translatedFormat('d M Y H:i') }} oleh {{ $reusable->verifier?->name }} ({{ count($reusable->courses()) }} MK).</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button wire:click="useReusable({{ $reusable->id }})" class="btn-primary">Pakai data terverifikasi ini</button>
                        <button wire:click="startExtraction" wire:confirm="Ekstrak ulang memakai kuota Claude. Lanjutkan?" class="btn-secondary">Ekstrak ulang</button>
                    </div>
                </div>
            @else
                <button wire:click="startExtraction" class="btn-primary" wire:loading.attr="disabled">Mulai ekstraksi data kurikulum</button>
            @endif
        </div>
    @elseif ($extraction->isWorking())
        <div class="card flex items-center gap-4 p-6">
            <svg class="h-6 w-6 animate-spin text-marun-700" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3"/></svg>
            <div>
                <p class="font-medium">{{ $extraction->status === 'queued' ? 'Menunggu antrean…' : 'Claude sedang membaca dokumen kurikulum…' }}</p>
                <p class="text-sm text-zinc-500">Anda boleh meninggalkan halaman ini; proses tetap berjalan.</p>
                @if ($extraction->error)<p class="text-xs text-amber-700">{{ $extraction->error }}</p>@endif
            </div>
        </div>
    @elseif (in_array($extraction->status, ['failed', 'blocked']))
        <div class="card space-y-3 border-red-200 p-6">
            <p class="font-medium text-red-700">Ekstraksi {{ $extraction->status === 'blocked' ? 'ditolak' : 'gagal' }}: {{ $extraction->error }}</p>
            <button wire:click="startExtraction" class="btn-primary">Coba lagi</button>
        </div>
    @else
        <livewire:curriculum.curriculum-editor :extraction-id="$extraction->id" :selectable="$hasRps" wire:model="selectedCodes" :key="'ce-'.$extraction->id" />

        <div class="card space-y-3 p-6">
            <h4 class="font-semibold">Dokumen yang akan dibuat</h4>
            @if ($hasRps)
                <p class="text-sm">RPS{{ $hasKontrak ? ' dan Kontrak Perkuliahan' : '' }} untuk <strong>{{ count($selectedCodes) }}</strong> MK yang dicentang di tabel.</p>
            @else
                <p class="text-sm text-zinc-500">Template RPS tidak dipilih, jadi RPS/Kontrak tidak dibuat.</p>
            @endif
            @if ($hasSilabus)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="generateSilabus" class="rounded text-marun-700"> Buat juga <strong>Silabus Prodi</strong> (satu dokumen untuk seluruh {{ count($extraction->courses()) }} MK, disusun bertahap per semester)</label>
            @endif
        </div>
    @endif

    @error('verify')<p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ $message }}</p>@enderror

    <div class="flex justify-between">
        <button wire:click="back" class="btn-secondary">&larr; Kembali</button>
        <button wire:click="continue" class="btn-primary" @disabled(! $extraction?->isVerified())>Lanjut &rarr;</button>
    </div>
</div>
