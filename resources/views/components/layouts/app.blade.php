@php($user = auth()->user())
<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100" x-data="{ nav: false }">
    @include('partials.toasts')

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform bg-marun-800 text-krem-100 transition lg:static lg:translate-x-0"
            :class="nav && 'translate-x-0'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-marun-700 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-krem-200 font-bold text-marun-800">HN</div>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">Perangkat Akademik</p>
                    <p class="text-xs text-krem-300">{{ config('platform.university_name') }}</p>
                </div>
            </div>
            <nav class="space-y-6 px-3 py-5 text-sm">
                @include('partials.nav')
            </nav>
        </aside>
        <div x-show="nav" x-cloak class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="nav = false"></div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-zinc-200 bg-white/90 px-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/90 sm:px-6">
                <button type="button" class="rounded-md p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden dark:text-zinc-300 dark:hover:bg-zinc-800" @click="nav = !nav" aria-label="Menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="truncate text-base font-semibold">{{ $title ?? '' }}</h1>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" onclick="toggleTheme()" class="rounded-md p-2 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" title="Mode gelap/terang">
                        <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                        <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32l1.41-1.41"/></svg>
                    </button>
                    @if ($user)
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-full p-1 pr-3 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                @if ($user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="" class="h-8 w-8 rounded-full" referrerpolicy="no-referrer">
                                @else
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-marun-100 text-sm font-semibold text-marun-800">{{ mb_substr($user->name, 0, 1) }}</span>
                                @endif
                                <span class="hidden text-sm sm:block">{{ $user->name }}</span>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false" class="card absolute right-0 mt-2 w-64 p-2 text-sm">
                                <div class="border-b border-zinc-100 px-3 pb-2 dark:border-zinc-800">
                                    <p class="font-medium">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $user->prodi?->namaLengkap() ?? 'Belum memilih prodi' }} · {{ $user->role->label() }}</p>
                                </div>
                                @unless ($user->prodi_locked)
                                    <a href="{{ route('prodi.select') }}" class="block rounded-md px-3 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">Ganti Program Studi</a>
                                @endunless
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="block w-full rounded-md px-3 py-2 text-left text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">Keluar</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            @if ($user && ! $user->canGenerate())
                <div class="border-b border-amber-200 bg-amber-50 px-6 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    Akun Anda sedang <strong>ditangguhkan</strong>{{ $user->status_reason ? ': '.$user->status_reason : '' }}. Anda masih dapat melihat dan mengunduh riwayat, tetapi tidak dapat menjalankan generate.
                </div>
            @endif

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>

            <footer class="border-t border-zinc-200 px-6 py-4 text-xs text-zinc-500 dark:border-zinc-800">
                Dokumen hasil AI wajib ditinjau dosen dan prodi sebelum disahkan. ·
                <a href="{{ route('privasi') }}" class="underline hover:text-marun-700">Informasi pemrosesan data</a>
            </footer>
        </div>
    </div>

    @livewireScripts
</body>
</html>
