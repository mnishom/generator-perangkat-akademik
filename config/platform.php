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

    'university_name' => env('UNIVERSITY_NAME', 'Universitas Harkat Negeri'),

];
