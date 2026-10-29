<?php

namespace App\Core\Modules;

/**
 * Berkas atau data yang dibutuhkan modul.
 */
final class InputRequirement
{
    public const SOURCE_UPLOAD = 'upload';            // unggah / pustaka berkas

    public const SOURCE_DOCUMENT_LIBRARY = 'document'; // dokumen hasil modul lain

    public const SOURCE_CURRICULUM = 'curriculum';     // data kurikulum terverifikasi

    /**
     * @param  string[]  $mimes  ekstensi yang diizinkan, mis. ['docx', 'pdf']
     */
    public function __construct(
        public readonly string $role,
        public readonly string $label,
        public readonly bool $required = true,
        public readonly array $mimes = ['docx', 'pdf'],
        public readonly string $source = self::SOURCE_UPLOAD,
        public readonly string $help = '',
    ) {}
}
