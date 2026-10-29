<div class="space-y-4">
    <div class="card p-6">
        <h3 class="text-lg font-semibold">{{ $title }}</h3>
        @if ($intro)<p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $intro }}</p>@endif
    </div>

    @foreach ($requirements as $role => $req)
        @php($current = isset($inputs[$role]) ? $selected[$inputs[$role]] ?? null : null)
        <div class="card grid gap-5 p-6 lg:grid-cols-2" wire:key="role-{{ $role }}">
            <div class="space-y-3">
                <div>
                    <h4 class="font-semibold">{{ $req->label }} @if ($req->required)<span class="text-red-600">*</span>@else<span class="text-xs font-normal text-zinc-500">(opsional)</span>@endif</h4>
                    @if ($req->help)<p class="text-sm text-zinc-500">{{ $req->help }}</p>@endif
                </div>
                @if ($current)
                    <div class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm dark:border-emerald-900 dark:bg-emerald-950">
                        <span class="badge bg-white text-emerald-700 dark:bg-zinc-900">{{ strtoupper($current->extension) }}</span>
                        <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $current->original_name }}</p><p class="text-xs text-zinc-500">{{ $current->sizeForHumans() }}{{ $current->is_shared ? ' · berkas bersama' : '' }}</p></div>
                        <button wire:click="choose('{{ $role }}', null)" class="text-xs text-red-600 hover:underline">Lepas</button>
                    </div>
                @else
                    <p class="rounded-lg border border-dashed border-zinc-300 p-3 text-sm text-zinc-500 dark:border-zinc-700">Belum dipilih.</p>
                @endif
                @if (($options[$role] ?? collect())->isNotEmpty())
                    <div>
                        <label class="label">Pilih dari pustaka</label>
                        <select class="input" wire:change="choose('{{ $role }}', $event.target.value ? parseInt($event.target.value) : null)">
                            <option value="">— Pilih berkas tersimpan —</option>
                            @foreach ($options[$role] as $f)
                                <option value="{{ $f->id }}" @selected($current?->id === $f->id)>{{ $f->original_name }} · {{ $f->created_at->translatedFormat('d M Y') }}{{ $f->is_shared ? ' · bersama' : '' }}{{ $f->description ? ' · '.$f->description : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div>
                <x-dropzone :model="'uploads.'.$role" :accept="'.'.implode(',.', $req->mimes)" class="h-full" />
                @error('uploads.'.$role) <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    @endforeach

    <div class="flex justify-between">
        <button wire:click="back" class="btn-secondary">&larr; Kembali</button>
        <button wire:click="continue" class="btn-primary">Lanjut &rarr;</button>
    </div>
</div>
