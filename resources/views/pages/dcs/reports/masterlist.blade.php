<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - DCS - Document Masterlist</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/reports.css', 'resources/css/dcs/sidebar.css', 'resources/js/dcs/sidebar.js', 'resources/js/dcs/reports.js'])
    <script>
        window.REPORT_CATEGORY = 'masterlist';
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
            <div class="rpt-crumb">Document Control System / Generate Report /<span> Document Masterlist</span></div>
            <h1>Document Masterlist</h1>
        </div>
    </header>

    <nav class="rpt-subs visible" id="subTabs" aria-label="Document types">
        <button class="rpt-sub" data-sub="internal_docs" type="button">Internal</button>
        <button class="rpt-sub" data-sub="external_docs" type="button">External</button>
        <button class="rpt-sub" data-sub="internal_forms" type="button">Internal Forms</button>
        <button class="rpt-sub" data-sub="forms" type="button">Forms</button>
        <button class="rpt-sub" data-sub="logbooks" type="button">Logbooks</button>
    </nav>

    @include('partials.report-filters-inline')
    @include('partials.report-preview-panel')

</main>
</body>
</html>
