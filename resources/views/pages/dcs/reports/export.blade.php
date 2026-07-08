<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - CSPC DCS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #fff;
        }

        /* ── Toolbar ── */
        .print-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 24px;
            display: flex;
            justify-content: center;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .print-toolbar button {
            padding: 8px 20px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .print-toolbar .btn-pdf { background: #0d2a7a; color: #fff; border-color: #0d2a7a; }
        .print-toolbar .btn-pdf:hover { background: #0b2368; }
        .print-toolbar .btn-print { background: #fff; color: #0d2a7a; border-color: #0d2a7a; }
        .print-toolbar .btn-print:hover { background: #eef2ff; }
        .print-toolbar .btn-close { background: #fff; color: #64748b; border-color: #e2e8f0; }
        .print-toolbar .btn-close:hover { background: #f8fafc; }

        /* ── Container — portrait A4 ── */
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 40px;
        }

        /* ═══════════════════════════════════
           INSTITUTION HEADER
           ═══════════════════════════════════ */
        .rpt-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }

        .rpt-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .rpt-inst-logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .rpt-inst-text {
            line-height: 1.4;
        }

        .rpt-inst-republic {
            font-size: 11px;
            font-weight: 400;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rpt-inst-name {
            font-size: 14px;
            font-weight: 700;
            color: #0d2a7a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .rpt-inst-location {
            font-size: 11px;
            font-weight: 400;
            color: #64748b;
        }

                /* ── Header Line with Number Right ── */
        .rpt-header-line {
            position: relative;
            margin-bottom: 16px;
            border-top: 2px solid #0d2a7a;
            height: 24px;
        }
        .rpt-header-line .line-right {
            position: absolute;
            top: -13px;
            right: 0;
            background: #fff;
            padding: 0 0 0 12px;
            font-size: 12px;
            font-weight: 700;
            color: #0d2a7a;
        }
        /* ═══════════════════════════════════
           REPORT TITLE
           ═══════════════════════════════════ */
        .rpt-report-title {
            text-align: center;
            margin-bottom: 14px;
        }

        .rpt-report-title h2 {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ═══════════════════════════════════
           CHECKBOXES
           ═══════════════════════════════════ */
        .rpt-filter-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .rpt-filter-check {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 500;
            color: #1e293b;
        }

        .rpt-filter-check input[type="checkbox"] {
            width: 13px;
            height: 13px;
            accent-color: #0d2a7a;
            cursor: pointer;
        }

        /* ═══════════════════════════════════
           TABLE
           ═══════════════════════════════════ */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th {
            background: #d6e4f0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 9px;
            color: #1e293b;
            padding: 8px 7px;
            border: 1px solid #b0c4de;
            text-align: left;
        }

        td {
            padding: 7px;
            border: 1px solid #f1f5f9;
            vertical-align: top;
            color: #1e293b;
            font-size: 10px;
        }

        tr:nth-child(even) td { background: #fafbfc; }
        .rpt-na { color: #94a3b8; }
        a { color: #0d2a7a; text-decoration: none; }

        /* ═══════════════════════════════════
           FOOTER — Fn (Form Number)
           ═══════════════════════════════════ */
               /* ═══════════════════════════════════
           FOOTER — Left, Center, Right
           ═══════════════════════════════════ */
        .rpt-footer {
            margin-top: 32px;
            padding-top: 8px;
            border-top: 1.5px solid #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #1e293b;
        }
        .rpt-footer .footer-left {
            flex: 1;
            text-align: left;
        }
        .rpt-footer .footer-center {
            flex: 1;
            text-align: center;
        }
        .rpt-footer .footer-right {
            flex: 1;
            text-align: right;
        }

        /* ── Print ── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 15mm 20mm 15mm;
            }
            .print-toolbar { display: none !important; }
            body { padding: 0; }
            .print-container { padding: 0 0 40px 0; max-width: 100%; }
            table { font-size: 9px; }
            th { font-size: 8px; padding: 6px 5px; }
            td { font-size: 9px; padding: 5px; }
            .rpt-filter-check input[type="checkbox"] {
                -webkit-appearance: auto;
                appearance: auto;
            }
            .rpt-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                margin: 0;
                padding: 8px 15mm;
                border-top: 1.5px solid #1e293b;
                background: #fff;
            }
        }
    </style>
</head>
<body>

    {{-- TOOLBAR --}}
    <div class="print-toolbar" id="toolbar">
        <button class="btn-pdf" onclick="window.print()" type="button">
            <i class="fa-solid fa-file-pdf"></i> Save as PDF
        </button>
        <button class="btn-print" onclick="window.print()" type="button">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn-close" onclick="window.close()" type="button">
            Close
        </button>
    </div>

    <div class="print-container">

        {{-- ═══════════════════════════════════════
             HEADER — Logo + Institution + Date/Number
             ═══════════════════════════════════════ --}}
        <div class="rpt-header-top">
            <div class="rpt-header-left">
                <img src="/images/logo.png" alt="Logo" class="rpt-inst-logo">
                <div class="rpt-inst-text">
                    <div class="rpt-inst-republic">{{ $republic ?? 'Republic of the Philippines' }}</div>
                    <div class="rpt-inst-name">{{ $institutionName ?? 'Camarines Sur Polytechnic Colleges' }}</div>
                    <div class="rpt-inst-location">{{ $institutionAddress ?? 'Naga City, Camarines Sur' }}</div>
                </div>
            </div>
        </div>

        {{-- LINE WITH NUMBER IN CENTER --}}
        <div class="rpt-header-line">
            <span class="line-right">{{ $letterNumber ?? 'CSPC-QA-F001' }}</span>
        </div>



        {{-- ═══════════════════════════════════════
             TITLE
             ═══════════════════════════════════════ --}}
        <div class="rpt-report-title">
            <h2>{{ $title ?? 'Document Masterlist' }}</h2>
        </div>

        {{-- ═══════════════════════════════════════
             CHECKBOX FILTERS
             ═══════════════════════════════════════ --}}
                {{-- CHECKBOX FILTERS --}}
        @php
            $allSubs = [
                'internal_docs'  => 'Internal',
                'external_docs'  => 'External',
                'internal_forms' => 'Internal Forms',
                'forms'          => 'Forms',
                'logbooks'       => 'Logbooks',
            ];
            $activeSub = $activeSub ?? null;
        @endphp

        <div class="rpt-filter-row">
            @foreach($allSubs as $key => $label)
                <label class="rpt-filter-check">
                    <input type="checkbox"
                           {{ ($activeSub === $key) ? 'checked' : '' }}
                           disabled>
                    {{ $label }}
                </label>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════
             TABLE — pdf_path filtered out
             ═══════════════════════════════════════ --}}
        @php
            $visibleColumns = [];
            foreach ($columns as $key => $label) {
                if ($key !== 'pdf_path') {
                    $visibleColumns[$key] = $label;
                }
            }
        @endphp

        <table>
            <thead>
                <tr>
                    @foreach($visibleColumns as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        @foreach(array_keys($visibleColumns) as $key)
                            @if($row[$key] === null || $row[$key] === '')
                                <td class="rpt-na">&mdash;</td>
                            @else
                                <td>{{ $row[$key] }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($visibleColumns) }}" style="text-align:center;padding:30px;color:#94a3b8;">
                            No records found for the selected filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

                {{-- FOOTER --}}
        <div class="rpt-footer">
            <span class="footer-left">{{ $footerLeft ?? 'Effectivity Date:' }}</span>
            <span class="footer-center">{{ $footerCenter ?? 'Rev:' }}</span>
            <span class="footer-right">{{ $footerRight ?? 'Fn: CSPC-QA-F001' }}</span>
        </div>

    </div>

    @if(!empty($autoPrint))
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 500);
        });
    </script>
    @endif

</body>
</html>