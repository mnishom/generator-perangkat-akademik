<div class="space-y-6">
    <div class="card overflow-hidden">
        <div class="bg-gradient-to-r from-marun-700 to-marun-900 px-6 py-8 text-krem-100">
            <p class="text-sm text-krem-300">Selamat datang,</p>
            <h2 class="text-2xl font-semibold text-white">{{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm">{{ auth()->user()->prodi?->namaLengkap() }}</p>
        </div>
    </div>
</div>
