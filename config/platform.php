<?php

return [

    /*
    | Domain email kampus yang diizinkan login. Diperiksa dua kali: klaim `hd`
    | dari Google dan akhiran alamat email di sisi server.
    */
    'allowed_email_domain' => env('ALLOWED_EMAIL_DOMAIN', 'harkatnegeri.ac.id'),

    /*
    | Email yang otomatis menjadi admin saat login pertama (dipisah koma).
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

    /*
    | Login cepat tanpa Google (/dev/login/{id}), hanya saat APP_ENV=local.
    */
    'dev_login' => (bool) env('DEV_LOGIN', false),

    /* Batas aksi generate per pengguna per menit (mulai generate, uji coba, generate ulang, ekstraksi). */
    'generate_rate_per_minute' => (int) env('GENERATE_RATE_PER_MINUTE', 10),

    'university_name' => env('UNIVERSITY_NAME', 'Universitas Harkat Negeri'),

];
