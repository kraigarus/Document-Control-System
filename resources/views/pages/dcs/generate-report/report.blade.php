<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Generate Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite([
        'resources/css/dcs/reports.css',
        'resources/js/dcs/reports.js'
    ])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

    <div class="rpt-container">
        <div class="rpt-header">
            <div>
                <div class="rpt-breadcrumb">Document Control System / Reports</div>
                <div class="rpt-title">Generate Reports</div>
            </div>
        </div>

        <div class="rpt-subtitle">Select a report type to get started</div>

        <!-- Report Type Cards -->
        <div class="rpt-type-grid">

            <a href="{{ route('generate-report.masterlist') }}" class="rpt-type-card" data-accent="blue">
                <div class="rpt-type-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div class="rpt-type-body">
                    <h3>Masterlists</h3>
                    <p>Generate official ISO 9001 masterlist reports with filters, categories, and document selection. Export as PDF, Excel, or print.</p>
                    <div class="rpt-type-tags">
                        <span class="rpt-type-tag">Filters</span>
                        <span class="rpt-type-tag">PDF</span>
                        <span class="rpt-type-tag">Excel</span>
                        <span class="rpt-type-tag">Print</span>
                    </div>
                </div>
                <div class="rpt-type-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <a href="{{ route('generate-report.monitoring') }}" class="rpt-type-card" data-accent="green">
                <div class="rpt-type-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="rpt-type-body">
                    <h3>Monitoring Reports</h3>
                    <p>Track and monitor document processing timelines, time spent metrics, and turnaround performance across all sections.</p>
                    <div class="rpt-type-tags">
                        <span class="rpt-type-tag">Timeline</span>
                        <span class="rpt-type-tag">Metrics</span>
                        <span class="rpt-type-tag">Charts</span>
                    </div>
                </div>
                <div class="rpt-type-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <a href="{{ route('generate-report.opcr') }}" class="rpt-type-card" data-accent="purple">
                <div class="rpt-type-icon">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
                <div class="rpt-type-body">
                    <h3>OPCR Targets</h3>
                    <p>PMT Report Accomplishment Evidence — generate performance monitoring and target accomplishment reports with supporting evidence.</p>
                    <div class="rpt-type-tags">
                        <span class="rpt-type-tag">Targets</span>
                        <span class="rpt-type-tag">Evidence</span>
                        <span class="rpt-type-tag">PMT</span>
                    </div>
                </div>
                <div class="rpt-type-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

            <a href="{{ route('generate-report.other') }}" class="rpt-type-card" data-accent="slate">
                <div class="rpt-type-icon">
                    <i class="fa-solid fa-ellipsis"></i>
                </div>
                <div class="rpt-type-body">
                    <h3>Other Reports</h3>
                    <p>Generate custom reports, summaries, and additional document analytics. Build your own report configurations.</p>
                    <div class="rpt-type-tags">
                        <span class="rpt-type-tag">Custom</span>
                        <span class="rpt-type-tag">Summary</span>
                        <span class="rpt-type-tag">Analytics</span>
                    </div>
                </div>
                <div class="rpt-type-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </a>

        </div>
    </div>

</body>
</html>