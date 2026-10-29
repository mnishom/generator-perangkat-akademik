@php
    $link = fn (bool $active) => 'flex items-center gap-3 rounded-lg px-3 py-2 transition '.($active ? 'bg-marun-900 text-white' : 'text-krem-100 hover:bg-marun-700');
@endphp
<div class="space-y-1">
    <a href="{{ route('dashboard') }}" class="{{ $link(request()->routeIs('dashboard')) }}" wire:navigate>Beranda</a>
</div>

@if (auth()->user()?->isAdmin())
    <div class="space-y-1">
        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-krem-300/70">Admin</p>
        <a href="{{ route('admin.users') }}" class="{{ $link(request()->routeIs('admin.users')) }}" wire:navigate>Pengguna</a>
        <a href="{{ route('admin.prodis') }}" class="{{ $link(request()->routeIs('admin.prodis')) }}" wire:navigate>Prodi &amp; Fakultas</a>
        <a href="{{ route('admin.settings') }}" class="{{ $link(request()->routeIs('admin.settings')) }}" wire:navigate>Pengaturan</a>
        <a href="{{ route('admin.audit') }}" class="{{ $link(request()->routeIs('admin.audit')) }}" wire:navigate>Audit Log</a>
    </div>
@endif
