<div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-100 pt-2 text-xs dark:border-zinc-800">
    <span class="text-zinc-400">{{ $doc->model }} · {{ number_format($doc->input_tokens + $doc->output_tokens, 0, ',', '.') }} token · {{ $doc->created_at->translatedFormat('d M H:i') }}</span>
    @if ($doc->version > 1)
        <button type="button" wire:click="showHistory({{ $doc->id }})" class="text-zinc-600 hover:underline dark:text-zinc-300">Riwayat ({{ $doc->version }} versi)</button>
    @endif
    @unless ($readOnly)
        @if ($doc->path)
            <button type="button" wire:click="toggleFinal({{ $doc->id }})" class="text-zinc-600 hover:underline dark:text-zinc-300">{{ $doc->is_final ? 'Batalkan final' : 'Tandai final' }}</button>
        @endif
        @if ($autofix)
            <button type="button" wire:click="autofix({{ $doc->id }})" wire:confirm="Generate ulang dokumen ini untuk memperbaiki masalah yang ditemukan?" class="font-medium text-amber-700 hover:underline dark:text-amber-300">⚙ Perbaiki otomatis ({{ $doc->autofix_count }}/{{ app(\App\Services\Settings::class)->get('autofix_max') }})</button>
        @endif
        <button type="button" wire:click="openRegenerate({{ $doc->id }})" class="text-marun-700 hover:underline dark:text-krem-200">↻ Generate ulang</button>
    @endunless
</div>
