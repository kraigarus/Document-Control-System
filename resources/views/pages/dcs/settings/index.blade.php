<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>Settings - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite([
        'resources/css/dcs/settings.css',
        'resources/js/dcs/settings.js'
    ])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="settings-main">
    <div class="settings-header">
        <div class="welcome-text">
            <p class="header-greeting">System Configuration</p>
            <h1 class="page-title">Settings</h1>
        </div>
    </div>

    <div class="settings-tabs">
        <button class="tab-btn active" data-tab="doctypes">
            <i class="fa-solid fa-tags"></i> Document Types
        </button>
        <button class="tab-btn" data-tab="offices">
            <i class="fa-solid fa-building"></i> Offices
        </button>
        <button class="tab-btn" data-tab="versiontypes">
            <i class="fa-solid fa-code-branch"></i> Version Types
        </button>
    </div>

    <!-- ══════════════ DOCUMENT TYPES ══════════════ -->
    <section class="tab-panel active" id="panel-doctypes">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Top-level types and their sub-types</span>
            <button class="btn-primary" onclick="openDocTypeModal()">
                <i class="fa-solid fa-plus"></i> Add Document Type
            </button>
        </div>

        <div class="doctype-list">
            @forelse($docTypes as $type)
                <div class="doctype-group" data-id="{{ $type->doc_type_id }}">
                    <div class="doctype-parent-row">
                        <div class="doctype-name">
                            <i class="fa-solid fa-folder"></i>
                            <span>{{ $type->doc_type_name }}</span>
                        </div>
                        <div class="row-actions">
                            <button class="icon-btn" title="Add sub-type" onclick="openDocTypeModal(null, {{ $type->doc_type_id }})">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <button class="icon-btn" title="Edit" onclick="openDocTypeModal({{ $type->doc_type_id }})" data-name="{{ $type->doc_type_name }}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteDocType({{ $type->doc_type_id }})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    @if($type->subTypes->count())
                        <div class="doctype-subtypes">
                            @foreach($type->subTypes as $sub)
                                <div class="doctype-sub-row" data-id="{{ $sub->doc_type_id }}">
                                    <div class="doctype-name">
                                        <i class="fa-solid fa-turn-up fa-rotate-90"></i>
                                        <span>{{ $sub->doc_type_name }}</span>
                                    </div>
                                    <div class="row-actions">
                                        <button class="icon-btn" title="Edit" onclick="openDocTypeModal({{ $sub->doc_type_id }})" data-name="{{ $sub->doc_type_name }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteDocType({{ $sub->doc_type_id }})">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    <i class="fa-solid fa-tags"></i>
                    <span>No document types yet. Add one to get started.</span>
                </div>
            @endforelse
        </div>
    </section>

    <!-- ══════════════ OFFICES ══════════════ -->
    <section class="tab-panel" id="panel-offices">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Inactive offices are hidden from all dropdowns (Register, Retrieval, Distribution)</span>
            <button class="btn-primary" onclick="openOfficeModal()">
                <i class="fa-solid fa-plus"></i> Add Office
            </button>
        </div>

        <table class="settings-table">
            <thead>
                <tr>
                    <th>Office Name</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="officesTableBody">
                @forelse($offices as $office)
                    <tr data-id="{{ $office->office_id }}">
                        <td>{{ $office->office_name }}</td>
                        <td>
                            <label class="status-toggle">
                                <input type="checkbox"
                                       {{ $office->status === 'active' ? 'checked' : '' }}
                                       onchange="toggleOfficeStatus({{ $office->office_id }}, this)">
                                <span class="toggle-track"></span>
                                <span class="toggle-label">{{ ucfirst($office->status) }}</span>
                            </label>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="icon-btn" title="Edit" onclick="openOfficeModal({{ $office->office_id }})" data-name="{{ $office->office_name }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteOffice({{ $office->office_id }})">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-cell">No offices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <!-- ══════════════ VERSION TYPES ══════════════ -->
    <section class="tab-panel" id="panel-versiontypes">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Document version classifications</span>
            <button class="btn-primary" onclick="openVersionTypeModal()">
                <i class="fa-solid fa-plus"></i> Add Version Type
            </button>
        </div>

        <table class="settings-table">
            <thead>
                <tr>
                    <th>Version Name</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="versionTypesTableBody">
                @forelse($versionTypes as $v)
                    <tr data-id="{{ $v->version_id }}">
                        <td>{{ $v->version_name }}</td>
                        <td>
                            <div class="row-actions">
                                <button class="icon-btn" title="Edit" onclick="openVersionTypeModal({{ $v->version_id }})" data-name="{{ $v->version_name }}">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteVersionType({{ $v->version_id }})">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty-cell">No version types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

</main>

<!-- ══════════════ SHARED MODAL ══════════════ -->
<div class="overlay" id="settingsOverlay">
    <div class="modal" id="settingsModalBox"></div>
</div>

<!-- Toast -->
<div id="settingsToast" class="settings-toast"></div>

</body>
</html>