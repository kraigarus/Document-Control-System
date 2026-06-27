<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Other Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css'])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

    <div class="rpt-container">
        <div class="rpt-header">
            <div>
                <div class="rpt-breadcrumb"><a href="{{ route('generate-report.report') }}">Reports</a> / Other</div>
                <div class="rpt-title">Other Reports</div>
            </div>
            <a href="{{ route('generate-report.report') }}" class="rpt-btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Reports
            </a>
        </div>

        <div class="rpt-concept-grid">

            <div class="rpt-concept-card" data-accent="blue">
                <div class="rpt-concept-icon"><i class="fa-solid fa-file-lines"></i></div>
                <h3>Document Summary by Type</h3>
                <p>Count and list documents grouped by document type — Internal, External, Forms, Logbooks — with drill-down into each category.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-chart-pie"></i> Pie chart</span>
                    <span><i class="fa-solid fa-layer-group"></i> Grouped</span>
                    <span><i class="fa-solid fa-download"></i> Export</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="green">
                <div class="rpt-concept-icon"><i class="fa-solid fa-building"></i></div>
                <h3>Office Activity Log</h3>
                <p>Report showing which offices are the most active originators, most frequent receivers, and their document processing patterns.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-building-columns"></i> By office</span>
                    <span><i class="fa-solid fa-arrows-left-right"></i> Send/receive</span>
                    <span><i class="fa-solid fa-calendar"></i> Date range</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="amber">
                <div class="rpt-concept-icon"><i class="fa-solid fa-user-shield"></i></div>
                <h3>User Activity Report</h3>
                <p>Track who created, updated, and managed each document. Useful for audit trails and accountability reports.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-user"></i> By user</span>
                    <span><i class="fa-solid fa-clock-rotate-left"></i> Audit trail</span>
                    <span><i class="fa-solid fa-shield-halved"></i> Compliance</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="slate">
                <div class="rpt-concept-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <h3>Custom Report Builder</h3>
                <p>Build your own reports by selecting columns, filters, grouping, and sorting. Save report templates for reuse.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-table-columns"></i> Pick columns</span>
                    <span><i class="fa-solid fa-floppy-disk"></i> Save templates</span>
                    <span><i class="fa-solid fa-sliders"></i> Custom filters</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

        </div>
    </div>

</body>
</html>