<?php

namespace App\Core\Validation;

use App\Models\GeneratedDocument;
use App\Models\GenerationItem;
use App\Models\GenerationSession;
use Illuminate\Support\Facades\Storage;

/**
 * Bahan pemeriksaan satu dokumen hasil.
 */
class ValidationContext
{
    private ?string $text = null;

    public function __construct(
        public readonly GeneratedDocument $document,
        public readonly GenerationSession $session,
        public readonly ?GenerationItem $item,
        public readonly ?array $report,
    ) {}

    public function absolutePath(): ?string
    {
        return $this->document->path ? Storage::disk('local')->path($this->document->path) : null;
    }

    /** Teks polos dokumen (diekstrak sekali, lalu di-cache). */
    public function text(): string
    {
        if ($this->text === null) {
            $path = $this->absolutePath();
            $this->text = $path && is_file($path) ? DocxText::extract($path) : '';
        }

        return $this->text;
    }

    /** Data masukan terverifikasi butir (mis. data MK). */
    public function itemInput(): array
    {
        return $this->item?->input ?? [];
    }

    /** Versi terbaru dokumen lain dalam butir/sesi yang sama, mis. RPS bagi Kontrak. */
    public function sibling(string $docType): ?GeneratedDocument
    {
        return GeneratedDocument::query()
            ->where('generation_session_id', $this->session->id)
            ->where('generation_item_id', $this->item?->id)
            ->where('doc_type', $docType)
            ->orderByDesc('version')
            ->first();
    }
}
