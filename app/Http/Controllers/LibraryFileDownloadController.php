<?php

namespace App\Http\Controllers;

use App\Models\LibraryFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryFileDownloadController extends Controller
{
    public function __invoke(LibraryFile $file): StreamedResponse
    {
        Gate::authorize('view', $file);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
