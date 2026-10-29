<div class="space-y-4">
    {{-- Ringkasan --}}
    <div class="grid gap-3 sm:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-zinc-500">Mata kuliah</p><p class="text-2xl font-semibold">{{ count($data['mata_kuliah']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-zinc-500">Total SKS (dijumlahkan)</p><p class="text-2xl font-semibold">{{ $anomalies['total_sks'] ?? '—' }} <span class="text-sm font-normal text-zinc-500">/ dokumen: {{ $data['prodi']['total_sks'] ?? '—' }}</span></p>
            @if (count($anomalies['tracks'] ?? []) > 1 || array_key_first($anomalies['tracks'] ?? ['semua' => 0]) !== 'semua')
                <p class="mt-1 text-xs text-zinc-500">per jalur: @foreach ($anomalies['tracks'] as $t => $n){{ $t }} {{ $n }}@if (! $loop->last), @endif @endforeach</p>
            @endif
        </div>
        <div class="card p-4"><p class="text-xs text-zinc-500">CPL prodi</p><p class="text-2xl font-semibold">{{ count($data['cpl']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-zinc-500">Catatan galat</p><p @class(['text-2xl font-semibold', 'text-red-600' => $errorCount, 'text-emerald-600' => ! $errorCount])>{{ $errorCount }}</p></div>
    </div>

    @foreach ($anomalies['global'] ?? [] as $flag)
        <div @class(['rounded-lg border p-3 text-sm', 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' => $flag['level'] === 'error', 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100' => $flag['level'] !== 'error'])>⚠ {{ $flag['message'] }}</div>
    @endforeach

    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 dark:border-zinc-800">
        <button wire:click="$set('tab', 'mk')" @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium', 'border-marun-700 text-marun-700 dark:text-krem-200' => $tab === 'mk', 'border-transparent text-zinc-500' => $tab !== 'mk'])>Mata Kuliah</button>
        <button wire:click="$set('tab', 'cpl')" @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium', 'border-marun-700 text-marun-700 dark:text-krem-200' => $tab === 'cpl', 'border-transparent text-zinc-500' => $tab !== 'cpl'])>CPL &amp; Profil Lulusan</button>
        <div class="ml-auto flex items-center gap-2 pb-2 text-xs">
            @if ($extraction->isVerified())
                <span class="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Terverifikasi {{ $extraction->verified_at?->translatedFormat('d M Y H:i') }} oleh {{ $extraction->verifier?->name }}</span>
            @else
                <span class="badge bg-amber-100 text-amber-800">Belum diverifikasi</span>
            @endif
        </div>
    </div>

    @if ($tab === 'mk')
        @if ($selectable)
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-zinc-500">Pilih:</span>
                <button wire:click="selectAll(true)" class="badge bg-zinc-100 px-3 py-1 dark:bg-zinc-800">Semua</button>
                <button wire:click="selectAll(false)" class="badge bg-zinc-100 px-3 py-1 dark:bg-zinc-800">Kosongkan</button>
                @foreach ($semesters as $sem)
                    <button wire:click="selectSemester({{ $sem }})" class="badge bg-zinc-100 px-3 py-1 dark:bg-zinc-800">+ Semester {{ $sem }}</button>
                @endforeach
                <span class="ml-auto text-zinc-500">{{ count($selected) }} MK dipilih untuk RPS &amp; Kontrak</span>
            </div>
        @endif

        <div class="card overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/50">
                    <tr>
                        @if ($selectable)<th class="w-8 px-3 py-3"></th>@endif
                        <th class="px-3 py-3">Kode</th><th class="px-3 py-3">Nama MK</th><th class="px-3 py-3">SKS</th><th class="px-3 py-3">Smt</th><th class="px-3 py-3">Jenis</th><th class="px-3 py-3">Peminatan</th><th class="px-3 py-3">CPL / CPMK</th><th class="px-3 py-3">Catatan</th><th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['mata_kuliah'] as $i => $mk)
                        @php($flags = $anomalies['courses'][$mk['kode'] ?: '#'.($i + 1)] ?? [])
                        <tr wire:key="mk-{{ $i }}-{{ $mk['kode'] }}" @class(['bg-red-50/60 dark:bg-red-950/30' => collect($flags)->contains('level', 'error')])>
                            @if ($selectable)<td class="px-3 py-2"><input type="checkbox" value="{{ $mk['kode'] }}" wire:model.live="selected" class="rounded text-marun-700" @disabled(! $mk['kode'])></td>@endif
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $mk['kode'] ?: '—' }}</td>
                            <td class="px-3 py-2 font-medium">{{ $mk['nama'] ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $mk['sks']['total'] ?? '—' }}
                                @php($parts = array_filter(['T' => $mk['sks']['teori'], 'P' => $mk['sks']['praktikum'], 'L' => $mk['sks']['lapangan'], 'S' => $mk['sks']['seminar']], fn ($v) => $v !== null))
                                @if ($parts)<span class="text-xs text-zinc-500">({{ collect($parts)->map(fn ($v, $k) => $k.$v)->implode(' ') }})</span>@endif
                            </td>
                            <td class="px-3 py-2">{{ $mk['semester'] ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $jenisLabels[$mk['jenis']] ?? $mk['jenis'] }}</td>
                            <td class="px-3 py-2 text-xs">{{ ($mk['peminatan'] ?? []) ? implode(', ', $mk['peminatan']) : 'Wajib' }}</td>
                            <td class="px-3 py-2 text-xs">{{ count($mk['cpl']) }} CPL · {{ count($mk['cpmk']) }} CPMK</td>
                            <td class="px-3 py-2">
                                @foreach ($flags as $f)
                                    <p @class(['text-xs', 'text-red-700 dark:text-red-300' => $f['level'] === 'error', 'text-amber-700 dark:text-amber-300' => $f['level'] !== 'error'])>• {{ $f['message'] }}</p>
                                @endforeach
                            </td>
                            <td class="px-3 py-2 text-right"><button wire:click="edit({{ $i }})" class="text-marun-700 hover:underline dark:text-krem-200">Ubah</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-8 text-center text-zinc-500">Tidak ada MK.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <button wire:click="addCourse" class="btn-secondary">+ Tambah MK</button>
    @else
        <div class="card space-y-4 p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label class="label">Nama prodi (dokumen)</label><input wire:model="data.prodi.nama" class="input"></div>
                <div><label class="label">Jenjang</label><input wire:model="data.prodi.jenjang" class="input"></div>
                <div><label class="label">Total SKS menurut dokumen</label><input type="number" wire:model="data.prodi.total_sks" class="input"></div>
            </div>
            <h4 class="font-semibold">CPL Prodi</h4>
            @foreach ($data['cpl'] as $i => $c)
                <div class="flex gap-2" wire:key="cpl-{{ $i }}">
                    <input wire:model="data.cpl.{{ $i }}.kode" class="input w-32" placeholder="Kode">
                    <textarea wire:model="data.cpl.{{ $i }}.rumusan" rows="2" class="input flex-1" placeholder="Rumusan CPL"></textarea>
                    <button wire:click="removeCpl({{ $i }})" class="text-red-600" title="Hapus">&times;</button>
                </div>
            @endforeach
            <button wire:click="addCpl" class="btn-secondary px-3 py-1.5">+ CPL</button>
            <h4 class="font-semibold">Profil Lulusan</h4>
            <ul class="list-disc pl-5 text-sm">
                @forelse ($data['profil_lulusan'] as $p)<li><strong>{{ $p['kode'] }}</strong> {{ $p['deskripsi'] }}</li>@empty<li class="text-zinc-500">Tidak tercantum.</li>@endforelse
            </ul>
            <div class="flex justify-end"><button wire:click="saveCpl" class="btn-primary">Simpan</button></div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-end gap-3">
        <p class="text-xs text-zinc-500">Data yang diverifikasi menjadi sumber data utama seluruh Job generate di sesi ini dan dapat dipakai ulang selama dokumen kurikulumnya sama.</p>
        <button wire:click="verify" class="btn-primary">{{ $extraction->isVerified() ? 'Simpan perubahan & verifikasi ulang' : 'Simpan & verifikasi data' }}</button>
    </div>

    {{-- Modal ubah MK --}}
    @if ($editing !== null)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <form wire:submit="saveCourse" class="card my-8 w-full max-w-3xl space-y-4 p-6">
                <h3 class="text-lg font-semibold">Ubah data MK</h3>
                <div class="grid gap-3 sm:grid-cols-4">
                    <div><label class="label">Kode MK</label><input wire:model="form.kode" class="input">@error('form.kode')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-3"><label class="label">Nama MK</label><input wire:model="form.nama" class="input">@error('form.nama')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                </div>
                <div class="grid gap-3 sm:grid-cols-7">
                    <div><label class="label">SKS total</label><input type="number" wire:model="form.sks_total" class="input">@error('form.sks_total')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Teori</label><input type="number" wire:model="form.sks_teori" class="input"></div>
                    <div><label class="label">Praktik</label><input type="number" wire:model="form.sks_praktikum" class="input"></div>
                    <div><label class="label">Lapangan</label><input type="number" wire:model="form.sks_lapangan" class="input"></div>
                    <div><label class="label">Seminar</label><input type="number" wire:model="form.sks_seminar" class="input"></div>
                    <div><label class="label">Semester</label><input type="number" wire:model="form.semester" class="input"></div>
                    <div><label class="label">Jenis</label><select wire:model="form.jenis" class="input">@foreach ($jenisLabels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-3"><div><label class="label">MK prasyarat (pisahkan koma)</label><input wire:model="form.prasyarat" class="input"></div>
                        <div><label class="label">Peminatan (pisahkan koma; kosong = wajib)</label><input wire:model="form.peminatan" class="input"></div></div>
                    <div>
                        <label class="label">CPL yang dibebankan</label>
                        <div class="flex max-h-28 flex-wrap gap-2 overflow-y-auto rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                            @forelse ($data['cpl'] as $c)
                                <label class="flex items-center gap-1"><input type="checkbox" value="{{ $c['kode'] }}" wire:model="form.cpl" class="rounded text-marun-700"> {{ $c['kode'] }}</label>
                            @empty
                                <span class="text-zinc-500">Isi daftar CPL di tab "CPL &amp; Profil Lulusan".</span>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div>
                    <label class="label">CPMK</label>
                    <div class="space-y-2">
                        @foreach ($form['cpmk'] as $ci => $c)
                            <div class="flex flex-wrap gap-2 sm:flex-nowrap" wire:key="fcpmk-{{ $ci }}">
                                <input wire:model="form.cpmk.{{ $ci }}.kode" class="input sm:w-28" placeholder="Kode">
                                <textarea wire:model="form.cpmk.{{ $ci }}.rumusan" rows="2" class="input flex-1" placeholder="Rumusan CPMK"></textarea>
                                <input wire:model="form.cpmk.{{ $ci }}.cpl" class="input sm:w-36" placeholder="CPL1:25, CPL6:5" title="Kode CPL, opsional dengan bobot (%)">
                                <button type="button" wire:click="removeCpmk({{ $ci }})" class="text-red-600" title="Hapus">&times;</button>
                            </div>
                            @error("form.cpmk.$ci.kode")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            @error("form.cpmk.$ci.rumusan")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        @endforeach
                        <button type="button" wire:click="addCpmk" class="btn-secondary px-3 py-1.5">+ CPMK</button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label">Bahan kajian (satu per baris)</label><textarea wire:model="form.bahan_kajian" rows="4" class="input"></textarea></div>
                    <div><label class="label">Pustaka (satu per baris)</label><textarea wire:model="form.pustaka" rows="4" class="input"></textarea></div>
                </div>
                <div><label class="label">Deskripsi MK</label><textarea wire:model="form.deskripsi" rows="3" class="input"></textarea></div>
                <div class="flex flex-wrap justify-between gap-2">
                    <button type="button" wire:click="removeCourse({{ $editing }})" wire:confirm="Hapus MK ini dari data kurikulum?" class="text-sm text-red-600 hover:underline">Hapus MK</button>
                    <div class="flex gap-2"><button type="button" wire:click="$set('editing', null)" class="btn-secondary">Batal</button><button class="btn-primary">Simpan MK</button></div>
                </div>
            </form>
        </div>
    @endif
</div>
