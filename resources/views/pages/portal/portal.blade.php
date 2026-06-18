<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>RMS - Portal</title>
    @vite(['resources/css/portal.css'])
    <style>
        .portal-container {
            background: url('/images/background.png') center/cover no-repeat;
        }
    </style>
</head>
<body>
    <header class="top-bar">
        RECORDS AND FREEDOM OF INFORMATION OFFICE
    </header>

    <div class="portal-container">
        <div class="overlay"></div>
        <div class="content-wrapper">
            <div class="logo-main">
                <img src="/images/logo.png" alt="logo">
            </div>
            <div class="menu-container">
                <a href="#" class="menu-btn">Document Tracking</a>
                <a href="#" class="menu-btn">Records Disposition Program</a>
                <a href="/login" class="menu-btn">Document Control</a>
                <a href="#" class="menu-btn">Administration Access</a>
            </div>
        </div>
    </div>

    <footer class="footer-bar">
        &copy; Copyright 2026. All Rights Reserved.
    </footer>
</body>
</html>
