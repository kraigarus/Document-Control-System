<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Monitoring Reports</title>
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
                <div class="rpt-breadcrumb"><a href="{{ route('generate-report.report') }}">Reports</a> / Monitoring</div>
                <div class="rpt-title">Monitoring Reports</div>
            </div>
            <a href="{{ route('generate-report.report') }}" class="rpt-btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Reports
            </a>
        </div>

        <!-- Concept Cards -->
        <div class="rpt-concept-grid">

            <div class="rpt-concept-card" data-accent="blue">
                <div class="rpt-concept-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <h3>Document Processing Timeline</h3>
                <p>Track how long each document takes through DRF → DCN → Masterlist → Retrieval → Distribution pipeline. Identify bottlenecks.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-chart-gantt"></i> Gantt timeline</span>
                    <span><i class="fa-solid fa-stopwatch"></i> Time metrics</span>
                    <span><i class="fa-solid fa-triangle-exclamation"></i> Overdue alerts</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="green">
                <div class="rpt-concept-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <h3>Processing Performance Summary</h3>
                <p>Dashboard-style overview showing average processing times, fastest/slowest documents, and office-level performance metrics.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-chart-bar"></i> Bar charts</span>
                    <span><i class="fa-solid fa-ranking-star"></i> Rankings</span>
                    <span><i class="fa-solid fa-calendar-week"></i> Date range</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="amber">
                <div class="rpt-concept-icon"><i class="fa-solid fa-building"></i></div>
                <h3>Office Distribution Report</h3>
                <p>Analyze which offices receive the most documents, copy distribution patterns, and retrieval frequency across departments.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-map"></i> Office breakdown</span>
                    <span><i class="fa-solid fa-copy"></i> Copy counts</span>
                    <span><i class="fa-solid fa-arrow-trend-up"></i> Trends</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="red">
                <div class="rpt-concept-icon"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                <h3>Overdue & Pending Documents</h3>
                <p>Documents that exceeded processing deadlines, are missing required sections, or have incomplete checklist items.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-bell"></i> Alerts</span>
                    <span><i class="fa-solid fa-list-check"></i> Checklist gaps</span>
                    <span><i class="fa-solid fa-user-clock"></i> Assignees</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

        </div>
    </div>

</body>
</html>