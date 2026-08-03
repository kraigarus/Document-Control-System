<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    private array $columns;
    private array $rows;

    public function __construct(array $columns, array $rows)
    {
        $this->columns = $columns;
        $this->rows = $rows;
    }

    public function array(): array
    {
        $colKeys = array_keys($this->columns);

        return array_map(function ($row) use ($colKeys) {
            return array_map(function ($key) use ($row) {
                $val = $row[$key] ?? '';
                if ($key === 'pdf_path' && $val) {
                    return 'View File';
                }
                return $val;
            }, $colKeys);
        }, $this->rows);
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function styles(Worksheet $sheet): array
    {
        // Apply Arial 11 to ALL cells in the sheet
        $sheet->getStyle('A1:ZZ1000')->applyFromArray([
            'font' => [
                'name' => 'Arial',
                'size' => 11,
            ],
        ]);

        // Header row: bold + fill
        return [
            1 => [
                'font' => [
                    'name'  => 'Arial',
                    'bold'  => true,
                    'size'  => 11,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFE2E8F0'],
                ],
            ],
        ];
    }
}