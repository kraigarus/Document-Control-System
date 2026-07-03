<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>Revision History — CSPC DCS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    @vite(['resources/css/dcs/history.css', 'resources/css/dcs/register.css', 'resources/js/dcs/history.js'])
    @include('partials.header')
    @include('partials.sidebar')
    @include('partials.inactivity-modal')

    <div class="hst-container">
        <div class="reg-header">
            <div>
                <div class="reg-breadcrumb">Document Control System / Update / History</div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="reg-title">{{ $docTitle }}</div>
                    <span class="hst-badge"><i class="fa-solid fa-clock-rotate-left"></i> {{ $revisions->count() }} {{ Str::plural('revision', $revisions->count()) }}</span>
                </div>
            </div>
            <a href="{{ route('register.update') }}" class="reg-btn reg-btn-cancel">
                <i class="fa-solid fa-arrow-left"></i> Back to Documents
            </a>
        </div>

        <div class="hst-timeline">
            @foreach($revisions as $i => $rev)
                @php
                    $ml   = $rev->masterlistRegistration;
                    $drf  = $rev->documentRequestForm;
                    $dcn  = $rev->documentChangeNotice;
                    $isLatest = ($i === 0);
                    $checklists = [];
                    if ($drf) $checklists[] = 'DRF';
                    if ($dcn) $checklists[] = 'DCN';
                    if ($ml)  $checklists[] = 'ML';
                @endphp

                <div class="hst-node {{ $isLatest ? 'hst-node-current' : '' }}" style="animation-delay: {{ $i * 0.1 }}s">
                    <div class="hst-dot">
                        @if($isLatest)
                            <i class="fa-solid fa-circle-check"></i>
                        @else
                            <i class="fa-solid fa-circle"></i>
                        @endif
                    </div>
                    <div class="hst-card">
                        <div class="hst-card-header">
                            <span class="hst-rev-badge">Rev {{ $ml->revise_no ?? 0 }}</span>
                            @if($isLatest) <span class="hst-current-tag">Current</span> @endif
                            <span class="hst-date">{{ $rev->created_at ? $rev->created_at->format('M d, Y h:i A') : 'N/A' }}</span>
                        </div>
                        <div class="hst-card-body">
                            <div class="hst-info-grid">
                                @if($ml)
                                    <div class="hst-info-item">
                                        <span class="hst-info-label">Title</span>
                                        <span class="hst-info-value">{{ $ml->doc_title ?? 'N/A' }}</span>
                                    </div>
                                    <div class="hst-info-item">
                                        <span class="hst-info-label">Effectivity</span>
                                        <span class="hst-info-value">{{ $ml->effectivity_date ? \Carbon\Carbon::parse($ml->effectivity_date)->format('M d, Y') : 'N/A' }}</span>
                                    </div>
                                    <div class="hst-info-item">
                                        <span class="hst-info-label">Pages</span>
                                        <span class="hst-info-value">{{ $ml->no_pages ?? 'N/A' }}</span>
                                    </div>
                                    <div class="hst-info-item">
                                        <span class="hst-info-label">In-charge</span>
                                        <span class="hst-info-value">{{ $ml->in_charge ?? 'N/A' }}</span>
                                    </div>
                                    <div class="hst-info-item">
                                        <span class="hst-info-label">Originator</span>
                                        <span class="hst-info-value">{{ $ml->originator_name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="hst-info-item">
                                        <span class="hst-info-label">Purpose</span>
                                        <span class="hst-info-value">{{ $ml->brief_purpose ?? 'N/A' }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="hst-tags">
                                @foreach($checklists as $cl)
                                    <span class="hst-tag">{{ $cl }}</span>
                                @endforeach
                            </div>
                        </div>
                        @if($isLatest)
                            <div class="hst-card-actions">
                                <a href="{{ route('register.edit', $rev->request_id) }}" class="hst-btn-edit">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit This Revision
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>