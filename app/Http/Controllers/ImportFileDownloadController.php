<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportFileDownloadController extends Controller
{
    public function __invoke(Request $request, string $filename)
    {
        $path = 'imports/' . $filename;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}