<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - OPCR Targets</title>
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
                <div class="rpt-breadcrumb"><a href="{{ route('generate-report.report') }}">Reports</a> / OPCR Targets</div>
                <div class="rpt-title">OPCR Targets — PMT Report</div>
            </div>
            <a href="{{ route('generate-report.report') }}" class="rpt-btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back to Reports
            </a>
        </div>

        <div class="rpt-concept-grid">

            <div class="rpt-concept-card" data-accent="purple">
                <div class="rpt-concept-icon"><i class="fa-solid fa-bullseye"></i></div>
                <h3>Target Accomplishment Tracker</h3>
                <p>Set quarterly/annual targets for document processing KPIs (e.g., "Register 50 new documents per quarter") and track actual accomplishment against targets.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-percentage"></i> % Accomplished</span>
                    <span><i class="fa-solid fa-sliders"></i> Target vs Actual</span>
                    <span><i class="fa-solid fa-calendar-days"></i> Quarterly</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="blue">
                <div class="rpt-concept-icon"><i class="fa-solid fa-paperclip"></i></div>
                <h3>Evidence Attachment Manager</h3>
                <p>Attach scanned copies, screenshots, and supporting documents as evidence for each OPCR accomplishment. Auto-link to registered documents.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-file-arrow-up"></i> Upload evidence</span>
                    <span><i class="fa-solid fa-link"></i> Auto-link docs</span>
                    <span><i class="fa-solid fa-folder-tree"></i> Organize</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="green">
                <div class="rpt-concept-icon"><i class="fa-solid fa-file-contract"></i></div>
                <h3>OPCR Summary Report Generator</h3>
                <p>Generate the official OPCR summary report showing all targets, accomplishments, evidence status, and ratings in the prescribed format.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-file-pdf"></i> Official format</span>
                    <span><i class="fa-solid fa-star-half-stroke"></i> Ratings</span>
                    <span><i class="fa-solid fa-signature"></i> Sign-off</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

            <div class="rpt-concept-card" data-accent="slate">
                <div class="rpt-concept-icon"><i class="fa-solid fa-chart-column"></i></div>
                <h3>PMT Performance Dashboard</h3>
                <p>Visual dashboard showing overall PMT performance across all offices, with trend lines, comparison charts, and drill-down capability.</p>
                <div class="rpt-concept-features">
                    <span><i class="fa-solid fa-chart-line"></i> Trend lines</span>
                    <span><i class="fa-solid fa-layer-group"></i> Drill-down</span>
                    <span><i class="fa-solid fa-gauge-high"></i> Scorecards</span>
                </div>
                <div class="rpt-concept-status">
                    <span class="rpt-status-badge coming-soon"><i class="fa-solid fa-wrench"></i> Coming Soon</span>
                </div>
            </div>

        </div>
    </div>

</body>
</html>