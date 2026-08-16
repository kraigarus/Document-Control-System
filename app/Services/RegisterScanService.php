<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\PdfToImage\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;

class RegisterScanService
{
    public static function extract(Request $request)
    {
        $request->validate([
            'scan' => 'required|file|mimes:pdf|max:10240',
            'section' => 'required|string|in:drf',
        ]);

        $file = $request->file('scan');
        $tempPath = $file->store('temp/scans', 'local');
        $fullPath = Storage::disk('local')->path($tempPath);
        $imagePath = Storage::disk('local')->path('temp/scans/' . uniqid() . '.jpg');

        try {
            (new Pdf($fullPath))->selectPage(1)->save($imagePath);
            $rawText = (new TesseractOCR($imagePath))->lang('eng')->run();

            return [
                'extracted' => true,
                'fields' => self::parseDrfFields($rawText),
                'raw_text_preview' => Str::limit($rawText, 500),
            ];
        } catch (\Throwable $e) {
            Log::warning('OCR extraction failed: ' . $e->getMessage());

            return ['extracted' => false, 'reason' => 'ocr_failed'];
        } finally {
            Storage::disk('local')->delete($tempPath);
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
    }

    private static function parseDrfFields(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $get = function (string $label) use ($lines) {
            foreach ($lines as $line) {
                if (stripos($line, $label) !== false) {
                    $parts = preg_split('/' . preg_quote($label, '/') . '[.:\s]*/i', $line, 2);
                    if (isset($parts[1]) && trim($parts[1]) !== '') {
                        return trim($parts[1]);
                    }
                }
            }

            return null;
        };

        return [
            'drfNo' => $get('DRF No'),
            'drfDate' => $get('DRF Date'),
            'drfTitle' => $get('Document Title'),
        ];
    }
}
