<?php

use App\Helpers\RegisterQueryHelper;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.dcs')] #[Title('CSPC - Document Control System')] class extends Component {
    public function with(): array
    {
        $visibleIds = RegisterQueryHelper::visibleRequestIds() ?: [0];
        $typeIds = RegisterQueryHelper::parentTypeIdMap();
        $base = fn () => DB::table('dcs_document_requests')
            ->whereIn('id', $visibleIds)
            ->whereIn('approval_status', ['applicable', 'not_applicable']);

        $stats = [
            'totalDocuments' => $base()->count(),
            'internalCount' => $base()->where('doc_type_id', $typeIds['internal_docs'])->count(),
            'internalFormsCount' => $base()->where('doc_type_id', $typeIds['internal_forms'])->count(),
            'externalCount' => $base()->where('doc_type_id', $typeIds['external_docs'])->count(),
            'formsCount' => $base()->where('doc_type_id', $typeIds['forms'])->count(),
            'logbooksCount' => $base()->where('doc_type_id', $typeIds['logbooks'])->count(),
        ];

        return [
            'stats' => $stats,
            'headerDate' => now('Asia/Manila')->format('l, F j, Y'),
            'holidays' => [
                '2026-01-01' => "New Year's Day",
                '2026-04-02' => 'Maundy Thursday',
                '2026-04-03' => 'Good Friday',
                '2026-04-09' => 'The Day of Valor',
                '2026-05-01' => 'Labor Day',
                '2026-06-12' => 'Independence Day',
                '2026-08-21' => 'Ninoy Aquino Day',
                '2026-08-31' => 'National Heroes Day',
                '2026-11-01' => "All Saints' Day",
                '2026-11-30' => 'Bonifacio Day',
                '2026-12-25' => 'Christmas Day',
                '2026-12-30' => 'Rizal Day',
            ],
        ];
    }
}; ?>

<main class="dashboard-main" x-data="dcsDashboardCalendar()">
    <div class="dashboard-header">
        <div class="welcome-text">
            <p class="header-greeting">Welcome back, {{ auth()->user()?->name ?: 'User' }}</p>
            <h1 class="page-title">Document Control System</h1>
        </div>
        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <span>{{ $headerDate }}</span>
        </div>
    </div>

    <div class="dashboard-content-wrapper">
        <div class="main-column">
            <section class="stats-row">
                @foreach([
                    ['id' => 'internalCount', 'label' => 'Total Internal Documents', 'icon' => 'fa-file-shield', 'accent' => null],
                    ['id' => 'internalFormsCount', 'label' => 'Total Internal Forms', 'icon' => 'fa-file-contract', 'accent' => 'slate'],
                    ['id' => 'externalCount', 'label' => 'Total External Documents', 'icon' => 'fa-file-export', 'accent' => 'navy'],
                    ['id' => 'formsCount', 'label' => 'Total Forms', 'icon' => 'fa-file-signature', 'accent' => 'red'],
                    ['id' => 'logbooksCount', 'label' => 'Total Logbooks', 'icon' => 'fa-book', 'accent' => 'green'],
                ] as $box)
                    @php $count = (int) ($stats[$box['id']] ?? 0); @endphp
                    <div class="stat-box" @if($box['accent']) data-accent="{{ $box['accent'] }}" @endif>
                        <div class="stat-icon-wrap"><i class="fa-solid {{ $box['icon'] }}"></i></div>
                        <div class="stat-body">
                            <p class="stat-label">{{ $box['label'] }}</p>
                            <div class="stat-number-row">
                                <h3 class="stat-value">{{ number_format($count) }}</h3>
                                <div class="stat-trend {{ $count > 0 ? 'up' : 'neutral' }}">
                                    <i class="fa-solid {{ $count > 0 ? 'fa-arrow-trend-up' : 'fa-minus' }}"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="actions-section">
                <div class="section-header">
                    <h2>Quick Actions</h2>
                    <span class="section-subtitle">Frequently used operations</span>
                </div>
                <div class="actions-row">
                    <a href="{{ route('dcs.register.create', absolute: false) }}" class="action-box">
                        <div class="action-icon-wrap"><i class="fa-solid fa-file-circle-plus"></i></div>
                        <div class="action-content">
                            <h4>Register New Document</h4>
                            <p>Create and route initial document draft</p>
                        </div>
                        <i class="fa-solid fa-arrow-right action-arrow"></i>
                    </a>
                    <a href="{{ route('dcs.register.create', ['type' => 'revised'], absolute: false) }}" class="action-box">
                        <div class="action-icon-wrap"><i class="fa-solid fa-file-pen"></i></div>
                        <div class="action-content">
                            <h4>Register Revised Document</h4>
                            <p>Upload new version for approval</p>
                        </div>
                        <i class="fa-solid fa-arrow-right action-arrow"></i>
                    </a>
                    <a href="{{ route('dcs.register.update', absolute: false) }}" class="action-box">
                        <div class="action-icon-wrap"><i class="fa-solid fa-rotate"></i></div>
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
            <div class="widget calendar-widget white-card">
                <div class="calendar-header">
                    <h3 x-text="title"></h3>
                    <div class="cal-nav">
                        <button type="button" x-on:click="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                        <button type="button" x-on:click="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>
                <div class="weekdays">
                    <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
                </div>
                <div class="calendar-grid">
                    <template x-for="cell in cells" :key="cell.iso + cell.outside">
                        <div class="cal-cell" :class="{ 'out-month': cell.outside }" x-on:click="openDay(cell.iso)">
                            <div class="day-num" :class="{ today: cell.today, holiday: cell.holiday }" x-text="cell.day"></div>
                            <div class="day-markers">
                                <span class="day-marker holiday" x-show="cell.holiday"></span>
                                <template x-for="color in cell.colors" :key="color">
                                    <span class="day-marker event" :style="'background-color:' + color"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="cal-legend">
                    <span class="legend-item"><span class="dot holiday-dot"></span> Holiday</span>
                    <span class="legend-item"><span class="dot event-dot"></span> Event</span>
                    <button type="button" class="btn-mini" x-on:click="openAdd(todayIso())">+ Add Event</button>
                </div>
            </div>

            <div class="widget upcoming-widget white-card">
                <div class="widget-header">
                    <h3>Upcoming</h3>
                    <span class="badge" x-text="upcoming.length"></span>
                </div>
                <div class="upcoming-list">
                    <template x-if="upcoming.length === 0">
                        <div class="upcoming-empty">
                            <i class="fa-regular fa-calendar-check"></i>
                            <span>No events today or tomorrow</span>
                        </div>
                    </template>
                    <template x-for="ev in upcoming" :key="ev.id">
                        <div class="upcoming-item" x-on:click="openDay(ev.date)">
                            <div class="upcoming-event-dot" :class="{ 'today-accent': ev.when === 'today' }"></div>
                            <div class="upcoming-info">
                                <div class="title" x-text="ev.title"></div>
                                <div class="time" x-text="formatTime(ev.startTime) + ' — ' + formatTime(ev.endTime)"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="overlay" x-show="modal !== null" x-cloak x-on:click.self="modal = null" style="display:none;" :style="modal ? 'display:flex' : 'display:none'">
        <div class="modal" x-on:click.stop>
            <template x-if="modal === 'day'">
                <div class="ev-modal">
                    <div class="ev-modal-top">
                        <div class="ev-modal-icon is-view"><i class="fa-regular fa-calendar"></i></div>
                        <button type="button" class="ev-modal-close" x-on:click="modal = null"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <h3 class="ev-modal-title" x-text="displayDate(activeIso)"></h3>
                    <p class="ev-modal-desc" x-text="dayEvents.length + ' event' + (dayEvents.length === 1 ? '' : 's') + ' scheduled'"></p>
                    <div class="ev-holiday" x-show="holidayName(activeIso)">
                        <i class="fa-solid fa-umbrella-beach"></i><span x-text="holidayName(activeIso)"></span>
                    </div>
                    <div class="ev-card-list">
                        <template x-if="dayEvents.length === 0">
                            <div class="ev-empty"><i class="fa-regular fa-calendar"></i><span>No events scheduled</span></div>
                        </template>
                        <template x-for="ev in dayEvents" :key="ev.id">
                            <div class="ev-card">
                                <div class="ev-card-color" :style="'background:' + (ev.color || '#0d2a7a')"></div>
                                <div class="ev-card-body">
                                    <div class="ev-card-top">
                                        <div class="ev-card-title" x-text="ev.title"></div>
                                        <div class="ev-card-btns">
                                            <button type="button" class="ev-card-btn" x-on:click="openEdit(ev.id)"><i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="ev-card-btn ev-card-btn-danger" x-on:click="removeEvent(ev.id)"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </div>
                                    <div class="ev-card-time">
                                        <i class="fa-regular fa-clock"></i>
                                        <span x-text="formatTime(ev.startTime) + ' — ' + formatTime(ev.endTime)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="ev-actions-row">
                        <button type="button" class="ev-btn ev-btn-primary" style="width:100%;" x-on:click="openAdd(activeIso)">
                            <i class="fa-solid fa-plus"></i> Add Event
                        </button>
                    </div>
                </div>
            </template>
            <template x-if="modal === 'form'">
                <div class="ev-modal">
                    <div class="ev-modal-top">
                        <div class="ev-modal-icon" :class="editingId ? 'is-edit' : 'is-add'">
                            <i class="fa-solid" :class="editingId ? 'fa-pen' : 'fa-plus'"></i>
                        </div>
                        <button type="button" class="ev-modal-close" x-on:click="openDay(form.date)"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <h3 class="ev-modal-title" x-text="editingId ? 'Edit Event' : 'New Event'"></h3>
                    <p class="ev-modal-desc" x-text="displayDate(form.date)"></p>
                    <form @submit.prevent="saveForm">
                        <div class="ev-field">
                            <label class="ev-label">Title</label>
                            <input class="ev-input" x-model="form.title" required autocomplete="off">
                        </div>
                        <div class="ev-field">
                            <label class="ev-label">Date</label>
                            <input type="date" class="ev-input" x-model="form.date" required>
                        </div>
                        <div class="ev-time-row">
                            <div class="ev-field ev-field-half">
                                <label class="ev-label">Start</label>
                                <input type="time" class="ev-input" x-model="form.startTime" required>
                            </div>
                            <div class="ev-field ev-field-half">
                                <label class="ev-label">End</label>
                                <input type="time" class="ev-input" x-model="form.endTime" required>
                            </div>
                        </div>
                        <div class="ev-field">
                            <label class="ev-label">Notes <span class="ev-optional">Optional</span></label>
                            <textarea class="ev-textarea" rows="2" x-model="form.description"></textarea>
                        </div>
                        <div class="ev-actions-row">
                            <button type="button" class="ev-btn ev-btn-ghost" x-on:click="openDay(form.date)">Cancel</button>
                            <button type="submit" class="ev-btn ev-btn-primary">
                                <i class="fa-solid" :class="editingId ? 'fa-check' : 'fa-plus'"></i>
                                <span x-text="editingId ? 'Save' : 'Create'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </div>
</main>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dcsDashboardCalendar', () => ({
        months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        holidays: @json($holidays),
        year: new Date().getFullYear(),
        month: new Date().getMonth(),
        events: [],
        modal: null,
        activeIso: '',
        editingId: null,
        form: { title: '', date: '', startTime: '09:00', endTime: '10:00', description: '' },
        init() {
            this.events = JSON.parse(localStorage.getItem('calendarEvents') || '[]');
        },
        get title() { return this.months[this.month] + ' ' + this.year; },
        pad(n) { return String(n).padStart(2, '0'); },
        todayIso() {
            const t = new Date();
            return `${t.getFullYear()}-${this.pad(t.getMonth() + 1)}-${this.pad(t.getDate())}`;
        },
        toIso(y, m, d) { return `${y}-${this.pad(m + 1)}-${this.pad(d)}`; },
        occursOn(ev, iso) { return ev.date === iso; },
        holidayName(iso) { return this.holidays[iso] || ''; },
        formatTime(time24) {
            if (!time24) return '';
            const [h, m] = time24.split(':').map(Number);
            return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
        },
        displayDate(iso) {
            return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        },
        persist() { localStorage.setItem('calendarEvents', JSON.stringify(this.events)); },
        changeMonth(dir) {
            this.month += dir;
            if (this.month > 11) { this.month = 0; this.year += 1; }
            else if (this.month < 0) { this.month = 11; this.year -= 1; }
        },
        get cells() {
            const first = new Date(this.year, this.month, 1).getDay();
            const days = new Date(this.year, this.month + 1, 0).getDate();
            const prevDays = new Date(this.year, this.month, 0).getDate();
            const today = this.todayIso();
            const out = [];
            for (let i = 0; i < 42; i++) {
                const offset = i - first + 1;
                let day = offset, m = this.month, y = this.year, outside = false;
                if (offset < 1) { day = prevDays + offset; m -= 1; outside = true; if (m < 0) { m = 11; y -= 1; } }
                else if (offset > days) { day = offset - days; m += 1; outside = true; if (m > 11) { m = 0; y += 1; } }
                const iso = this.toIso(y, m, day);
                const evs = this.events.filter(ev => this.occursOn(ev, iso));
                out.push({ iso, day, outside, today: iso === today, holiday: !!this.holidays[iso], colors: evs.slice(0, 3).map(e => e.color || '#0d2a7a') });
            }
            return out;
        },
        get dayEvents() { return this.events.filter(ev => this.occursOn(ev, this.activeIso)); },
        get upcoming() {
            const t = this.todayIso();
            const n = new Date(); n.setDate(n.getDate() + 1);
            const tom = this.toIso(n.getFullYear(), n.getMonth(), n.getDate());
            return this.events
                .filter(ev => ev.date === t || ev.date === tom)
                .sort((a, b) => (a.startTime || '').localeCompare(b.startTime || ''))
                .map(ev => ({ ...ev, when: ev.date === t ? 'today' : 'tomorrow' }));
        },
        openDay(iso) { this.activeIso = iso; this.modal = 'day'; },
        openAdd(iso) {
            this.editingId = null;
            this.form = { title: '', date: iso || this.todayIso(), startTime: '09:00', endTime: '10:00', description: '' };
            this.modal = 'form';
        },
        openEdit(id) {
            const ev = this.events.find(e => e.id === id);
            if (!ev) return;
            this.editingId = id;
            this.form = { title: ev.title, date: ev.date, startTime: ev.startTime, endTime: ev.endTime, description: ev.description || '' };
            this.modal = 'form';
        },
        saveForm() {
            if (this.form.endTime < this.form.startTime) { alert('End time cannot be earlier than start time.'); return; }
            if (this.editingId) {
                const idx = this.events.findIndex(e => e.id === this.editingId);
                if (idx !== -1) this.events[idx] = { ...this.events[idx], ...this.form };
            } else {
                const id = this.events.reduce((m, e) => Math.max(m, e.id || 0), 0) + 1;
                this.events.push({ id, ...this.form, color: '#0d2a7a' });
            }
            this.persist();
            this.openDay(this.form.date);
        },
        removeEvent(id) {
            if (!confirm('Delete this event?')) return;
            this.events = this.events.filter(e => e.id !== id);
            this.persist();
        },
    }));
});
</script>
