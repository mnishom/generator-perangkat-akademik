@props(['status'])
@php
    $cls = match ($status) {
        \App\Enums\AccountStatus::Aktif => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        \App\Enums\AccountStatus::Ditangguhkan => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        \App\Enums\AccountStatus::Dinonaktifkan => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    };
@endphp
<span {{ $attributes->class(['badge', $cls]) }}>{{ $status->label() }}</span>
