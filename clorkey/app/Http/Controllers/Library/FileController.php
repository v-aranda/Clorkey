<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryFile;
use App\Models\LibraryFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:153600', // 150MB max
            'library_book_id' => 'required|exists:library_books,id',
            'library_folder_id' => 'nullable|exists:library_folders,id',
        ]);

        $bookId = $request->library_book_id;
        $folderId = $request->library_folder_id;

        foreach ($request->file('files') as $file) {
            $path = $file->store("books/{$bookId}", 's3');
            $previewPath = null;

            // Generate Preview for PDF
            if ($file->getMimeType() === 'application/pdf') {
                try {
                    $pdf = new \Spatie\PdfToImage\Pdf($file->getPathname());
                    $previewName = 'preview_' . pathinfo($file->hashName(), PATHINFO_FILENAME) . '.jpg';
                    $previewTempPath = sys_get_temp_dir() . '/' . $previewName;

                    // Save first page as image
                    $pdf->saveImage($previewTempPath);

                    // Upload to S3
                    $StoragePath = "books/{$bookId}/previews/{$previewName}";
                    Storage::disk('s3')->put($StoragePath, file_get_contents($previewTempPath));
                    $previewPath = $StoragePath;

                    // Clean up temp file
                    @unlink($previewTempPath);
                } catch (\Exception $e) {
                    // Log error but continue
                    Log::error("PDF Preview Generation Failed: " . $e->getMessage());
                }
            }

            LibraryFile::create([
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'preview_path' => $previewPath,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'library_book_id' => $bookId,
                'library_folder_id' => $folderId,
                'user_id' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Arquivos enviados com sucesso.');
    }

    public function show(LibraryFile $file)
    {
        if (!Storage::disk('s3')->exists($file->path)) {
            abort(404);
        }

        // Return a temporary URL for download/preview (valid for 5 mins)
        // Or stream directly if preferred, but presigned URLs are better for MinIO
        // return redirect(Storage::disk('s3')->temporaryUrl($file->path, now()->addMinutes(5)));

        // For local MinIO behind sail/gateway, sometimes direct stream is safer if public access isn't perfect
        return Storage::disk('s3')->download($file->path, $file->name);
    }

    public function update(Request $request, LibraryFile $file)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $file->update(['name' => $request->name]);

        return back()->with('success', 'Arquivo renomeado com sucesso.');
    }

    public function destroy(LibraryFile $file)
    {
        if (Storage::disk('s3')->exists($file->path)) {
            Storage::disk('s3')->delete($file->path);
        }

        $file->delete();

        return back()->with('success', 'Arquivo removido com sucesso.');
    }

    public function downloadZip(Request $request)
    {
        $request->validate([
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'integer|exists:library_files,id',
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'integer|exists:library_folders,id',
        ]);

        $fileIds = $request->file_ids ?? [];
        $folderIds = $request->folder_ids ?? [];

        if (empty($fileIds) && empty($folderIds)) {
            abort(422, 'Nenhum item selecionado.');
        }

        $zipFileName = 'download_' . now()->format('Y-m-d_His') . '.zip';
        $tempPath = sys_get_temp_dir() . '/' . $zipFileName;

        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível criar o arquivo ZIP.');
        }

        // Add individual files (root level in ZIP)
        if (!empty($fileIds)) {
            $files = LibraryFile::whereIn('id', $fileIds)->get();
            foreach ($files as $file) {
                if (Storage::disk('s3')->exists($file->path)) {
                    $zip->addFromString($file->name, Storage::disk('s3')->get($file->path));
                }
            }
        }

        // Add folders recursively
        if (!empty($folderIds)) {
            $folders = LibraryFolder::whereIn('id', $folderIds)->get();
            foreach ($folders as $folder) {
                $this->addFolderToZip($zip, $folder, $folder->name);
            }
        }

        $zip->close();

        return response()->download($tempPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function addFolderToZip(\ZipArchive $zip, LibraryFolder $folder, string $basePath): void
    {
        // Add empty directory entry
        $zip->addEmptyDir($basePath);

        // Add files in this folder
        foreach ($folder->files as $file) {
            if (Storage::disk('s3')->exists($file->path)) {
                $zip->addFromString($basePath . '/' . $file->name, Storage::disk('s3')->get($file->path));
            }
        }

        // Recurse into subfolders
        foreach ($folder->children as $child) {
            $this->addFolderToZip($zip, $child, $basePath . '/' . $child->name);
        }
    }

    /**
     * Move files and folders to a new destination.
     */
    public function move(Request $request)
    {
        $request->validate([
            'file_ids' => 'nullable|array',
            'file_ids.*' => 'integer|exists:library_files,id',
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'integer|exists:library_folders,id',
            'destination_book_id' => 'required|integer|exists:library_books,id',
            'destination_folder_id' => 'nullable|integer|exists:library_folders,id',
        ]);

        $fileIds = $request->file_ids ?? [];
        $folderIds = $request->folder_ids ?? [];
        $destBookId = $request->destination_book_id;
        $destFolderId = $request->destination_folder_id;

        // Move individual files
        if (!empty($fileIds)) {
            LibraryFile::whereIn('id', $fileIds)->update([
                'library_book_id' => $destBookId,
                'library_folder_id' => $destFolderId,
            ]);
        }

        // Move folders (update parent + book recursively)
        if (!empty($folderIds)) {
            foreach ($folderIds as $folderId) {
                $folder = LibraryFolder::find($folderId);
                if (!$folder)
                    continue;

                // Prevent moving a folder into itself or its descendants
                if ($destFolderId) {
                    $check = LibraryFolder::find($destFolderId);
                    while ($check) {
                        if ($check->id == $folderId) {
                            return back()->withErrors(['move' => 'Não é possível mover um ficheiro para dentro de si mesmo.']);
                        }
                        $check = $check->parent;
                    }
                }

                $folder->update([
                    'parent_id' => $destFolderId,
                    'library_book_id' => $destBookId,
                ]);

                // Recursively update book_id on children
                $this->updateFolderBookId($folder, $destBookId);
            }
        }

        return back()->with('success', 'Itens movidos com sucesso.');
    }

    /**
     * Recursively update book_id for all nested children and files.
     */
    private function updateFolderBookId(LibraryFolder $folder, int $bookId): void
    {
        // Update files in this folder
        $folder->files()->update(['library_book_id' => $bookId]);

        // Update child folders
        foreach ($folder->children as $child) {
            $child->update(['library_book_id' => $bookId]);
            $this->updateFolderBookId($child, $bookId);
        }
    }
}
