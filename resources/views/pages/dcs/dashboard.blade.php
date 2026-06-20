<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite([
        'resources/css/dcs/dashboard.css', 
        'resources/js/dcs/dashboard.js', 
        'resources/js/dcs/calendar.js'
    ])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="dashboard-main">
    <div class="dashboard-header">
        <div class="welcome-text">
            <p class="header-greeting">Welcome back, {{ auth()->user()?->name ?? 'User' }}</p>
            <h1 class="page-title">Document Control System</h1>
        </div>
        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <span id="headerDate"></span>
        </div>
    </div>

    <div class="dashboard-content-wrapper">
        <div class="main-column">

            <!-- Stats Row -->
            <section class="stats-row">
                <div class="stat-box" data-accent="blue">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-file-shield"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-title">Total Internal Documents</p>
                        <h3 class="stat-value" id="internalCount">0</h3>
                    </div>
                    <div class="stat-trend up">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>

                <div class="stat-box" data-accent="slate">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-file-contract"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-title">Total Internal Forms</p>
                        <h3 class="stat-value" id="internalFormsCount">0</h3>
                    </div>
                    <div class="stat-trend up">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>

                <div class="stat-box" data-accent="navy">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-file-export"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-title">Total External Documents</p>
                        <h3 class="stat-value" id="externalCount">0</h3>
                    </div>
                    <div class="stat-trend down">
                        <i class="fa-solid fa-arrow-trend-down"></i>
                    </div>
                </div>

                <div class="stat-box" data-accent="red">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-title">Total Forms</p>
                        <h3 class="stat-value" id="formsCount">0</h3>
                    </div>
                    <div class="stat-trend up">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>

                <div class="stat-box" data-accent="green">
                    <div class="stat-icon-wrap">
                        <i class="fa-solid fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-title">Total Logbooks</p>
                        <h3 class="stat-value" id="logbooksCount">0</h3>
                    </div>
                    <div class="stat-trend up">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                </div>
            </section>

            <!-- Quick Actions -->
            <section class="actions-section">
                <div class="section-header">
                    <h2>Quick Actions</h2>
                    <span class="section-subtitle">Frequently used operations</span>
                </div>
                <div class="actions-row">
                    <a href="/register" class="action-box">
                        <div class="action-icon-wrap">
                            <i class="fa-solid fa-file-circle-plus"></i>
                        </div>
                        <div class="action-content">
                            <h4>Register New Document</h4>
                            <p>Create and route initial document draft</p>
                        </div>
                        <i class="fa-solid fa-arrow-right action-arrow"></i>
                    </a>
                    <a href="/register?type=revised" class="action-box">
                        <div class="action-icon-wrap">
                            <i class="fa-solid fa-file-pen"></i>
                        </div>
                        <div class="action-content">
                            <h4>Register Revised Document</h4>
                            <p>Upload new version for approval</p>
                        </div>
                        <i class="fa-solid fa-arrow-right action-arrow"></i>
                    </a>
                    <a href="/register/update" class="action-box">
                        <div class="action-icon-wrap">
                            <i class="fa-solid fa-rotate"></i>
                        </div>
                        <div class="action-content">
                            <h4>Update Document</h4>
                            <p>Modify metadata or access permissions</p>
                        </div>
                        <i class="fa-solid fa-arrow-right action-arrow"></i>
                    </a>
                </div>
            </section>

        </div>

        <div class="side-column">
            <!-- Calendar Widget -->
            <div class="widget calendar-widget white-card">
                <div class="calendar-header">
                    <h3 id="calTitle"></h3>
                    <div class="cal-nav">
                        <button onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                        <button onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>

                <div class="weekdays">
                    <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
                </div>

                <div id="calGrid" class="calendar-grid"></div>

                <div class="cal-legend">
                    <span class="legend-item">
                        <span class="dot holiday-dot"></span> Holiday
                    </span>
                    <span class="legend-item">
                        <span class="dot event-dot"></span> Event
                    </span>
                    <button onclick="openAddModal(todayISO())" class="btn-mini">+ Add Event</button>
                </div>
            </div>

            <!-- Upcoming Events Widget -->
            <div class="widget upcoming-widget white-card">
                <div class="widget-header">
                    <h3>Upcoming</h3>
                    <span class="badge" id="upcomingCount">0</span>
                </div>
                <div class="upcoming-list" id="upcomingList">
                    <div class="upcoming-empty">
                        <i class="fa-regular fa-calendar-check"></i>
                        <span>No events today or tomorrow</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<div class="overlay" id="overlay">
    <div class="modal" id="modalBox"></div>
</div>


</body>
</html>