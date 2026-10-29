<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\GenerationItem;
use App\Models\GenerationSession;
use App\Services\Audit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * ZIP versi terbaru seluruh dokumen sesi, atau satu MK (butir).
 */
class ZipDownloadController extends Controller
{
    public function session(GenerationSession $session): BinaryFileResponse
    {
        Gate::authorize('view', $session);
        $docs = $this->latest($session->documents()->getQuery());
        $name = 'Sesi-'.$session->id.'_'.Str::slug($session->title ?: 'dokumen').'.zip';

        return $this->zip($session, $docs, $name, fn (GeneratedDocument $d) => $d->ref_code ? $d->ref_code.'/'.$d->filename : $d->filename);
    }

    public function item(GenerationSession $session, GenerationItem $item): BinaryFileResponse
    {
        Gate::authorize('view', $session);
        abort_unless($item->generation_session_id === $session->id, 404);
        $docs = $this->latest($item->documents()->getQuery());

        return $this->zip($session, $docs, Str::slug($item->ref_code.' '.$item->ref_label).'.zip', fn (GeneratedDocument $d) => $d->filename);
    }

    private function latest($query)
    {
        return $query->latestVersions()->whereNotNull('path')->get()
            ->filter(fn (GeneratedDocument $d) => Storage::disk('local')->exists($d->path));
    }

    private function zip(GenerationSession $session, $docs, string $name, callable $entry): BinaryFileResponse
    {
        abort_if($docs->isEmpty(), 404, 'Belum ada dokumen untuk diunduh.');

        if ($session->user_id !== auth()->id()) {
            Audit::log('session.zip', $session, ['owner_id' => $session->user_id, 'count' => $docs->count()]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $used = [];
        foreach ($docs as $doc) {
            $path = $entry($doc);
            if (isset($used[$path])) {
                $path = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME).'_v'.$doc->version.'.'.$doc->format;
                $path = ltrim($path, './');
            }
            $used[$path] = true;
            $zip->addFile(Storage::disk('local')->path($doc->path), $path);
        }
        $zip->close();

        return response()->download($tmp, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
