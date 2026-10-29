<div class="mx-auto max-w-6xl space-y-6">
    <div class="card overflow-hidden">
        <div class="bg-gradient-to-r from-marun-700 to-marun-900 px-6 py-8 text-krem-100">
            <p class="text-sm text-krem-300">Selamat datang,</p>
            <h2 class="text-2xl font-semibold text-white">{{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm">{{ auth()->user()->prodi?->namaLengkap() }}</p>
        </div>
        <div class="grid divide-y divide-zinc-100 text-center sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-zinc-800">
            <div class="p-4"><p class="text-2xl font-semibold">{{ $stats['generates'] }}</p><p class="text-xs text-zinc-500">generate bulan ini</p></div>
            <div class="p-4"><p class="text-2xl font-semibold">{{ $stats['documents'] }}</p><p class="text-xs text-zinc-500">dokumen tersimpan</p></div>
            <div class="p-4"><p @class(['text-2xl font-semibold', 'text-amber-600' => $stats['attention']])>{{ $stats['attention'] }}</p><p class="text-xs text-zinc-500">perlu perhatian</p></div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($modules as $m)
            <a href="{{ route('modules.show', $m->key()) }}" class="card group flex gap-4 p-5 transition hover:border-marun-300" wire:navigate>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-krem-200 text-marun-800"><x-icon :name="$m->icon()" class="h-6 w-6" /></div>
                <div><p class="font-semibold group-hover:text-marun-700 dark:group-hover:text-krem-200">{{ $m->name() }}</p><p class="mt-1 text-sm text-zinc-500">{{ $m->description() }}</p></div>
            </a>
        @endforeach
    </div>

    @if ($recent->isNotEmpty())
        <div class="card">
            <div class="flex items-center border-b border-zinc-100 px-4 py-3 dark:border-zinc-800"><h3 class="flex-1 font-semibold">Sesi terakhir</h3><a href="{{ route('history') }}" class="text-sm text-marun-700 hover:underline dark:text-krem-200" wire:navigate>Semua riwayat &rarr;</a></div>
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ($recent as $s)
                    <li class="flex items-center gap-3 px-4 py-3">
                        <a href="{{ $s->status === 'draft' ? route('modules.wizard', [$s->module_key, $s]) : route('sessions.show', $s) }}" class="flex-1 truncate hover:underline" wire:navigate>{{ $s->title ?: 'Sesi #'.$s->id }}</a>
                        <span class="text-xs text-zinc-500">{{ $s->created_at->diffForHumans() }}</span>
                        <x-session-status :status="$s->status" />
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
