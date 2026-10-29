<form wire:submit="save" class="mx-auto max-w-3xl space-y-6">
    <section class="card p-6">
        <h2 class="font-semibold">Sakelar global generate</h2>
        <label class="mt-4 flex items-center gap-3">
            <input type="checkbox" wire:model.live="form.generate_enabled" class="h-5 w-5 rounded text-marun-700">
            <span class="text-sm">Fitur generate <strong>{{ $form['generate_enabled'] ? 'DIBUKA' : 'DITUTUP' }}</strong> untuk semua pengguna</span>
        </label>
        <div class="mt-4">
            <label class="label">Pesan saat ditutup</label>
            <input wire:model="form.generate_closed_message" class="input">
        </div>
        <p class="mt-2 text-xs text-zinc-500">Job yang sudah antre juga memeriksa sakelar ini sebelum memanggil Claude.</p>
    </section>

    <section class="card grid gap-4 p-6 sm:grid-cols-2">
        <h2 class="font-semibold sm:col-span-2">Pemrosesan</h2>
        <div>
            <label class="label">Model default</label>
            <select wire:model="form.default_model" class="input">
                @foreach ($models as $id => $m)
                    <option value="{{ $id }}">{{ $m['label'] }} ({{ $id }})</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Job berjalan bersamaan (maks.)</label><input type="number" min="1" max="10" wire:model="form.max_concurrent_jobs" class="input"></div>
        <div><label class="label">Batas ukuran unggahan (MB)</label><input type="number" min="1" wire:model="form.max_upload_mb" class="input"></div>
        <div><label class="label">Perbaiki otomatis (maks. per dokumen)</label><input type="number" min="0" max="5" wire:model="form.autofix_max" class="input"></div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="form.delete_remote_files" class="rounded text-marun-700"> Hapus berkas masukan dari Anthropic Files API setelah job selesai</label>
        @if ($errors->any())
            <ul class="text-sm text-red-600 sm:col-span-2">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
    </section>

    <section class="card grid gap-4 p-6 sm:grid-cols-3">
        <h2 class="font-semibold sm:col-span-3">Batas pemakaian global <span class="text-sm font-normal text-zinc-500">(kosong = tanpa batas)</span></h2>
        <div><label class="label">Generate / hari</label><input type="number" min="0" wire:model="limit.max_generate_per_day" class="input"></div>
        <div><label class="label">Generate / bulan</label><input type="number" min="0" wire:model="limit.max_generate_per_month" class="input"></div>
        <div><label class="label">Token / bulan</label><input type="number" min="0" wire:model="limit.max_tokens_per_month" class="input"></div>
    </section>

    <div class="flex justify-end"><button class="btn-primary">Simpan pengaturan</button></div>
</form>
