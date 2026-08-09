<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentStamp;
use App\Models\DocType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class StampingController extends Controller
{
    private const STAMPS = [
        'controlled' => [
            'label'     => 'Controlled',
            'title'     => 'CONTROLLED',
            'subtitle'  => 'Controlled Copy',
            'color'     => [0, 51, 153],
            'width'     => 55,
            'height'    => 22,
            'titleSize' => 12,
            'subSize'   => 7,
        ],
        'obsolete' => [
            'label'     => 'Obsolete',
            'title'     => 'OBSOLETE',
            'subtitle'  => 'Superseded Document',
            'color'     => [180, 0, 0],
            'width'     => 55,
            'height'    => 22,
            'titleSize' => 12,
            'subSize'   => 7,
        ],
        'master_copy' => [
            'label'     => 'Master Copy',
            'title'     => 'MASTER COPY',
            'subtitle'  => 'Official Master Copy',
            'color'     => [0, 100, 0],
            'width'     => 55,
            'height'    => 22,
            'titleSize' => 12,
            'subSize'   => 7,
        ],
        'reference' => [
            'label'     => 'Reference',
            'title'     => 'REFERENCE COPY',
            'subtitle'  => 'For Reference Only',
            'color'     => [100, 100, 100],
            'width'     => 55,
            'height'    => 22,
            'titleSize' => 11,
            'subSize'   => 7,
        ],
        'certified_true_copy' => [
            'label'     => 'Certified True Copy',
            'title'     => 'CERTIFIED TRUE COPY',
            'color'     => [0, 51, 153],
            'width'     => 65,
            'height'    => 30,
            'titleSize' => 10,
        ],
    ];

    // ──────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────

    public function index()
    {
        $documents = DocumentRequest::with([
            'masterlistRegistration',
            'documentRequestForm',
            'documentChangeNotice',
            'documentDistribution',
            'documentRetrieval',
            'docType',
            'stamps',
        ])
        ->where(function ($q) {
            $q->whereHas('masterlistRegistration', fn ($q2) =>
                $q2->whereNotNull('scanned_masterlist')->where('scanned_masterlist', '!=', ''))
              ->orWhereHas('documentRequestForm', fn ($q2) =>
                $q2->whereNotNull('scanned_drf')->where('scanned_drf', '!=', ''))
              ->orWhereHas('documentChangeNotice', fn ($q2) =>
                $q2->whereNotNull('scanned_dcn')->where('scanned_dcn', '!=', ''))
              ->orWhereHas('documentDistribution', fn ($q2) =>
                $q2->whereNotNull('scanned_distribution')->where('scanned_distribution', '!=', ''))
              ->orWhereHas('documentRetrieval', fn ($q2) =>
                $q2->whereNotNull('scanned_retrieval')->where('scanned_retrieval', '!=', ''));
        })
        ->orderBy('id', 'desc')
        ->paginate(15);

        $docTypes = DocType::whereNull('parent_id')->orderBy('doc_type_name')->get();

        return view('pages.dcs.stamping.index', compact('documents', 'docTypes'));
    }

    // ──────────────────────────────────────────
    // SHARED VALIDATION
    // ──────────────────────────────────────────

    private function validateStampPayload(Request $request): array
    {
        $validated = $request->validate([
            'file_path'    => 'required|string|max:500',
            'file_key'     => 'required|string|in:masterlist,drf,dcn,distribution,retrieval',
            'request_id'   => 'required|integer|exists:dcs_document_requests,id',
            'doc_no'       => 'nullable|string|max:100',
            'doc_title'    => 'nullable|string|max:500',
            'rev'          => 'nullable|string|max:20',
            'stamp_type'   => 'required|string|in:' . implode(',', array_keys(self::STAMPS)),
            'position'     => 'required|string|in:top-left,top-right,bottom-left,bottom-right,center,auto',
            'all_pages'    => 'required|boolean',
            'certified_by' => 'nullable|string|max:255',
            'designation'  => 'nullable|string|max:255',
        ]);

        if ($validated['stamp_type'] === 'certified_true_copy') {
            if (empty(trim($validated['certified_by'] ?? ''))) {
                abort(422, 'Certified By is required for Certified True Copy.');
            }
            if (empty(trim($validated['designation'] ?? ''))) {
                abort(422, 'Designation is required for Certified True Copy.');
            }
        }

        $path = ltrim($validated['file_path'], '/');
        $path = str_replace(['../', '..\\'], '', $path);
        if (str_contains($path, '..')) {
            abort(422, 'Invalid file path.');
        }
        $validated['file_path'] = $path;

        return $validated;
    }

    /**
     * Find the emptiest spot on a page for the stamp using pixel-density analysis.
     * Requires ext-imagick + Ghostscript. Falls back to bottom-right if unavailable
     * or if the page is too dense to find a clean spot.
     */
    private function findEmptyArea(string $pdfPath, int $pageNum, float $pageWmm, float $pageHmm, float $stampWmm, float $stampHmm): array
    {
        $fallback = ['x' => $pageWmm - $stampWmm - 15, 'y' => $pageHmm - $stampHmm - 15];

        if (!class_exists(\Imagick::class)) {
            Log::warning('Stamp: Imagick unavailable, using fallback for auto-placement');
            return $fallback;
        }

        try {
            $dpi = 100;
            $img = new \Imagick();
            $img->setResolution($dpi, $dpi);
            $img->readImage($pdfPath . '[' . ($pageNum - 1) . ']');
            $img->setImageColorspace(\Imagick::COLORSPACE_GRAY);

            $imgW = $img->getImageWidth();
            $imgH = $img->getImageHeight();
            $pxPerMm = $dpi / 25.4;

            $pixels = $img->exportImagePixels(0, 0, $imgW, $imgH, 'I', \Imagick::PIXEL_CHAR);
            $img->clear();

            // ── Two integral images: one for sum (mean/variance), one for "non-white" count ──
            // CHANGED: threshold raised from 200 -> 250. Catches faint gray watermarks,
            // not just solid black text.
            $NONWHITE_THRESHOLD = 250;

            $sumTable    = array_fill(0, $imgH + 1, null);
            $sumSqTable  = array_fill(0, $imgH + 1, null); // NEW: for variance
            $inkTable    = array_fill(0, $imgH + 1, null);
            $sumTable[0]   = array_fill(0, $imgW + 1, 0);
            $sumSqTable[0] = array_fill(0, $imgW + 1, 0);
            $inkTable[0]   = array_fill(0, $imgW + 1, 0);

            for ($y = 0; $y < $imgH; $y++) {
                $sumTable[$y + 1]   = [0];
                $sumSqTable[$y + 1] = [0];
                $inkTable[$y + 1]   = [0];
                for ($x = 0; $x < $imgW; $x++) {
                    $v = $pixels[$y * $imgW + $x];
                    $ink = ($v < $NONWHITE_THRESHOLD) ? 1 : 0;
                    $sumTable[$y + 1][$x + 1]   = $v       + $sumTable[$y][$x + 1]   + $sumTable[$y + 1][$x]   - $sumTable[$y][$x];
                    $sumSqTable[$y + 1][$x + 1] = ($v * $v) + $sumSqTable[$y][$x + 1] + $sumSqTable[$y + 1][$x] - $sumSqTable[$y][$x];
                    $inkTable[$y + 1][$x + 1]   = $ink      + $inkTable[$y][$x + 1]   + $inkTable[$y + 1][$x]   - $inkTable[$y][$x];
                }
            }

            $regionSum = function (array $table, int $x, int $y, int $w, int $h) {
                return $table[$y + $h][$x + $w] - $table[$y][$x + $w] - $table[$y + $h][$x] + $table[$y][$x];
            };

            $stampWpx = (int) round($stampWmm * $pxPerMm);
            $stampHpx = (int) round($stampHmm * $pxPerMm);
            $marginPx = (int) round(12 * $pxPerMm);
            $step     = max(4, (int) round(3 * $pxPerMm));
            $totalPx  = $stampWpx * $stampHpx;

            // NEW: a region is only "clean" if BOTH conditions hold:
            //  1. near-zero non-white pixels (catches solid text/lines)
            //  2. low variance (catches faint/light watermark text that doesn't
            //     individually cross the brightness threshold but still isn't flat white)
            $isClean = function (int $x, int $y) use ($regionSum, $sumTable, $sumSqTable, $inkTable, $stampWpx, $stampHpx, $totalPx) {
                $inkCount = $regionSum($inkTable, $x, $y, $stampWpx, $stampHpx);
                if ($inkCount > $totalPx * 0.003) return [false, $inkCount, null];

                $sum   = $regionSum($sumTable, $x, $y, $stampWpx, $stampHpx);
                $sumSq = $regionSum($sumSqTable, $x, $y, $stampWpx, $stampHpx);
                $mean  = $sum / $totalPx;
                $variance = max(0, ($sumSq / $totalPx) - ($mean * $mean));

                // Flat white paper has variance near 0. Faint diagonal watermark text
                // pushes this well above ~15-20 even when no pixel is individually "dark".
                $clean = $variance < 12 && $mean > 245;
                return [$clean, $inkCount, $variance];
            };

            $preferredOrder = ['bottom-right', 'bottom-left', 'top-right', 'top-left', 'bottom-center', 'top-center'];
            $anchorPoints = [
                'bottom-right'  => [$imgW - $stampWpx - $marginPx, $imgH - $stampHpx - $marginPx],
                'bottom-left'   => [$marginPx, $imgH - $stampHpx - $marginPx],
                'top-right'     => [$imgW - $stampWpx - $marginPx, $marginPx],
                'top-left'      => [$marginPx, $marginPx],
                'bottom-center' => [(int) (($imgW - $stampWpx) / 2), $imgH - $stampHpx - $marginPx],
                'top-center'    => [(int) (($imgW - $stampWpx) / 2), $marginPx],
            ];

            $best = null;
            $found = false;

            foreach ($preferredOrder as $key) {
                [$x, $y] = $anchorPoints[$key];
                if ($x < 0 || $y < 0 || $x + $stampWpx > $imgW || $y + $stampHpx > $imgH) continue;

                [$clean] = $isClean($x, $y);
                if ($clean) {
                    $best = ['x' => $x / $pxPerMm, 'y' => $y / $pxPerMm];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                for ($y = $marginPx; $y + $stampHpx <= $imgH - $marginPx && !$found; $y += $step) {
                    for ($x = $marginPx; $x + $stampWpx <= $imgW - $marginPx; $x += $step) {
                        [$clean] = $isClean($x, $y);
                        if ($clean) {
                            $best = ['x' => $x / $pxPerMm, 'y' => $y / $pxPerMm];
                            $found = true;
                            break;
                        }
                    }
                }
            }

            if (!$found) {
                Log::info('Stamp: no clean empty area found, using fallback', ['page' => $pageNum]);
                return $this->clampToPage($fallback, $pageWmm, $pageHmm, $stampWmm, $stampHmm);
            }

            return $best;

        } catch (\Throwable $e) {
            Log::warning('Stamp: auto-placement detection failed', ['error' => $e->getMessage(), 'page' => $pageNum]);
            return $fallback;
        }
    }

    // ──────────────────────────────────────────
    // BACKUP MANAGEMENT
    // Always stamp from the ORIGINAL file, not the already-stamped one
    // ──────────────────────────────────────────

    private function getBackupDir(int $requestId): string
    {
        return storage_path('app/private/stamp_backups/' . $requestId);
    }

    private function getBackupPath(int $requestId, string $fileKey): string
    {
        return $this->getBackupDir($requestId) . '/' . $fileKey . '_original.pdf';
    }

    private function ensureBackup(int $requestId, string $fileKey, string $currentFilePath): string
    {
        $backupPath = $this->getBackupPath($requestId, $fileKey);

        // Backup already exists — use it
        if (file_exists($backupPath) && filesize($backupPath) > 0) {
            Log::info('Stamp: using existing backup', ['backup' => $backupPath]);
            return $backupPath;
        }

        // Create backup from current file
        $backupDir = $this->getBackupDir($requestId);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        if (!copy($currentFilePath, $backupPath)) {
            throw new \RuntimeException('Failed to create backup of original file.');
        }

        Log::info('Stamp: created backup', [
            'from' => $currentFilePath,
            'to'   => $backupPath,
            'size' => filesize($backupPath),
        ]);

        return $backupPath;
    }

    private function restoreFromBackup(int $requestId, string $fileKey, string $targetPath): bool
    {
        $backupPath = $this->getBackupPath($requestId, $fileKey);

        if (!file_exists($backupPath)) {
            Log::warning('Stamp: no backup found to restore', ['backup' => $backupPath]);
            return false;
        }

        $restored = copy($backupPath, $targetPath);

        Log::info('Stamp: restored from backup', [
            'backup' => $backupPath,
            'target' => $targetPath,
            'success' => $restored,
        ]);

        return $restored;
    }

    // ──────────────────────────────────────────
    // APPLY STAMP
    // ──────────────────────────────────────────

    public function apply(Request $request)
    {
        $validated = $this->validateStampPayload($request);

        $fullPath = $this->resolveFilePath($validated['file_path']);

        if (!$fullPath) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on the server.',
            ], 404);
        }

        try {
            // Get or create backup of the ORIGINAL (unstamped) file
            $sourcePath = $this->ensureBackup(
                $validated['request_id'],
                $validated['file_key'],
                $fullPath
            );

            Log::info('Stamp: applying to file', [
                'source'  => $sourcePath,
                'target'  => $fullPath,
                'type'    => $validated['stamp_type'],
                'pos'     => $validated['position'],
            ]);

            // Stamp from the ORIGINAL file
            $outputPath = $this->stampPdf(
                $sourcePath,
                $validated['stamp_type'],
                $validated['position'],
                [
                    'stamp_all_pages' => $validated['all_pages'],
                    'certified_by'    => $validated['certified_by'] ?? '',
                    'designation'     => $validated['designation'] ?? '',
                ]
            );

            // Verify output exists and is valid
            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException('Stamp output file is empty or missing.');
            }

            Log::info('Stamp: output generated', [
                'output' => $outputPath,
                'size'   => filesize($outputPath),
            ]);

            // Overwrite the actual file with the stamped version
            if (!copy($outputPath, $fullPath)) {
                throw new \RuntimeException('Failed to write stamped file to destination.');
            }

            Log::info('Stamp: file overwritten', [
                'target' => $fullPath,
                'newSize' => filesize($fullPath),
            ]);

            // Clean up temp file
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            // Record in database (update or create)
            $existing = DocumentStamp::where('document_request_id', $validated['request_id'])
                ->where('file_key', $validated['file_key'])
                ->first();

            $stampData = [
                'file_path'    => $validated['file_path'],
                'stamp_type'   => $validated['stamp_type'],
                'position'     => $validated['position'],
                'all_pages'    => $validated['all_pages'],
                'certified_by' => $validated['certified_by'] ?? null,
                'designation'  => $validated['designation'] ?? null,
                'stamped_by'   => Auth::id(),
                'stamped_at'   => now(),
            ];

            if ($existing) {
                $existing->update($stampData);
                $action = 'changed';
            } else {
                DocumentStamp::create(array_merge($stampData, [
                    'document_request_id' => $validated['request_id'],
                    'file_key'            => $validated['file_key'],
                ]));
                $action = 'applied';
            }

            $stampLabel = self::STAMPS[$validated['stamp_type']]['label'];

            Log::info("Stamp {$action}", [
                'doc'  => $validated['doc_no'] ?? 'N/A',
                'file' => $validated['file_path'],
                'type' => $validated['stamp_type'],
                'user' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Stamp {$action} — {$stampLabel}",
            ]);

        } catch (\Throwable $e) {
            Log::error('Stamp apply error', [
                'file'    => $validated['file_path'],
                'type'    => $validated['stamp_type'],
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stamping failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────
    // REMOVE STAMP — restores original file
    // ──────────────────────────────────────────

    public function remove(Request $request)
    {
        $request->validate([
            'request_id' => 'required|integer|exists:dcs_document_requests,id',
            'file_key'   => 'required|string|in:masterlist,drf,dcn,distribution,retrieval',
            'file_path'  => 'required|string',
        ]);

        $stamp = DocumentStamp::where('document_request_id', $request->request_id)
            ->where('file_key', $request->file_key)
            ->first();

        if (!$stamp) {
            return response()->json([
                'success' => false,
                'message' => 'No stamp found for this file.',
            ], 404);
        }

        $path = ltrim(str_replace(['../', '..\\'], '', $request->file_path), '/');
        $fullPath = $this->resolveFilePath($path);

        if ($fullPath) {
            $this->restoreFromBackup(
                $request->request_id,
                $request->file_key,
                $fullPath
            );
        }

        $stamp->delete();

        Log::info('Stamp removed', [
            'request_id' => $request->request_id,
            'file_key'   => $request->file_key,
            'user'       => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stamp removed. Original file restored.',
        ]);
    }

    // ──────────────────────────────────────────
    // DOWNLOAD STAMPED COPY (no overwrite)
    // ──────────────────────────────────────────

    public function download(Request $request)
    {
        $validated = $this->validateStampPayload($request);

        $fullPath = $this->resolveFilePath($validated['file_path']);

        if (!$fullPath) {
            return response()->json([
                'success' => false,
                'message' => 'File not found.',
            ], 404);
        }

        try {
            // Stamp from backup if available, otherwise from current file
            $backupPath = $this->getBackupPath($validated['request_id'], $validated['file_key']);
            $sourcePath = file_exists($backupPath) ? $backupPath : $fullPath;

            $outputPath = $this->stampPdf(
                $sourcePath,
                $validated['stamp_type'],
                $validated['position'],
                [
                    'stamp_all_pages' => $validated['all_pages'],
                    'certified_by'    => $validated['certified_by'] ?? '',
                    'designation'     => $validated['designation'] ?? '',
                ]
            );

            $stampLabel = strtolower(str_replace(' ', '-', self::STAMPS[$validated['stamp_type']]['label']));
            $baseName   = pathinfo($validated['file_path'], PATHINFO_FILENAME);
            $filename   = "stamped-{$stampLabel}-{$baseName}.pdf";

            return response()
                ->download($outputPath, $filename, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('Stamp download error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────
    // CHECK STAMP
    // ──────────────────────────────────────────

    public function checkStamp(Request $request)
    {
        $request->validate([
            'request_id' => 'required|integer',
            'file_key'   => 'required|string',
        ]);

        $stamp = DocumentStamp::where('document_request_id', $request->request_id)
            ->where('file_key', $request->file_key)
            ->first();

        if (!$stamp) {
            return response()->json(['stamped' => false]);
        }

        return response()->json([
            'stamped'    => true,
            'stamp_type' => $stamp->stamp_type,
            'label'      => self::STAMPS[$stamp->stamp_type]['label'] ?? $stamp->stamp_type,
            'stamped_at' => $stamp->stamped_at->format('M d, Y h:i A'),
            'stamped_by' => $stamp->user->name ?? 'Unknown',
        ]);
    }

    // ──────────────────────────────────────────
    // FILE PATH RESOLUTION
    // ──────────────────────────────────────────

    private function resolveFilePath(string $path): ?string
    {
        $path = ltrim($path, '/');

        $candidates = [
            storage_path('app/public/' . $path),
            storage_path('app/' . $path),
            public_path('storage/' . $path),
            public_path($path),
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && file_exists($real) && is_readable($real)) {
                Log::info('Stamp: resolved file', ['input' => $path, 'resolved' => $real]);
                return $real;
            }
        }

        Log::warning('Stamp: file not found', [
            'path'       => $path,
            'candidates' => $candidates,
        ]);

        return null;
    }

    // ──────────────────────────────────────────
    // PDF STAMPING ENGINE
    // ──────────────────────────────────────────

    private function stampPdf(string $inputPath, string $stampType, string $position, array $options): string
    {
        if (!file_exists($inputPath)) {
            throw new \RuntimeException("Source PDF not found: {$inputPath}");
        }

        if (filesize($inputPath) === 0) {
            throw new \RuntimeException("Source PDF is empty: {$inputPath}");
        }

        Log::info('Stamp: engine starting', [
            'input'  => $inputPath,
            'size'   => filesize($inputPath),
            'type'   => $stampType,
            'pos'    => $position,
            'pages'  => $options['stamp_all_pages'] ? 'all' : 'first',
        ]);

        $pdf = new Fpdi();

        // CRITICAL: prevent FPDI from silently inserting new blank pages when a
        // Cell() call would cross the default bottom margin. We manage pages
        // manually via importPage()/useTemplate(), so auto page-break must be off —
        // otherwise a stamp placed near the page edge causes its trailing lines
        // (date/subtitle) to spill onto an auto-created extra page.
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pageCount = $pdf->setSourceFile($inputPath);

        if ($pageCount < 1) {
            throw new \RuntimeException('PDF has no pages.');
        }

        Log::info('Stamp: loaded PDF', ['pageCount' => $pageCount]);

        $stampAll = $options['stamp_all_pages'] ?? true;

        for ($page = 1; $page <= $pageCount; $page++) {
            $tplId = $pdf->importPage($page);
            $size  = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            if ($stampAll || $page === 1) {
                $this->renderStamp($pdf, $stampType, $position, $size['width'], $size['height'], $options, $inputPath, $page);
            }
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'stamp_') . '.pdf';
        $pdf->Output($outputPath, 'F');

        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('FPDI failed to generate output PDF.');
        }

        Log::info('Stamp: engine output', [
            'output' => $outputPath,
            'size'   => filesize($outputPath),
        ]);

        return $outputPath;
    }

    private function renderStamp($pdf, string $type, string $position, float $pageW, float $pageH, array $options, string $pdfPath, int $pageNum): void
    {
        $config = self::STAMPS[$type];

        $pos = $position === 'auto'
            ? $this->findEmptyArea($pdfPath, $pageNum, $pageW, $pageH, $config['width'], $config['height'])
            : $this->calcPosition($position, $pageW, $pageH, $config['width'], $config['height']);

        if ($type === 'certified_true_copy') {
            $this->drawCertified($pdf, $pos['x'], $pos['y'], $config, $options);
        } else {
            $this->drawStandard($pdf, $pos['x'], $pos['y'], $config);
        }
    }

    private function calcPosition(string $position, float $pageW, float $pageH, float $stampW, float $stampH): array
    {
        $m = 15;

        $pos = match ($position) {
            'top-left'     => ['x' => $m, 'y' => $m],
            'top-right'    => ['x' => $pageW - $stampW - $m, 'y' => $m],
            'bottom-left'  => ['x' => $m, 'y' => $pageH - $stampH - $m],
            'bottom-right' => ['x' => $pageW - $stampW - $m, 'y' => $pageH - $stampH - $m],
            'center'       => ['x' => ($pageW - $stampW) / 2, 'y' => ($pageH - $stampH) / 2],
        };

        return $this->clampToPage($pos, $pageW, $pageH, $stampW, $stampH);
    }

    // NEW: hard safety clamp — guarantees the stamp box (and everything drawn
    // inside it, including trailing text lines) always stays fully within the
    // physical page bounds, regardless of which placement strategy picked the spot.
    private function clampToPage(array $pos, float $pageW, float $pageH, float $stampW, float $stampH): array
    {
        $pos['x'] = max(0, min($pos['x'], $pageW - $stampW));
        $pos['y'] = max(0, min($pos['y'], $pageH - $stampH));
        return $pos;
    }

    private function drawStandard($pdf, float $x, float $y, array $cfg): void
    {
        $w = $cfg['width'];
        $h = $cfg['height'];
        [$r, $g, $b] = $cfg['color'];
        $date = now()->format('M d, Y');

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $w, $h, 'F');

        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetLineWidth(0.6);
        $pdf->Rect($x, $y, $w, $h);

        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x + 1.5, $y + 1.5, $w - 3, $h - 3);

        $pdf->SetFont('Helvetica', 'B', $cfg['titleSize']);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetXY($x, $y + 3);
        $pdf->Cell($w, 6, $cfg['title'], 0, 0, 'C');

        $pdf->SetFont('Helvetica', '', $cfg['subSize']);
        $pdf->SetXY($x, $y + 10);
        $pdf->Cell($w, 5, 'Date: ' . $date, 0, 0, 'C');

        $pdf->SetFont('Helvetica', 'I', 6);
        $pdf->SetXY($x, $y + 15.5);
        $pdf->Cell($w, 4, $cfg['subtitle'], 0, 0, 'C');
    }

    private function drawCertified($pdf, float $x, float $y, array $cfg, array $options): void
    {
        $w = $cfg['width'];
        $h = $cfg['height'];
        [$r, $g, $b] = $cfg['color'];
        $date   = now()->format('M d, Y');
        $certBy = $options['certified_by'] ?? '';
        $desig  = $options['designation'] ?? '';

        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $w, $h, 'F');

        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetLineWidth(0.7);
        $pdf->Rect($x, $y, $w, $h);

        $pdf->SetLineWidth(0.3);
        $pdf->Rect($x + 1.5, $y + 1.5, $w - 3, $h - 3);

        $pdf->SetFont('Helvetica', 'B', $cfg['titleSize']);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetXY($x, $y + 3);
        $pdf->Cell($w, 5, $cfg['title'], 0, 0, 'C');

        $pdf->SetLineWidth(0.2);
        $pdf->Line($x + 4, $y + 9, $x + $w - 4, $y + 9);

        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetXY($x + 3, $y + 10.5);
        $pdf->Cell(25, 4, 'Certified by:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->Cell($w - 31, 4, $certBy, 0, 0, 'L');

        $pdf->SetLineWidth(0.15);
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Line($x + 25, $y + 14.5, $x + $w - 4, $y + 14.5);

        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetXY($x + 3, $y + 16);
        $pdf->Cell(25, 4, 'Designation:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->Cell($w - 31, 4, $desig, 0, 0, 'L');

        $pdf->Line($x + 25, $y + 20, $x + $w - 4, $y + 20);

        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetXY($x, $y + 23);
        $pdf->Cell($w, 4, 'Date: ' . $date, 0, 0, 'C');
    }
}