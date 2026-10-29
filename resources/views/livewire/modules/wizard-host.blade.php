<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <a href="{{ route('modules.show', $moduleDef->key()) }}" class="text-sm text-marun-700 hover:underline dark:text-krem-200" wire:navigate>&larr; {{ $moduleDef->name() }}</a>
            <h2 class="text-xl font-semibold">{{ $session->title ?: 'Sesi Generate #'.$session->id }}</h2>
        </div>
    </div>

    {{-- Stepper --}}
    <ol class="card flex gap-1 overflow-x-auto p-2 text-sm">
        @foreach ($steps as $key => $step)
            @php($i = $loop->index)
            <li class="min-w-fit flex-1">
                <button type="button"
                    wire:click="goTo('{{ $key }}')"
                    @disabled($i > $maxIndex)
                    @class([
                        'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left transition',
                        'bg-marun-700 text-white' => $i === $currentIndex,
                        'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => $i !== $currentIndex && $i <= $maxIndex,
                        'cursor-not-allowed text-zinc-400' => $i > $maxIndex,
                    ])>
                    <span @class([
                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-white text-marun-700' => $i === $currentIndex,
                        'bg-krem-200 text-marun-800' => $i < $currentIndex || ($i !== $currentIndex && $i <= $maxIndex),
                        'bg-zinc-200 text-zinc-500 dark:bg-zinc-700' => $i > $maxIndex,
                    ])>{{ $i < $currentIndex ? '✓' : $i + 1 }}</span>
                    <span class="whitespace-nowrap">{{ $step['label'] }}</span>
                </button>
            </li>
        @endforeach
    </ol>

    <div>
        @livewire($steps[$current]['component'], ['sessionId' => $session->id], key($current.'-'.$session->id))
    </div>
</div>
