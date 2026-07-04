<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - CSPC DCS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #172b4d;
            background: #fff;
            padding: 0;
        }

        .print-toolbar {
            background: #f4f5f7;
            border-bottom: 1px solid #dfe1e6;
            padding: 10px 24px;
            display: flex;
            justify-content: center;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .print-toolbar button {
            padding: 7px 20px;
            border: 1px solid #dfe1e6;
            border-radius: 4px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .print-toolbar .btn-print {
            background: #0c4a2f;
            color: #fff;
            border-color: #0c4a2f;
        }
        .print-toolbar .btn-close {
            background: #fff;
            color: #172b4d;
        }

        .print-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
        }

        .print-header {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 3px double #0c4a2f;
        }
        .print-header h1 {
            font-size: 18px;
            font-weight: 700;
            color: #0c4a2f;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .print-header h2 {
            font-size: 15px;
            font-weight: 400;
            color: #172b4d;
            margin-top: 4px;
        }
        .print-header p {
            font-size: 11px;
            color: #5e6c84;
            margin-top: 6px;
        }

        .print-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 11px;
            color: #5e6c84;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        th {
            background: #f4f5f7;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 10px;
            color: #5e6c84;
            padding: 10px 10px;
            border: 1px solid #dfe1e6;
            text-align: left;
        }
        td {
            padding: 9px 10px;
            border: 1px solid #ebecf0;
            vertical-align: top;
            color: #172b4d;
        }
        tr:nth-child(even) td {
            background: #fafbfc;
        }
        .rpt-na { color: #97a0af; }
        a { color: #0c4a2f; text-decoration: none; }

        .print-footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #dfe1e6;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #97a0af;
        }

        .print-sig {
            margin-top: 48px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .print-sig-block {
            flex: 1;
            text-align: center;
        }
        .print-sig-line {
            border-top: 1px solid #172b4d;
            margin-top: 50px;
            padding-top: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #172b4d;
        }
        .print-sig-label {
            font-size: 10px;
            color: #5e6c84;
            margin-top: 2px;
        }

        @media print {
            .print-toolbar { display: none; }
            body { padding: 0; }
            .print-container { padding: 20px 30px; }
        }
    </style>
</head>
<body>

    <div class="print-toolbar">
        <button class="btn-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn-close" onclick="window.close()">Close</button>
    </div>

    <div class="print-container">

        <div class="print-header">
            <h1>CSPC Document Control System</h1>
            <h2>{{ $title }}</h2>
            @if($categoryLabel && $subLabel)
                <p>{{ $categoryLabel }} &mdash; {{ $subLabel }}</p>
            @endif
        </div>

        <div class="print-meta">
            <div>
                @if($dateFrom || $dateTo)
                    <strong>Period:</strong>
                    {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('M d, Y') : 'Start' }}
                    &mdash;
                    {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('M d, Y') : 'Present' }}
                @else
                    <strong>Period:</strong> All Time
                @endif
            </div>
            <div>
                <strong>Generated:</strong> {{ now()->format('M d, Y h:i A') }}
                &nbsp;|&nbsp;
                <strong>Records:</strong> {{ count($rows) }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    @foreach($columns as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach(array_keys($columns) as $key)
                            @if($key === 'pdf_path' && $row[$key])
                                <td><a href="{{ $row[$key] }}" target="_blank">View File</a></td>
                            @elseif($row[$key] === null || $row[$key] === '')
                                <td class="rpt-na">&mdash;</td>
                            @else
                                <td>{{ $row[$key] }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" style="text-align:center; padding:30px; color:#97a0af;">
                            No records found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="print-sig">
            <div class="print-sig-block">
                <div class="print-sig-line">Prepared By</div>
                <div class="print-sig-label">Document Control Officer</div>
            </div>
            <div class="print-sig-block">
                <div class="print-sig-line">Reviewed By</div>
                <div class="print-sig-label">Quality Assurance Head</div>
            </div>
            <div class="print-sig-block">
                <div class="print-sig-line">Approved By</div>
                <div class="print-sig-label">Management Representative</div>
            </div>
        </div>

        <div class="print-footer">
            <span>CSPC Document Control System &mdash; Confidential</span>
            <span>Page generated on {{ now()->format('M d, Y h:i A') }}</span>
        </div>

    </div>

</body>
</html>