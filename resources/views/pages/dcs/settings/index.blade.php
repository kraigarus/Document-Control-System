<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>CSPC - Document Control System</title>
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
        <div class="settings-header-left">
            <div class="settings-breadcrumb">Document Control System / <span>Settings</span></div>
            <h1>Settings</h1>
        </div>
    </div>

    <div class="settings-tabs">
        <button class="tab-btn" data-tab="versiontypes">
            <i class="fa-solid fa-code-branch"></i> Version Types
        </button>
        <button class="tab-btn active" data-tab="doctypes">
            <i class="fa-solid fa-tags"></i> Document Types
        </button>
        <button class="tab-btn" data-tab="offices">
            <i class="fa-solid fa-building"></i> Offices
        </button>
        <button class="tab-btn" data-tab="originators">
            <i class="fa-solid fa-user-pen"></i> Originators
        </button>
        <button class="tab-btn" data-tab="colleges">
            <i class="fa-solid fa-graduation-cap"></i> Colleges
        </button>
        <button class="tab-btn" data-tab="programs">
            <i class="fa-solid fa-book-open"></i> Programs
        </button>
        <button class="tab-btn" data-tab="semesters">
            <i class="fa-solid fa-calendar-week"></i> Semesters
        </button>
        <button class="tab-btn" data-tab="schoolyears">
            <i class="fa-solid fa-calendar-days"></i> School Years
        </button>
        <button class="tab-btn" data-tab="coursenames">
            <i class="fa-solid fa-list-check"></i> Course Names
        </button>
    </div>

    <!-- ══════════════ VERSION TYPES ══════════════ -->
    <section class="tab-panel" id="panel-versiontypes">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Document version classifications</span>
            <button class="btn-primary" onclick="openVersionTypeModal()">
                <i class="fa-solid fa-plus"></i> Add Version Type
            </button>
        </div>

        <div class="table-wrap">
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
        </div>
    </section>

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

        <div class="table-wrap">
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
                            <td data-label="Office">{{ $office->office_name }}</td>
                            <td data-label="Status">
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
        </div>
    </section>

    <!-- ══════════════ ORIGINATORS ══════════════ -->
    <section class="tab-panel" id="panel-originators">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Manage document originators (authors/creators)</span>
            <button class="btn-primary" onclick="openOriginatorModal()">
                <i class="fa-solid fa-plus"></i> Add Originator
            </button>
        </div>

        <div class="table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>Originator Name</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="originatorsTableBody">
                    @forelse($originators as $orig)
                        <tr data-id="{{ $orig->originator_id }}">
                            <td data-label="Originator">{{ $orig->originator_name }}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" title="Edit" onclick="openOriginatorModal({{ $orig->originator_id }})" data-name="{{ $orig->originator_name }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteOriginator({{ $orig->originator_id }})">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="empty-cell">No originators yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- ══════════════ COLLEGES ══════════════ -->
    <section class="tab-panel" id="panel-colleges">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Manage colleges / departments</span>
            <button class="btn-primary" onclick="openCollegeModal()">
                <i class="fa-solid fa-plus"></i> Add College
            </button>
        </div>

        <div class="table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>College Name</th>
                        <th style="width:100px;">Programs</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="collegesTableBody">
                    @forelse($colleges as $college)
                        <tr data-id="{{ $college->college_id }}">
                            <td data-label="College">{{ $college->college_name }}</td>
                            <td data-label="Programs">{{ $college->programs->count() }}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" title="Edit"
                                            data-name="{{ $college->college_name }}"
                                            onclick="openCollegeModal({{ $college->college_id }})">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="icon-btn icon-btn-danger" title="Delete"
                                            onclick="deleteCollege({{ $college->college_id }})">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-cell">No colleges yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- ══════════════ PROGRAMS ══════════════ -->
    <section class="tab-panel" id="panel-programs">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Manage programs under each college</span>
            <button class="btn-primary" onclick="openProgramModal()">
                <i class="fa-solid fa-plus"></i> Add Program
            </button>
        </div>

        <div class="table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>College</th>
                        <th>Program Name</th>
                        <th style="width:100px;">Code</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="programsTableBody">
                    @forelse($colleges as $college)
                        <tr class="college-group-header">
                            <td colspan="4">
                                <i class="fa-solid fa-graduation-cap"></i>
                                {{ $college->college_name }}
                                <span class="program-count">({{ $college->programs->count() }} {{ Str::plural('program', $college->programs->count()) }})</span>
                            </td>
                        </tr>
                        @forelse($college->programs as $prog)
                            <tr data-id="{{ $prog->program_id }}">
                                <td data-label="College">{{ $college->college_name }}</td>
                                <td data-label="Program">{{ $prog->program_name }}</td>
                                <td data-label="Code">{{ $prog->program_code ?? '—' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <button class="icon-btn" title="Edit"
                                                onclick="openProgramModal({{ $prog->program_id }})"
                                                data-college="{{ $prog->college_id }}"
                                                data-name="{{ $prog->program_name }}"
                                                data-code="{{ $prog->program_code }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button class="icon-btn icon-btn-danger" title="Delete"
                                                onclick="deleteProgram({{ $prog->program_id }})">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="college-group-empty">
                                <td colspan="4" class="empty-cell">No programs under this college yet.</td>
                            </tr>
                        @endforelse
                    @empty
                        <tr><td colspan="4" class="empty-cell">No colleges or programs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- ══════════════ SEMESTERS ══════════════ -->
    <section class="tab-panel" id="panel-semesters">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Academic semesters</span>
            <button class="btn-primary" onclick="openSemesterModal()">
                <i class="fa-solid fa-plus"></i> Add Semester
            </button>
        </div>

        <div class="table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>Semester Name</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="semestersTableBody">
                    @forelse($semesters as $sem)
                        <tr data-id="{{ $sem->semester_id }}">
                            <td data-label="Semester">{{ $sem->semester_name }}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" title="Edit" onclick="openSemesterModal({{ $sem->semester_id }})" data-name="{{ $sem->semester_name }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteSemester({{ $sem->semester_id }})">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="empty-cell">No semesters yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- ══════════════ SCHOOL YEARS ══════════════ -->
    <section class="tab-panel" id="panel-schoolyears">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Academic school years</span>
            <button class="btn-primary" onclick="openSchoolYearModal()">
                <i class="fa-solid fa-plus"></i> Add School Year
            </button>
        </div>

        <div class="table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="schoolYearsTableBody">
                    @forelse($schoolYears as $sy)
                        <tr data-id="{{ $sy->school_year_id }}">
                            <td data-label="School Year">{{ $sy->school_year }}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" title="Edit" onclick="openSchoolYearModal({{ $sy->school_year_id }})" data-name="{{ $sy->school_year }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteSchoolYear({{ $sy->school_year_id }})">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="empty-cell">No school years yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- ══════════════ COURSE NAMES ══════════════ -->
    <section class="tab-panel" id="panel-coursenames">
        <div class="panel-toolbar">
            <span class="panel-subtitle">Curriculum course list per program and semester — used to auto-fill Syllabi/TOS-Rubrics registration</span>
            <button class="btn-primary" onclick="openProgramCourseModal()">
                <i class="fa-solid fa-plus"></i> Add Course
            </button>
        </div>

        <div class="table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>College</th>
                        <th>Program</th>
                        <th>Semester</th>
                        <th>Course Name</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="programCoursesTableBody">
                    @forelse($programCourses as $course)
                        <tr data-id="{{ $course->course_id }}">
                            <td data-label="College">{{ $course->program->college->college_name ?? '—' }}</td>
                            <td data-label="Program">{{ $course->program->program_name ?? '—' }}</td>
                            <td data-label="Semester">{{ $course->semester->semester_name ?? '—' }}</td>
                            <td data-label="Course Name">{{ $course->course_name }}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" title="Edit"
                                            onclick="openProgramCourseModal({{ $course->course_id }})"
                                            data-program="{{ $course->program_id }}"
                                            data-semester="{{ $course->semester_id }}"
                                            data-name="{{ $course->course_name }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteProgramCourse({{ $course->course_id }})">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No courses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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