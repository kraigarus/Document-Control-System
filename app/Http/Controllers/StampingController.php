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
        ->orderBy('request_id', 'desc')
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
            'request_id'   => 'required|integer|exists:document_requests,request_id',
            'doc_no'       => 'nullable|string|max:100',
            'doc_title'    => 'nullable|string|max:500',
            'rev'          => 'nullable|string|max:20',
            'stamp_type'   => 'required|string|in:' . implode(',', array_keys(self::STAMPS)),
            'position'     => 'required|string|in:top-left,top-right,bottom-left,bottom-right,center',
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
            'request_id' => 'required|integer|exists:document_requests,request_id',
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
                $this->renderStamp($pdf, $stampType, $position, $size['width'], $size['height'], $options);
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

    private function renderStamp($pdf, string $type, string $position, float $pageW, float $pageH, array $options): void
    {
        $config = self::STAMPS[$type];
        $pos    = $this->calcPosition($position, $pageW, $pageH, $config['width'], $config['height']);

        if ($type === 'certified_true_copy') {
            $this->drawCertified($pdf, $pos['x'], $pos['y'], $config, $options);
        } else {
            $this->drawStandard($pdf, $pos['x'], $pos['y'], $config);
        }
    }

    private function calcPosition(string $position, float $pageW, float $pageH, float $stampW, float $stampH): array
    {
        $m = 15;

        return match ($position) {
            'top-left'     => ['x' => $m, 'y' => $m],
            'top-right'    => ['x' => $pageW - $stampW - $m, 'y' => $m],
            'bottom-left'  => ['x' => $m, 'y' => $pageH - $stampH - $m],
            'bottom-right' => ['x' => $pageW - $stampW - $m, 'y' => $pageH - $stampH - $m],
            'center'       => ['x' => ($pageW - $stampW) / 2, 'y' => ($pageH - $stampH) / 2],
        };
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