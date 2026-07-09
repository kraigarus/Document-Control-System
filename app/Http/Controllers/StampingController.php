<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            'docType',
        ])
        ->where(function ($q) {
            $q->whereHas('masterlistRegistration', fn ($q2) =>
                $q2->whereNotNull('scanned_masterlist')->where('scanned_masterlist', '!=', ''))
              ->orWhereHas('documentRequestForm', fn ($q2) =>
                $q2->whereNotNull('scanned_drf')->where('scanned_drf', '!=', ''))
              ->orWhereHas('documentChangeNotice', fn ($q2) =>
                $q2->whereNotNull('scanned_dcn')->where('scanned_dcn', '!=', ''));
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
            'doc_no'       => 'nullable|string|max:100',
            'doc_title'    => 'nullable|string|max:500',
            'rev'          => 'nullable|string|max:20',
            'stamp_type'   => 'required|string|in:' . implode(',', array_keys(self::STAMPS)),
            'position'     => 'required|string|in:top-left,top-right,bottom-left,bottom-right,center',
            'all_pages'    => 'required|boolean',
            'certified_by' => 'nullable|string|max:255',
            'designation'  => 'nullable|string|max:255',
        ], [
            'file_path.required'  => 'No file selected.',
            'file_path.string'    => 'Invalid file path.',
            'file_key.required'   => 'File type is required.',
            'file_key.in'         => 'Invalid file type selected.',
            'stamp_type.required' => 'Please select a stamp type.',
            'stamp_type.in'       => 'Invalid stamp type.',
            'position.required'   => 'Please select a stamp position.',
            'position.in'         => 'Invalid position.',
            'all_pages.required'  => 'Page selection is required.',
            'all_pages.boolean'   => 'Invalid page selection.',
            'certified_by.max'    => 'Certified by name cannot exceed 255 characters.',
            'designation.max'     => 'Designation cannot exceed 255 characters.',
        ]);

        // If certified_true_copy, certified_by and designation are mandatory
        if ($validated['stamp_type'] === 'certified_true_copy') {
            if (empty(trim($validated['certified_by'] ?? ''))) {
                abort(422, 'Certified By is required for Certified True Copy stamps.');
            }
            if (empty(trim($validated['designation'] ?? ''))) {
                abort(422, 'Designation is required for Certified True Copy stamps.');
            }
        }

        // Sanitize file_path — prevent directory traversal
        $path = $validated['file_path'];
        $path = ltrim($path, '/');
        $path = str_replace(['../', '..\\'], '', $path);

        if (str_contains($path, '..')) {
            abort(422, 'Invalid file path.');
        }

        $validated['file_path'] = $path;

        return $validated;
    }

    // ──────────────────────────────────────────
    // APPLY STAMP (saves permanently)
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
            $outputPath = $this->stampPdf(
                $fullPath,
                $validated['stamp_type'],
                $validated['position'],
                [
                    'stamp_all_pages' => $validated['all_pages'],
                    'certified_by'    => $validated['certified_by'] ?? '',
                    'designation'     => $validated['designation'] ?? '',
                ]
            );

            copy($outputPath, $fullPath);
            @unlink($outputPath);

            $stampLabel = self::STAMPS[$validated['stamp_type']]['label'];

            Log::info('Stamp applied successfully', [
                'file'  => $validated['file_path'],
                'type'  => $validated['stamp_type'],
                'doc'   => $validated['doc_no'] ?? 'N/A',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Stamp applied — {$stampLabel}",
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
    // DOWNLOAD STAMPED COPY (does NOT modify original)
    // ──────────────────────────────────────────

    public function download(Request $request)
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
            $outputPath = $this->stampPdf(
                $fullPath,
                $validated['stamp_type'],
                $validated['position'],
                [
                    'stamp_all_pages' => $validated['all_pages'],
                    'certified_by'    => $validated['certified_by'] ?? '',
                    'designation'     => $validated['designation'] ?? '',
                ]
            );

            $prefix   = $validated['stamp_type'] === 'certified_true_copy' ? 'certified-' : '';
            $stampLabel = strtolower(str_replace(' ', '-', self::STAMPS[$validated['stamp_type']]['label']));
            $baseName = pathinfo($validated['file_path'], PATHINFO_FILENAME);
            $filename = "stamped-{$prefix}{$stampLabel}-{$baseName}.pdf";

            Log::info('Stamp download generated', [
                'file' => $validated['file_path'],
                'type' => $validated['stamp_type'],
            ]);

            return response()
                ->download($outputPath, $filename, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('Stamp download error', [
                'file'    => $validated['file_path'],
                'type'    => $validated['stamp_type'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────
    // PREVIEW — returns first page with stamp as image
    // ──────────────────────────────────────────

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'file_path'    => 'required|string|max:500',
            'stamp_type'   => 'required|string|in:' . implode(',', array_keys(self::STAMPS)),
            'position'     => 'required|string|in:top-left,top-right,bottom-left,bottom-right,center',
            'certified_by' => 'nullable|string|max:255',
            'designation'  => 'nullable|string|max:255',
        ]);

        $path = ltrim(str_replace(['../', '..\\'], '', $validated['file_path']), '/');
        $fullPath = $this->resolveFilePath($path);

        if (!$fullPath) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($fullPath);

            if ($pageCount < 1) {
                return response()->json(['error' => 'PDF has no pages.'], 400);
            }

            $tplId = $pdf->importPage(1);
            $size  = $pdf->getTemplateSize($tplId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);

            $this->renderStamp($pdf, $validated['stamp_type'], $validated['position'], $size['width'], $size['height'], [
                'stamp_all_pages' => true,
                'certified_by'    => $validated['certified_by'] ?? '',
                'designation'     => $validated['designation'] ?? '',
            ]);

            $tempPath = sys_get_temp_dir() . '/preview_' . uniqid() . '.pdf';
            $pdf->Output($tempPath, 'F');

            return response()
                ->file($tempPath, ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('Stamp preview error: ' . $e->getMessage());
            return response()->json(['error' => 'Preview failed: ' . $e->getMessage()], 500);
        }
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
                Log::info('Stamp: resolved file', ['from' => $path, 'to' => $real]);
                return $real;
            }
        }

        Log::warning('Stamp: file not found in any candidate path', [
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
        Log::info('Stamp: starting', [
            'input' => $inputPath,
            'type'  => $stampType,
            'pos'   => $position,
        ]);

        $pdf = new Fpdi();

        $pageCount = $pdf->setSourceFile($inputPath);

        if ($pageCount < 1) {
            throw new \RuntimeException('The PDF has no pages.');
        }

        Log::info('Stamp: imported PDF', ['pages' => $pageCount]);

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

        $outputPath = sys_get_temp_dir() . '/stamp_' . uniqid() . '.pdf';
        $pdf->Output($outputPath, 'F');

        Log::info('Stamp: output written', ['output' => $outputPath, 'size' => filesize($outputPath)]);

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