@php
    $link = fn (bool $active) => 'flex items-center gap-3 rounded-lg px-3 py-2 transition '.($active ? 'bg-marun-900 text-white' : 'text-krem-100 hover:bg-marun-700');
    $navModules = auth()->check() ? app(\App\Core\Modules\ModuleAccess::class)->accessibleModules(auth()->user()) : [];
    $currentModule = request()->route('module');
@endphp
<div class="space-y-1">
    <a href="{{ route('dashboard') }}" class="{{ $link(request()->routeIs('dashboard')) }}" wire:navigate><x-icon name="home" /> Beranda</a>
    <a href="{{ route('history') }}" class="{{ $link(request()->routeIs('history', 'sessions.*')) }}" wire:navigate><x-icon name="clock" /> Riwayat</a>
</div>

@if ($navModules)
    <div class="space-y-1">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-krem-300/70">Modul</p>
        @foreach ($navModules as $m)
            <a href="{{ route('modules.show', $m->key()) }}" class="{{ $link($currentModule === $m->key()) }}" wire:navigate data-module="{{ $m->key() }}"><x-icon :name="$m->icon()" /> {{ $m->name() }}</a>
        @endforeach
    </div>
@endif

<div class="space-y-1">
    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-krem-300/70">Pustaka</p>
    <a href="{{ route('library.files') }}" class="{{ $link(request()->routeIs('library.files')) }}" wire:navigate><x-icon name="folder" /> Pustaka Berkas</a>
    <a href="{{ route('library.documents') }}" class="{{ $link(request()->routeIs('library.documents')) }}" wire:navigate><x-icon name="book" /> Pustaka Dokumen</a>
</div>

@if (auth()->user()?->isAdmin())
    <div class="space-y-1">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-krem-300/70">Admin</p>
        <a href="{{ route('admin.users') }}" class="{{ $link(request()->routeIs('admin.users')) }}" wire:navigate>Pengguna</a>
        <a href="{{ route('admin.prodis') }}" class="{{ $link(request()->routeIs('admin.prodis')) }}" wire:navigate>Prodi &amp; Fakultas</a>
        <a href="{{ route('admin.modules') }}" class="{{ $link(request()->routeIs('admin.modules')) }}" wire:navigate>Modul</a>
        <a href="{{ route('admin.settings') }}" class="{{ $link(request()->routeIs('admin.settings')) }}" wire:navigate>Pengaturan</a>
        <a href="{{ route('admin.audit') }}" class="{{ $link(request()->routeIs('admin.audit')) }}" wire:navigate>Audit Log</a>
    </div>
@endif
