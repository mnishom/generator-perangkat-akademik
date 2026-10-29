@props(['doc', 'label' => null])
{{-- Satu dokumen hasil: status, unduh, dan laporan pemeriksaan. --}}
<div {{ $attributes->class('rounded-lg border border-zinc-200 p-3 dark:border-zinc-800') }}>
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-medium">{{ $label ?? $doc->doc_type }}</span>
        <span class="text-xs text-zinc-500">v{{ $doc->version }}</span>
        <x-session-status :status="$doc->status" />
        @if ($doc->is_final)<span class="badge bg-marun-700 text-white">Final</span>@endif
        @if ($doc->path)
            <a href="{{ route('documents.download', $doc) }}" class="ml-auto text-sm font-medium text-marun-700 hover:underline dark:text-krem-200">⬇ {{ $doc->filename }}</a>
        @endif
    </div>
    @if ($doc->instruction)<p class="mt-1 text-xs text-zinc-500">Instruksi: {{ $doc->instruction }}</p>@endif
    @if ($doc->checks)
        <details class="mt-2 text-sm" @if ($doc->status !== 'ok') open @endif>
            <summary class="cursor-pointer text-xs text-zinc-500">Laporan pemeriksaan ({{ count($doc->checks) - count($doc->failedChecks()) }}/{{ count($doc->checks) }} lolos)</summary>
            <ul class="mt-2 space-y-1">
                @foreach ($doc->checks as $c)
                    <li class="flex gap-2 text-xs">
                        <span @class(['text-emerald-600' => $c['passed'], 'text-red-600' => ! $c['passed']])>{{ $c['passed'] ? '✓' : '✗' }}</span>
                        <span><strong>{{ $c['label'] }}</strong>@if ($c['message']) — {{ $c['message'] }}@endif <span class="text-zinc-400">({{ $c['source'] ?? 'aplikasi' }})</span></span>
                    </li>
                @endforeach
            </ul>
            @if ($doc->report)
                <details class="mt-2"><summary class="cursor-pointer text-xs text-zinc-500">Laporan JSON dari Claude</summary><pre class="mt-1 max-h-60 overflow-auto rounded bg-zinc-50 p-2 text-xs dark:bg-zinc-800">{{ json_encode($doc->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
            @endif
        </details>
    @endif
    {{ $slot }}
</div>
