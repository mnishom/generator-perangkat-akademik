<?php

return [

    'api_key' => env('ANTHROPIC_API_KEY'),

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),

    'version' => env('ANTHROPIC_VERSION', '2023-06-01'),

    /*
    | Model default. Wajib termasuk dalam `models` (kompatibel dengan code execution).
    */
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),

    /*
    | Model yang dapat dipilih admin (kompatibel dengan code execution tool),
    | beserta harga per juta token (USD) untuk estimasi biaya.
    */
    'models' => [
        'claude-sonnet-5-5' => ['label' => 'Claude Sonnet 5.5', 'input' => 2.00, 'output' => 10.00],
        'claude-sonnet-5' => ['label' => 'Claude Sonnet 5', 'input' => 2.00, 'output' => 10.00],
        'claude-opus-5-5' => ['label' => 'Claude Opus 5.5 (kualitas tertinggi)', 'input' => 4.00, 'output' => 20.00],
    ],

    'code_execution_tool' => env('ANTHROPIC_CODE_EXECUTION_TOOL', 'code_execution_20260521'),

    /*
    | Beta header yang dikirim pada panggilan generate dengan Agent Skills.
    | Files API dan Skills sudah GA, jadi cukup header code execution (lihat docs/DECISIONS.md D-004).
    */
    'betas' => array_values(array_filter(explode(',', (string) env('ANTHROPIC_BETAS', 'code-execution-2025-08-25')))),

    'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 32000),

    'effort' => env('ANTHROPIC_EFFORT', 'high'),

    /*
    | Prompt caching otomatis (cache_control tingkat atas). Menghemat biaya saat
    | percakapan dilanjutkan di container yang sama (RPS → Kontrak, Silabus bertahap).
    */
    'auto_cache' => (bool) env('ANTHROPIC_AUTO_CACHE', true),

    /*
    | Fallback sisi server bila model menolak (refusal). "default" = rute otomatis.
    | Kosongkan (ANTHROPIC_FALLBACKS=) untuk menonaktifkan.
    */
    'fallbacks' => env('ANTHROPIC_FALLBACKS', 'default') ?: null,
    'fallbacks_beta' => env('ANTHROPIC_FALLBACKS_BETA', 'server-side-fallback-2026-07-01'),

    /* Batas pelanjutan `pause_turn` per panggilan generate. */
    'max_continuations' => (int) env('ANTHROPIC_MAX_CONTINUATIONS', 8),

    /* Timeout HTTP (detik) untuk satu permintaan. */
    'timeout' => (int) env('ANTHROPIC_TIMEOUT', 600),

];
