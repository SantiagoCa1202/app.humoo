<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentStorage
{
    public function storeUploadedFile(
        UploadedFile $file,
        string $workspaceId
    ): array {
        $disk = (string) config('filesystems.default', 'local');
        abort_if($disk === 'public', 500, 'BEO documents cannot use public storage.');
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $path = sprintf(
            'workspaces/%s/documents/%s/%s%s',
            $workspaceId,
            now()->format('Y/m'),
            Str::ulid(),
            $extension ? ".{$extension}" : ''
        );

        $stored = Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if ($stored === false) {
            throw new RuntimeException('The uploaded document could not be persisted.');
        }

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

    public function delete(Document $document): void
    {
        Storage::disk($document->disk)->delete($document->path);
    }

    public function deleteStored(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
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
