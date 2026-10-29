<x-layouts.guest :title="'Informasi Pemrosesan Data'">
    <div class="mx-auto max-w-2xl p-6 py-12">
        <a href="{{ url('/') }}" class="text-sm text-marun-700 hover:underline">&larr; Kembali</a>
        <h1 class="mt-4 text-2xl font-semibold text-marun-800 dark:text-krem-200">Informasi Pemrosesan Data</h1>
        <div class="card mt-6 space-y-4 p-6 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
            <p>Aplikasi ini membantu Program Studi di {{ config('platform.university_name') }} menyusun perangkat akademik (misalnya RPS, Kontrak Perkuliahan, dan Silabus) dengan bantuan <strong>Claude API</strong> dari Anthropic.</p>
            <p>Saat Anda menjalankan ekstraksi atau generate, berkas yang Anda unggah (dokumen kurikulum, contoh dokumen, dan template) beserta data sesi dikirim ke Claude API <strong>hanya untuk keperluan penyusunan dokumen</strong>. Berkas asli dan hasil generate disimpan di server aplikasi pada penyimpanan privat dan hanya dapat diunduh oleh pemiliknya (serta admin, dengan pencatatan audit).</p>
            <p>Salinan berkas di Anthropic Files API dapat dihapus otomatis setelah proses selesai sesuai pengaturan admin. Lingkungan eksekusi (container) di Anthropic kedaluwarsa paling lama 30 hari.</p>
            <p>Jangan mengunggah data pribadi yang tidak diperlukan, seperti data mahasiswa. Dokumen hasil AI <strong>wajib ditinjau</strong> dosen dan prodi sebelum disahkan, terutama pustaka yang ditandai "perlu verifikasi".</p>
        </div>
    </div>
</x-layouts.guest>
