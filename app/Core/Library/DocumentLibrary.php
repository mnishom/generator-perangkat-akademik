<?php

namespace App\Core\Library;

use App\Models\GeneratedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pustaka dokumen hasil generate dari modul mana pun. Modul lain mengakses
 * dokumen (mis. RPS sebagai acuan materi) melalui layanan ini, bukan langsung
 * ke tabel milik modul lain.
 */
class DocumentLibrary
{
    /** Dokumen yang dapat dipakai pengguna: miliknya atau milik prodinya, versi berkas yang ada. */
    public function availableTo(User $user): Builder
    {
        return GeneratedDocument::query()
            ->whereNotNull('path')
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->when($user->prodi_id, fn ($q) => $q->orWhere('prodi_id', $user->prodi_id)));
    }

    /** Dokumen final (ditandai pengguna) yang dapat dipakai, opsional disaring. */
    public function finals(User $user, ?string $moduleKey = null, ?string $docType = null, ?string $refCode = null): Collection
    {
        return $this->availableTo($user)
            ->where('is_final', true)
            ->when($moduleKey, fn ($q) => $q->where('module_key', $moduleKey))
            ->when($docType, fn ($q) => $q->where('doc_type', $docType))
            ->when($refCode, fn ($q) => $q->where('ref_code', $refCode))
            ->with(['item', 'session', 'user'])
            ->latest('updated_at')
            ->get();
    }

    /** Dokumen final terbaru untuk satu jenis dan kode (mis. RPS DTI3019) di prodi pengguna. */
    public function latestFinal(User $user, string $docType, string $refCode): ?GeneratedDocument
    {
        return $this->finals($user, docType: $docType, refCode: $refCode)->first();
    }

    /**
     * Tandai satu versi sebagai final; versi lain dari dokumen yang sama
     * (sesi, butir, jenis) otomatis tidak final.
     */
    public function markFinal(GeneratedDocument $doc, bool $final = true): void
    {
        if ($final) {
            GeneratedDocument::where('generation_session_id', $doc->generation_session_id)
                ->where('doc_type', $doc->doc_type)
                ->where(fn ($q) => $doc->generation_item_id ? $q->where('generation_item_id', $doc->generation_item_id) : $q->whereNull('generation_item_id'))
                ->where('id', '!=', $doc->id)
                ->update(['is_final' => false]);
        }
        $doc->forceFill(['is_final' => $final])->save();
    }
}
