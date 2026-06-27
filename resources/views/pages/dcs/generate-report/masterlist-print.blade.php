<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: landscape;
            margin: 15mm 12mm;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 9pt;
            color: #1a1a1a;
            line-height: 1.3;
        }

        /* Header */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #0d2a7a;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .report-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .report-logo {
            height: 45px;
        }

        .report-org-info h2 {
            font-size: 11pt;
            font-weight: 700;
            color: #0d2a7a;
            margin: 0;
        }

        .report-org-info p {
            font-size: 8pt;
            color: #555;
            margin: 0;
        }

        .report-header-right {
            text-align: right;
        }

        .report-header-right .doc-ref {
            font-size: 7.5pt;
            color: #666;
        }

        /* Title */
        .report-title-bar {
            text-align: center;
            background: #0d2a7a;
            color: #fff;
            padding: 8px 0;
            margin-bottom: 10px;
        }

        .report-title-bar h1 {
            font-size: 13pt;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Meta */
        .report-meta {
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #555;
            margin-bottom: 10px;
            padding: 0 2px;
        }

        /* Table */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .report-table thead th {
            background: #e8ecf4;
            color: #0d2a7a;
            font-weight: 700;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 7px 8px;
            border: 1px solid #bfc6d4;
            text-align: center;
        }

        .report-table tbody td {
            padding: 6px 8px;
            border: 1px solid #d5d9e2;
            vertical-align: middle;
        }

        .report-table tbody tr:nth-child(even) {
            background: #f7f8fb;
        }

        .report-table .col-num {
            text-align: center;
            width: 40px;
            font-weight: 600;
        }

        .report-table .col-docno {
            width: 120px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }

        .report-table .col-rev {
            text-align: center;
            width: 50px;
        }

        .report-table .col-originator {
            width: 130px;
        }

        .report-table .col-effectivity {
            width: 100px;
            text-align: center;
        }

        .report-table .col-status {
            width: 70px;
            text-align: center;
        }

        .status-active {
            color: #059669;
            font-weight: 700;
        }

        /* Footer */
        .report-footer {
            margin-top: 20px;
            border-top: 2px solid #0d2a7a;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #666;
        }

        .report-footer .total-docs {
            font-weight: 700;
            color: #0d2a7a;
        }

        /* No print */
        .print-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #0d2a7a;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .print-toolbar button {
            background: #FFB800;
            color: #0d2a7a;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
        }

        .print-toolbar button:hover {
            background: #e6a600;
        }

        .print-toolbar .close-btn {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .print-toolbar .close-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .print-body {
            padding-top: 60px;
        }

        @media print {
            .print-toolbar { display: none !important; }
            .print-body { padding-top: 0; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="print-toolbar">
        <span style="font-weight:600;">{{ $title }}</span>
        <div style="display:flex; gap:8px;">
            <button class="close-btn" onclick="window.close()">Close</button>
            <button onclick="window.print()">
                <i style="margin-right:6px;"></i>Print / Save PDF
            </button>
        </div>
    </div>

    <div class="print-body">
        <!-- Header -->
        <div class="report-header">
            <div class="report-header-left">
                <img src="/images/logo.png" alt="Logo" class="report-logo">
                <div class="report-org-info">
                    <h2>Camarines Sur Polytechnic Colleges</h2>
                    <p>Document Control System</p>
                </div>
            </div>
            <div class="report-header-right">
                <div class="doc-ref">QMS-F-REG-001</div>
            </div>
        </div>

        <!-- Title Bar -->
        <div class="report-title-bar">
            <h1>{{ strtoupper($title) }}</h1>
        </div>

        <!-- Meta -->
        <div class="report-meta">
            <div>
                <strong>Generated by:</strong> {{ $generatedBy }}<br>
                <strong>Date &amp; Time:</strong> {{ now()->format('F d, Y — h:i A') }}
            </div>
            <div style="text-align:right;">
                <strong>Total Documents:</strong> {{ $rows->count() }}<br>
                <strong>Page:</strong> <span class="page-num"></span>
            </div>
        </div>

        <!-- Table -->
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width:40px;">Item #</th>
                    <th style="width:120px;">Document No.</th>
                    <th>Document Title</th>
                    <th style="width:50px;">Rev</th>
                    <th style="width:130px;">Originator</th>
                    <th style="width:100px;">Effectivity Date</th>
                    <th style="width:70px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="col-num">{{ $row['item_no'] }}</td>
                    <td class="col-docno">{{ $row['doc_no'] }}</td>
                    <td>{{ $row['title'] }}</td>
                    <td class="col-rev">{{ $row['rev_no'] }}</td>
                    <td class="col-originator">{{ $row['originator'] }}</td>
                    <td class="col-effectivity">{{ $row['effectivity'] }}</td>
                    <td class="col-status"><span class="status-active">{{ $row['status'] }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px; color:#999;">
                        No documents found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer -->
        <div class="report-footer">
            <div>
                <span class="total-docs">Total: {{ $rows->count() }} document(s)</span>
            </div>
            <div>
                CSPC — Document Control System &bull; ISO 9001:2015
            </div>
        </div>
    </div>

</body>
</html>