<?php

namespace App\Core\Validation;

use ZipArchive;

/**
 * Ekstraksi teks polos dari DOCX langsung dari XML-nya (badan dokumen, header,
 * footer, tabel, kotak teks). Lebih toleran dibanding pembaca PhpWord untuk
 * dokumen dengan elemen yang tidak didukung PhpWord.
 */
class DocxText
{
    public static function extract(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/(document|header\d*|footer\d*|footnotes|endnotes)\.xml$#', $name)) {
                $parts[$name] = $zip->getFromIndex($i);
            }
        }
        $zip->close();

        // Badan dokumen dulu agar urutan teks wajar.
        uksort($parts, fn ($a, $b) => ($a === 'word/document.xml' ? -1 : 0) <=> ($b === 'word/document.xml' ? -1 : 0));

        return trim(implode("\n", array_map([self::class, 'xmlToText'], $parts)));
    }

    public static function xmlToText(string $xml): string
    {
        $xml = preg_replace('#<w:tab[^>]*/>#', "\t", $xml);
        $xml = preg_replace('#<w:(br|cr)[^>]*/>#', ' ', $xml);

        // Satu baris tabel = satu baris teks (sel dipisah tab), agar label dan isinya tetap bersama.
        $xml = preg_replace_callback('#<w:tr[ >].*?</w:tr>#s', function ($m) {
            $row = preg_replace('#</w:p>#', ' ', $m[0]);
            $row = preg_replace('#</w:tc>#', "\t", $row);
            $row = html_entity_decode(strip_tags($row), ENT_QUOTES | ENT_XML1, 'UTF-8');

            return '<w:p>'.htmlspecialchars(trim(preg_replace('/ {2,}/', ' ', $row)), ENT_XML1).'</w:p>';
        }, $xml);

        $xml = preg_replace('#</w:p>#', "\n", $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text);

        return preg_replace("/\n{3,}/", "\n\n", $text);
    }

    /** Normalisasi untuk pencocokan longgar (spasi, huruf besar-kecil, tanda hubung). */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{00A0}"], ['-', '-', '-', '-', '-', ' '], $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }
}
