<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System - OPCR Targets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css', 'resources/css/dcs/sidebar.css', 'resources/js/dcs/sidebar.js', 'resources/js/dcs/reports.js'])
    <script>
        window.REPORT_CATEGORY = 'opcr';
        window.REPORT_DOC_TYPES = @json($allDocTypes ?? []);
    </script>
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="rpt-page" id="rptPage">

    <header class="rpt-hdr">
        <div>
            <div class="rpt-crumb">Document Control System / Generate Report / <span>OPCR Targets</span></div>
            <h1>OPCR Targets</h1>
        </div>
    </header>

    <nav class="rpt-subs visible" id="subTabs" aria-label="OPCR categories">
        <button class="rpt-sub" data-sub="update_masterlist" type="button">Updating of Masterlist</button>
        <button class="rpt-sub" data-sub="issuance_internal" type="button">Issuance of Internal</button>
        <button class="rpt-sub" data-sub="issuance_external" type="button">Issuance of External</button>
        <button class="rpt-sub" data-sub="control_forms" type="button">Controlling of Forms</button>
        <button class="rpt-sub" data-sub="control_logbooks" type="button">Controlling of Logbooks</button>
        <button class="rpt-sub" data-sub="control_internal_forms" type="button">Controlling of Internal Forms</button>
    </nav>

    @include('partials.report-filters-inline')
    @include('partials.report-preview-panel')

</main>
</body>
</html>
