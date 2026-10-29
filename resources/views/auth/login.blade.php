<x-layouts.guest :title="'Masuk'">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-marun-700 text-xl font-bold text-krem-100 shadow">HN</div>
                <h1 class="text-2xl font-semibold text-marun-800 dark:text-krem-200">Generator Perangkat Akademik</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ config('platform.university_name') }}</p>
            </div>

            <div class="card p-8">
                <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">
                    Masuk dengan akun Google kampus <strong>&#64;{{ config('platform.allowed_email_domain') }}</strong>.
                </p>
                <a href="{{ route('auth.google.redirect') }}" class="btn-primary w-full py-3">
                    <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                    Masuk dengan Google
                </a>
                <p class="mt-6 text-xs text-zinc-500">
                    Dokumen yang Anda unggah akan diproses oleh Claude API (Anthropic) untuk penyusunan dokumen.
                    <a href="{{ route('privasi') }}" class="underline hover:text-marun-700">Pelajari lebih lanjut</a>.
                </p>
            </div>
        </div>
    </div>
</x-layouts.guest>
