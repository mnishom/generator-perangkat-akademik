<div class="space-y-6">
    @forelse ($defs as $key => $def)
        <div class="card grid gap-0 lg:grid-cols-3" wire:key="prompt-{{ $key }}">
            <div class="space-y-3 p-6 lg:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold">{{ $def['label'] }}</h3>
                    @if ($isDefault[$key])<span class="badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800">Default</span>@else<span class="badge bg-krem-200 text-marun-800">Disunting</span>@endif
                </div>
                @if ($def['description'] ?? null)<p class="text-sm text-zinc-500">{{ $def['description'] }}</p>@endif

                @if ($analysis[$key]['missing'])
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                        ⚠ Prompt tidak lagi memuat variabel penting:
                        @foreach ($analysis[$key]['missing'] as $v)<x-var :name="$v" class="mx-0.5" />@endforeach.
                        Tanpa variabel ini, Claude dapat memakai data MK yang salah.
                    </div>
                @endif
                @if ($analysis[$key]['unknown'])
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                        Variabel tidak dikenal (tidak akan diganti):
                        @foreach ($analysis[$key]['unknown'] as $v)<x-var :name="$v" class="mx-0.5" />@endforeach
                    </div>
                @endif

                <textarea wire:model.live.debounce.600ms="bodies.{{ $key }}" rows="16" class="input font-mono text-xs leading-relaxed"></textarea>
                @error('bodies.'.$key)<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="flex flex-wrap gap-2">
                    <button wire:click="savePersonal('{{ $key }}')" class="btn-secondary px-3 py-1.5">Simpan sebagai prompt pribadi</button>
                    @if ($hasPersonal[$key])<button wire:click="loadPersonal('{{ $key }}')" class="btn-secondary px-3 py-1.5">Muat prompt pribadi</button>@endif
                    <button wire:click="resetDefault('{{ $key }}')" wire:confirm="Kembalikan prompt ini ke default?" class="btn-secondary px-3 py-1.5">Kembalikan ke default</button>
                </div>
            </div>
            <aside class="border-t border-zinc-100 bg-zinc-50/60 p-6 text-sm dark:border-zinc-800 dark:bg-zinc-800/30 lg:border-l lg:border-t-0">
                <h4 class="font-semibold">Variabel otomatis</h4>
                <p class="mb-3 text-xs text-zinc-500">Diganti otomatis saat generate. <span class="text-red-600">*</span> = penting.</p>
                <ul class="space-y-2">
                    @foreach ($def['variables'] as $name => $v)
                        <li>
                            <x-var :name="$name" />@if ($v['required'] ?? false)<span class="text-red-600">*</span>@endif
                            <p class="text-xs text-zinc-500">{{ $v['description'] }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-zinc-500">Aplikasi juga menambahkan instruksi sistem tetap (peran berkas, akurasi data, nama berkas, dan laporan pemeriksaan) yang tidak dapat diedit.</p>
            </aside>
        </div>
    @empty
        <div class="card p-6 text-sm text-zinc-500">Tidak ada prompt yang perlu diatur untuk pilihan dokumen saat ini.</div>
    @endforelse

    <div class="flex justify-between">
        <button wire:click="back" class="btn-secondary">&larr; Kembali</button>
        <button wire:click="continue" class="btn-primary">Lanjut &rarr;</button>
    </div>
</div>
