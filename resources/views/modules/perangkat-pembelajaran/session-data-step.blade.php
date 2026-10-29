<form wire:submit="continue" class="card space-y-6 p-6">
    <div>
        <h3 class="text-lg font-semibold">Data sesi</h3>
        <p class="mt-1 text-sm text-zinc-500">Data ini dipakai seragam di semua dokumen sesi ini.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-3"><label class="label">Judul sesi (opsional)</label><input wire:model="form.title" class="input" placeholder="mis. RPS Semester Ganjil 2025/2026"></div>
        <div><label class="label">Tanggal penyusunan dokumen</label><input type="date" wire:model="form.tanggal_penyusunan" class="input">@error('form.tanggal_penyusunan')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="label">Tahun akademik</label><input wire:model="form.tahun_akademik" class="input" placeholder="2025/2026">@error('form.tahun_akademik')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
    </div>
    <fieldset class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
        <legend class="px-1 text-sm font-medium">Data identitas (opsional)</legend>
        <p class="mb-4 text-xs text-zinc-500">Bagian yang dikosongkan <strong>tidak akan dikarang</strong> oleh Claude; bagian terkait di dokumen dibiarkan kosong atau bertitik-titik sesuai template.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nama fakultas</label><input wire:model="form.nama_fakultas" class="input"></div>
            <div><label class="label">Nama program studi</label><input wire:model="form.nama_prodi" class="input"></div>
            <div><label class="label">Nama Ketua Program Studi</label><input wire:model="form.nama_kaprodi" class="input"></div>
            <div><label class="label">Nama Dekan</label><input wire:model="form.nama_dekan" class="input"></div>
        </div>
    </fieldset>
    <div class="flex justify-end"><button class="btn-primary">Lanjut &rarr;</button></div>
</form>
