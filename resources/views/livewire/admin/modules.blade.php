<div class="space-y-6">
    @forelse ($modules as $key => $m)
        <form wire:submit="save('{{ $key }}')" class="card space-y-4 p-6" wire:key="mod-{{ $key }}">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-krem-200 text-marun-800"><x-icon :name="$m->icon()" /></div>
                <div class="flex-1">
                    <h2 class="font-semibold">{{ $m->name() }} <span class="font-mono text-xs text-zinc-500">{{ $key }}</span></h2>
                    <p class="text-sm text-zinc-500">{{ $m->description() }}</p>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="states.{{ $key }}.enabled" class="rounded text-marun-700"> Modul aktif untuk semua pengguna</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="states.{{ $key }}.generate_open" class="rounded text-marun-700"> Generate dibuka (sakelar modul)</label>
                <div>
                    <label class="label">Model khusus modul</label>
                    <select wire:model="states.{{ $key }}.model" class="input">
                        <option value="">Ikut model default sistem</option>
                        @foreach ($models as $id => $model)<option value="{{ $id }}">{{ $model['label'] }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="label">Generate / hari (seluruh pengguna)</label><input type="number" min="0" wire:model="limits.{{ $key }}.max_generate_per_day" class="input"></div>
                <div><label class="label">Generate / bulan</label><input type="number" min="0" wire:model="limits.{{ $key }}.max_generate_per_month" class="input"></div>
                <div><label class="label">Token / bulan</label><input type="number" min="0" wire:model="limits.{{ $key }}.max_tokens_per_month" class="input"></div>
            </div>
            <div class="flex justify-end"><button class="btn-primary">Simpan</button></div>
        </form>
    @empty
        <div class="card p-6 text-sm text-zinc-500">Belum ada modul terdaftar di <code>config/modules.php</code>.</div>
    @endforelse

    @if ($modules)
        <div class="card p-6">
            <h2 class="font-semibold">Pengecualian akses per prodi / pengguna</h2>
            <p class="mt-1 text-sm text-zinc-500">Aturan per pengguna mengalahkan aturan per prodi, dan keduanya mengalahkan status aktif modul.</p>
            <form wire:submit="addRule" class="mt-4 grid gap-3 sm:grid-cols-5 sm:items-end">
                <div><label class="label">Modul</label><select wire:model="rule.module_key" class="input">@foreach ($modules as $key => $m)<option value="{{ $key }}">{{ $m->name() }}</option>@endforeach</select></div>
                <div><label class="label">Cakupan</label><select wire:model.live="rule.scope" class="input"><option value="prodi">Prodi</option><option value="user">Pengguna</option></select></div>
                <div>
                    <label class="label">{{ $rule['scope'] === 'user' ? 'Pengguna' : 'Prodi' }}</label>
                    <select wire:model="rule.scope_id" class="input">
                        <option value="">—</option>
                        @if ($rule['scope'] === 'user')
                            @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach
                        @else
                            @foreach ($prodis as $p)<option value="{{ $p->id }}">{{ $p->namaLengkap() }}</option>@endforeach
                        @endif
                    </select>
                    @error('rule.scope_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="label">Akses</label><select wire:model="rule.allowed" class="input"><option value="1">Izinkan</option><option value="0">Tolak</option></select></div>
                <button class="btn-primary">Tambah aturan</button>
            </form>
            <ul class="mt-4 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @forelse ($rules as $r)
                    <li class="flex items-center gap-3 py-2" wire:key="rule-{{ $r->id }}">
                        <span class="font-mono text-xs">{{ $r->module_key }}</span>
                        <span>{{ $r->scope === 'user' ? 'Pengguna: '.($userNames[$r->scope_id]->name ?? '#'.$r->scope_id) : 'Prodi: '.($prodiNames[$r->scope_id]?->namaLengkap() ?? '#'.$r->scope_id) }}</span>
                        <span @class(['badge', 'bg-emerald-100 text-emerald-800' => $r->allowed, 'bg-red-100 text-red-800' => ! $r->allowed])>{{ $r->allowed ? 'Izinkan' : 'Tolak' }}</span>
                        <button type="button" wire:click="removeRule({{ $r->id }})" wire:confirm="Hapus aturan ini?" class="ml-auto text-red-600 hover:underline">Hapus</button>
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">Belum ada pengecualian.</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
