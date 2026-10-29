<div class="grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-1">
        <div class="flex items-center justify-between border-b border-zinc-100 p-4 dark:border-zinc-800">
            <h2 class="font-semibold">Fakultas</h2>
            <button wire:click="editFakultas" class="btn-primary px-3 py-1.5">Tambah</button>
        </div>
        <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
            @forelse ($fakultas as $f)
                <li class="flex items-center justify-between gap-2 p-4" wire:key="f-{{ $f->id }}">
                    <div>
                        <p class="font-medium">{{ $f->nama }} @if ($f->singkatan)<span class="text-zinc-500">({{ $f->singkatan }})</span>@endif</p>
                        <p class="text-xs text-zinc-500">Dekan: {{ $f->nama_dekan ?: '—' }} · {{ $f->prodis_count }} prodi</p>
                    </div>
                    <button wire:click="editFakultas({{ $f->id }})" class="text-marun-700 hover:underline dark:text-krem-200">Ubah</button>
                </li>
            @empty
                <li class="p-4 text-zinc-500">Belum ada fakultas.</li>
            @endforelse
        </ul>
    </div>

    <div class="card overflow-x-auto xl:col-span-2">
        <div class="flex items-center justify-between border-b border-zinc-100 p-4 dark:border-zinc-800">
            <h2 class="font-semibold">Program Studi</h2>
            <button wire:click="editProdi" class="btn-primary px-3 py-1.5">Tambah</button>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                <tr><th class="px-4 py-3">Prodi</th><th class="px-4 py-3">Fakultas</th><th class="px-4 py-3">Kaprodi</th><th class="px-4 py-3">Pengguna</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($prodis as $p)
                    <tr wire:key="p-{{ $p->id }}" @class(['opacity-50' => ! $p->is_active])>
                        <td class="px-4 py-3"><p class="font-medium">{{ $p->namaLengkap() }}</p><p class="text-xs text-zinc-500">{{ $p->kode }} {{ $p->is_active ? '' : '· nonaktif' }}</p></td>
                        <td class="px-4 py-3">{{ $p->fakultas?->nama ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $p->nama_kaprodi ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $p->users_count }}</td>
                        <td class="px-4 py-3 text-right"><button wire:click="editProdi({{ $p->id }})" class="text-marun-700 hover:underline dark:text-krem-200">Ubah</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">Belum ada program studi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showFakultas)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <form wire:submit="saveFakultas" class="card w-full max-w-lg space-y-4 p-6">
                <h3 class="text-lg font-semibold">{{ $fakultasId ? 'Ubah' : 'Tambah' }} Fakultas</h3>
                <div><label class="label">Nama fakultas</label><input wire:model="fakultasForm.nama" class="input">@error('fakultasForm.nama')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Singkatan</label><input wire:model="fakultasForm.singkatan" class="input"></div>
                    <div><label class="label">Nama Dekan</label><input wire:model="fakultasForm.nama_dekan" class="input"></div>
                </div>
                <div class="flex justify-end gap-2"><button type="button" wire:click="$set('showFakultas', false)" class="btn-secondary">Batal</button><button class="btn-primary">Simpan</button></div>
            </form>
        </div>
    @endif

    @if ($showProdi)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <form wire:submit="saveProdi" class="card w-full max-w-lg space-y-4 p-6">
                <h3 class="text-lg font-semibold">{{ $prodiId ? 'Ubah' : 'Tambah' }} Program Studi</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="label">Jenjang</label><input wire:model="prodiForm.jenjang" class="input" placeholder="D4"></div>
                    <div class="sm:col-span-2"><label class="label">Nama prodi</label><input wire:model="prodiForm.nama" class="input">@error('prodiForm.nama')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Kode</label><input wire:model="prodiForm.kode" class="input">@error('prodiForm.kode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="label">Fakultas</label>
                        <select wire:model="prodiForm.fakultas_id" class="input"><option value="">—</option>@foreach ($fakultas as $f)<option value="{{ $f->id }}">{{ $f->nama }}</option>@endforeach</select>
                    </div>
                </div>
                <div><label class="label">Nama Ketua Program Studi</label><input wire:model="prodiForm.nama_kaprodi" class="input"></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="prodiForm.is_active" class="rounded text-marun-700"> Aktif</label>
                <div class="flex justify-end gap-2"><button type="button" wire:click="$set('showProdi', false)" class="btn-secondary">Batal</button><button class="btn-primary">Simpan</button></div>
            </form>
        </div>
    @endif
</div>
