<div class="space-y-4">
    <div class="card flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-48 flex-1">
            <label class="label">Cari</label>
            <input type="search" wire:model.live.debounce.400ms="search" class="input" placeholder="Nama atau email">
        </div>
        <div>
            <label class="label">Peran</label>
            <select wire:model.live="role" class="input">
                <option value="">Semua</option>
                @foreach (\App\Enums\UserRole::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Status</label>
            <select wire:model.live="status" class="input">
                <option value="">Semua</option>
                @foreach (\App\Enums\AccountStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Prodi</label>
            <select wire:model.live="prodi" class="input">
                <option value="">Semua</option>
                @foreach ($prodis as $p)
                    <option value="{{ $p->id }}">{{ $p->namaLengkap() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Prodi</th>
                    <th class="px-4 py-3">Peran</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Login terakhir</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $u->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $u->email }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $u->prodi?->namaLengkap() ?? '—' }} @if ($u->prodi_locked)<span class="badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800" title="Prodi dikunci">🔒</span>@endif</td>
                        <td class="px-4 py-3">{{ $u->role->label() }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$u->effectiveStatus()" /></td>
                        <td class="px-4 py-3 text-zinc-500">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><button wire:click="edit({{ $u->id }})" class="btn-secondary px-3 py-1.5">Kelola</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">Tidak ada pengguna.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $users->links() }}</div>
    </div>

    @if ($editing)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center" wire:key="modal-{{ $editing->id }}">
            <div class="card w-full max-w-2xl p-6" @click.outside="$wire.cancel()">
                <h3 class="text-lg font-semibold">{{ $editing->name }}</h3>
                <p class="text-sm text-zinc-500">{{ $editing->email }}</p>

                <form wire:submit="save" class="mt-5 space-y-5">
                    @error('form.role') <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ $message }}</p> @enderror

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Peran</label>
                            <select wire:model="form.role" class="input">
                                @foreach (\App\Enums\UserRole::cases() as $r)
                                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">Program Studi</label>
                            <select wire:model="form.prodi_id" class="input">
                                <option value="">—</option>
                                @foreach ($prodis as $p)
                                    <option value="{{ $p->id }}">{{ $p->namaLengkap() }}</option>
                                @endforeach
                            </select>
                            <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.prodi_locked" class="rounded text-marun-700"> Kunci prodi (pengguna tidak dapat mengganti)</label>
                        </div>
                    </div>

                    <fieldset class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        <legend class="px-1 text-sm font-medium">Status akun</legend>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="label">Status</label>
                                <select wire:model="form.status" class="input">
                                    @foreach (\App\Enums\AccountStatus::cases() as $s)
                                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label">Alasan (opsional)</label>
                                <input type="text" wire:model="form.status_reason" class="input">
                            </div>
                            <div>
                                <label class="label">Berakhir (opsional)</label>
                                <input type="datetime-local" wire:model="form.status_until" class="input">
                                @error('form.status_until') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <p class="text-xs text-zinc-500 sm:col-span-2 sm:self-end">Ditangguhkan: tetap bisa login dan mengunduh riwayat, tetapi tidak bisa generate. Dinonaktifkan: tidak bisa login.</p>
                        </div>
                        @if ($statusLogs->isNotEmpty())
                            <ul class="mt-4 space-y-1 text-xs text-zinc-500">
                                @foreach ($statusLogs as $log)
                                    <li>{{ $log->created_at->format('d/m/Y H:i') }}: {{ $log->from_status?->label() ?? '—' }} → {{ $log->to_status->label() }} oleh {{ $log->changer?->name ?? 'sistem' }}{{ $log->reason ? ' ('.$log->reason.')' : '' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </fieldset>

                    <fieldset class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        <legend class="px-1 text-sm font-medium">Batas pemakaian pengguna ini (kosong = ikut batas global)</legend>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div><label class="label">Generate / hari</label><input type="number" min="0" wire:model="form.max_generate_per_day" class="input"></div>
                            <div><label class="label">Generate / bulan</label><input type="number" min="0" wire:model="form.max_generate_per_month" class="input"></div>
                            <div><label class="label">Token / bulan</label><input type="number" min="0" wire:model="form.max_tokens_per_month" class="input"></div>
                        </div>
                    </fieldset>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancel" class="btn-secondary">Batal</button>
                        <button type="submit" class="btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
