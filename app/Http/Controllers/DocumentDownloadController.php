<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Services\Audit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(GeneratedDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);
        abort_unless($document->path && Storage::disk('local')->exists($document->path), 404);

        if ($document->user_id !== auth()->id()) {
            Audit::log('document.download', $document, ['owner_id' => $document->user_id]);
        }

        return Storage::disk('local')->download($document->path, $document->filename);
    }
}
