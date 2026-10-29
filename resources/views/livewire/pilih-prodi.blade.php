<div class="mx-auto max-w-xl">
    <div class="card p-6">
        <h2 class="text-lg font-semibold">Pilih Program Studi</h2>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Program Studi menentukan pustaka berkas dan riwayat dokumen yang Anda gunakan.</p>

        <form wire:submit="save" class="mt-6 space-y-4">
            <div>
                <label for="prodi" class="label">Program Studi</label>
                <select id="prodi" wire:model="prodiId" class="input">
                    <option value="">— Pilih —</option>
                    @foreach ($prodis as $fakultas => $items)
                        <optgroup label="{{ $fakultas }}">
                            @foreach ($items as $prodi)
                                <option value="{{ $prodi->id }}">{{ $prodi->namaLengkap() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('prodiId') <p class="mt-1 text-sm text-red-600">Silakan pilih Program Studi.</p> @enderror
                @if ($prodis->isEmpty())
                    <p class="mt-2 text-sm text-amber-700">Belum ada Program Studi. Hubungi admin.</p>
                @endif
            </div>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Simpan</button>
        </form>
    </div>
</div>
