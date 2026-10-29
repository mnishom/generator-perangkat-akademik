@php
    $link = fn (bool $active) => 'flex items-center gap-3 rounded-lg px-3 py-2 transition '.($active ? 'bg-marun-900 text-white' : 'text-krem-100 hover:bg-marun-700');
@endphp
<div class="space-y-1">
    <a href="{{ route('dashboard') }}" class="{{ $link(request()->routeIs('dashboard')) }}" wire:navigate>Beranda</a>
</div>
