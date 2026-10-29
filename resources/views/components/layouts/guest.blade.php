<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-krem-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @include('partials.toasts')
    {{ $slot }}
    @livewireScripts
</body>
</html>
