<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DocumentStorage
{
    public function storeUploadedFile(
        UploadedFile $file,
        string $workspaceId
    ): array {
        $disk = (string) config('filesystems.default', 'local');
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $path = sprintf(
            'workspaces/%s/documents/%s/%s%s',
            $workspaceId,
            now()->format('Y/m'),
            Str::ulid(),
            $extension ? ".{$extension}" : ''
        );

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return [
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'disk' => $disk,
            'extension' => $extension ?: null,
            'mime_type' => $file->getMimeType(),
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
        ];
    }

    public function temporaryDownloadUrl(Document $document): string
    {
        return URL::temporarySignedRoute(
            'api.documents.download',
            now()->addMinutes(15),
            ['document' => $document->getKey()]
        );
    }
}
