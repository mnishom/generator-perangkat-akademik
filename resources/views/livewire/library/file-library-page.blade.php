<div class="mx-auto max-w-6xl space-y-6">
    <div class="card grid gap-6 p-6 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-2">
            <div>
                <h2 class="font-semibold">Unggah ke pustaka</h2>
                <p class="mt-1 text-sm text-zinc-500">Berkas tersimpan per Program Studi dan dapat dipilih lagi pada sesi berikutnya tanpa mengunggah ulang.</p>
            </div>
            <div>
                <label class="label">Jenis berkas</label>
                <select wire:model.live="role" class="input">
                    @foreach ($roles as $key => $r)
                        <option value="{{ $key }}">{{ $r['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Keterangan (opsional)</label>
                <input wire:model="description" class="input" placeholder="mis. Kurikulum OBE 2025">
            </div>
            @if (auth()->user()->isAdmin())
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="shared" class="rounded text-marun-700"> Jadikan <strong>berkas bersama</strong> (template resmi universitas, terlihat semua prodi)</label>
            @endif
        </div>
        <div class="lg:col-span-3">
            <x-dropzone model="upload" :accept="'.'.implode(',.', $roles[$role]['mimes'] ?? ['docx', 'pdf'])" class="h-full" />
            @error('upload') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="text-zinc-500">Saring:</span>
        <button wire:click="$set('filter', '')" @class(['badge px-3 py-1', 'bg-marun-700 text-white' => $filter === '', 'bg-zinc-100 dark:bg-zinc-800' => $filter !== ''])>Semua</button>
        @foreach ($roles as $key => $r)
            <button wire:click="$set('filter', '{{ $key }}')" @class(['badge px-3 py-1', 'bg-marun-700 text-white' => $filter === $key, 'bg-zinc-100 dark:bg-zinc-800' => $filter !== $key])>{{ $r['label'] }}</button>
        @endforeach
    </div>

    @forelse ($files as $role => $items)
        <div class="card">
            <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800"><h3 class="font-semibold">{{ $roles[$role]['label'] ?? $role }}</h3></div>
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ($items as $f)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="lf-{{ $f->id }}">
                        <span class="badge {{ $f->extension === 'pdf' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700' }}">{{ strtoupper($f->extension) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $f->original_name }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ $f->sizeForHumans() }} · {{ $f->created_at->translatedFormat('d M Y') }} · {{ $f->uploader?->name ?? '—' }}
                                @if ($f->description) · {{ $f->description }} @endif
                            </p>
                        </div>
                        @if ($f->is_shared)<span class="badge bg-krem-200 text-marun-800">Bersama</span>@endif
                        <a href="{{ route('library.download', $f) }}" class="text-marun-700 hover:underline dark:text-krem-200">Unduh</a>
                        @can('delete', $f)
                            <button wire:click="delete({{ $f->id }})" wire:confirm="Hapus berkas ini dari pustaka? Sesi lama yang memakainya tetap utuh." class="text-red-600 hover:underline">Hapus</button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-zinc-500">Pustaka masih kosong.</div>
    @endforelse
</div>
