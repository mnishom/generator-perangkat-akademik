@props(['status'])
@php
    [$label, $cls] = match ($status) {
        'draft' => ['Draf', 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'],
        'queued' => ['Antre', 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'],
        'waiting' => ['Menunggu slot', 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200'],
        'running' => ['Berjalan', 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200'],
        'done', 'ok' => ['Selesai', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'],
        'attention', 'perlu_perhatian' => ['Perlu Perhatian', 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'],
        'failed', 'gagal' => ['Gagal', 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'],
        'blocked' => ['Ditolak', 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'],
        'cancelled' => ['Dibatalkan', 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'],
        default => [$status, 'bg-zinc-100 text-zinc-700'],
    };
@endphp
<span {{ $attributes->class(['badge', $cls]) }}>{{ $label }}</span>
