<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Models\PdfProcessing;

class DocumentController extends Controller
{
    /**
     * Download a converted document
     */
    public function download($id)
    {
        // Find the conversion record
        $conversion = PdfProcessing::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->first();

        if (!$conversion) {
            abort(404, 'File not found or not accessible');
        }

        // Check if the converted file exists
        $filePath = $conversion->supabase_output_path;
        
        if (!$filePath || !Storage::exists($filePath)) {
            abort(404, 'Converted file not found');
        }

        // Get the file content and info
        $fileContent = Storage::get($filePath);
        $fileName = basename($filePath);
        
        // Determine the MIME type based on file extension
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ppt' => 'application/vnd.ms-powerpoint',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Create a more user-friendly filename
        $originalName = pathinfo($conversion->original_filename, PATHINFO_FILENAME);
        $downloadName = $originalName . '_converted.' . $extension;

        return Response::make($fileContent, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Length' => strlen($fileContent),
        ]);
    }
}
