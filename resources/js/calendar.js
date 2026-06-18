console.log("calendar.js loaded");

const MONTHS = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

const PH_HOLIDAYS = {
  "2026-01-01": "New Year's Day",
  "2026-01-16": "Lailatul Isra Wal Mi Raj",
  "2026-01-23": "First Philippine Republic Day",
  "2026-02-17": "Lunar New Year's Day",
  "2026-02-19": "Ramadan Start",
  "2026-02-25": "People Power Anniversary",
  "2026-03-20": "Eid al-Fitr Holiday",
  "2026-03-21": "Eid al-Fitr",
  "2026-04-02": "Maundy Thursday",
  "2026-04-03": "Good Friday",
  "2026-04-04": "Black Saturday",
  "2026-04-05": "Easter Sunday",
  "2026-04-09": "The Day of Valor",
  "2026-05-01": "Labor Day",
  "2026-05-27": "Eid al-Adha",
  "2026-05-28": "Eid al-Adha Day 2",
  "2026-06-12": "Independence Day",
  "2026-06-17": "Amun Jadid",
  "2026-07-27": "Founding Anniversary of Iglesia ni Cristo",
  "2026-08-21": "Ninoy Aquino Day",
  "2026-08-26": "Maulid un-Nabi",
  "2026-08-31": "National Heroes Day",
  "2026-09-03": "Yamashita Surrender Day",
  "2026-09-08": "Feast of the Nativity of Mary",
  "2026-11-01": "All Saints' Day",
  "2026-11-02": "All Souls' Day",
  "2026-11-07": "Sheikh Karim’ul Makhdum Day",
  "2026-11-30": "Bonifacio Day",
  "2026-12-08": "Feast of the Immaculate Conception",
  "2026-12-24": "Christmas Eve",
  "2026-12-25": "Christmas Day",
  "2026-12-30": "Rizal Day",
  "2026-12-31": "New Year's Eve"
};

let calYear;
let calMonth;
const DEFAULT_EVENT_COLOR = "#0d2a7a";

let events = JSON.parse(localStorage.getItem("calendarEvents")) || [];

let nextId =
  events.length > 0
    ? Math.max(...events.map(ev => ev.id)) + 1
    : 1;

function saveEvents() {
  localStorage.setItem("calendarEvents", JSON.stringify(events));
}

function pad(n) {
  return String(n).padStart(2, "0");
}

function toISO(y, m, d) {
  return `${y}-${pad(m + 1)}-${pad(d)}`;
}

function todayISO() {
  const t = new Date();
  return toISO(t.getFullYear(), t.getMonth(), t.getDate());
}

function escapeHtml(value) {
  const str = String(value ?? "");
  return str
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function occursOn(ev, iso) {
  const ed = new Date(ev.date);
  const cd = new Date(iso);

  if (cd < ed) return false;

  if (ev.recurrence === "daily") return true;
  if (ev.recurrence === "weekly") return ed.getDay() === cd.getDay();
  if (ev.recurrence === "monthly") return ed.getDate() === cd.getDate();

  return ev.date === iso;
}

function getEventsByDay(iso) {
  return events.filter((e) => occursOn(e, iso));
}

function getEventById(id) {
  return events.find((ev) => ev.id === Number(id));
}

function changeMonth(dir) {
  calMonth += dir;

  if (calMonth > 11) {
    calMonth = 0;
    calYear += 1;
  } else if (calMonth < 0) {
    calMonth = 11;
    calYear -= 1;
  }

  renderCal();
}

function renderCal() {
  const title = document.getElementById("calTitle");
  const grid = document.getElementById("calGrid");
  if (!title || !grid) return;

  title.textContent = `${MONTHS[calMonth]} ${calYear}`;
  grid.innerHTML = "";

  const firstDay = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const prevMonthDays = new Date(calYear, calMonth, 0).getDate();
  const today = todayISO();

  const totalCells = 42;

  for (let i = 0; i < totalCells; i += 1) {
    const dayOffset = i - firstDay + 1;
    let dayNumber = dayOffset;
    let cellMonth = calMonth;
    let cellYear = calYear;
    let isOutside = false;

    if (dayOffset < 1) {
      dayNumber = prevMonthDays + dayOffset;
      cellMonth = calMonth - 1;
      isOutside = true;
      if (cellMonth < 0) {
        cellMonth = 11;
        cellYear -= 1;
      }
    } else if (dayOffset > daysInMonth) {
      dayNumber = dayOffset - daysInMonth;
      cellMonth = calMonth + 1;
      isOutside = true;
      if (cellMonth > 11) {
        cellMonth = 0;
        cellYear += 1;
      }
    }

    const iso = toISO(cellYear, cellMonth, dayNumber);
    const evList = getEventsByDay(iso);
    const holiday = PH_HOLIDAYS[iso];

    const cell = document.createElement("div");
    cell.className = `cal-cell${isOutside ? " out-month" : ""}`;
    cell.onclick = () => openDayModal(iso);

    const num = document.createElement("div");
    num.className = `day-num${iso === today ? " today" : ""}${holiday ? " holiday" : ""}`;
    num.textContent = dayNumber;

    const markerRow = document.createElement("div");
    markerRow.className = "day-markers";

    if (holiday) {
      const holDot = document.createElement("span");
      holDot.className = "day-marker holiday";
      markerRow.appendChild(holDot);
    }

    if (evList.length > 0) {
      const palette = evList.slice(0, 3).map((ev) => ev.color || "#0d2a7a");
      palette.forEach((color) => {
        const evDot = document.createElement("span");
        evDot.className = "day-marker event";
        evDot.style.backgroundColor = color;
        markerRow.appendChild(evDot);
      });
    }

    cell.appendChild(num);
    cell.appendChild(markerRow);
    grid.appendChild(cell);
  }
}

function openDayModal(iso) {
    const holiday = PH_HOLIDAYS[iso];
    const evList = getEventsByDay(iso);

    const rows = evList.length
        ? evList
              .map(
                  (ev) => `
            <div class="ev-item" style="border-left:4px solid ${escapeHtml(ev.color)};">
                <div class="ev-head">
                    <div style="flex:1;">
                        <div class="ev-item-title">${escapeHtml(ev.title)}</div>
                        <div class="ev-item-meta">
                            <i class="fa-regular fa-clock" style="margin-right:4px;"></i>
                            ${escapeHtml(ev.startTime)} — ${escapeHtml(ev.endTime)}
                        </div>
                    </div>
                    <div class="ev-actions">
                        <button class="btn-ghost btn-xs" onclick="openEditModal(${ev.id})">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn-danger btn-xs" onclick="deleteEvent(${ev.id}, '${iso}')">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
                ${ev.description ? `<div class="ev-item-desc">${escapeHtml(ev.description)}</div>` : ""}
            </div>
        `
              )
              .join("")
        : `<div class="empty-state">
                <i class="fa-regular fa-calendar" style="font-size:1.8rem; opacity:0.2; margin-bottom:8px;"></i>
                <p>No events scheduled for this date</p>
           </div>`;

    const holidayBanner = holiday
        ? `<div class="hol-banner"><i class="fa-solid fa-flag" style="margin-right:6px;"></i>${escapeHtml(holiday)}</div>`
        : "";

    openModal(`
        <div class="modal-hdr">
            <div class="modal-hdr-text">
                <div class="modal-icon-wrap add">
                    <i class="fa-regular fa-calendar"></i>
                </div>
                <div>
                    <h3>${escapeHtml(formatDisplayDate(iso))}</h3>
                    <p class="modal-sub">${evList.length} event${evList.length !== 1 ? "s" : ""} scheduled</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        ${holidayBanner}
        <div class="event-list">${rows}</div>
        <div class="modal-foot">
            <button onclick="openAddModal('${iso}')" class="btn-save">
                <i class="fa-solid fa-plus"></i> Add Event
            </button>
        </div>
    `);
}


function formatDisplayDate(iso) {
    const d = new Date(iso + "T00:00:00");
    const options = { weekday: "long", month: "long", day: "numeric", year: "numeric" };
    return d.toLocaleDateString("en-US", options);
}

// Add to window exports
window.formatDisplayDate = formatDisplayDate;


function openAddModal(iso) {
  openEventFormModal({
    mode: "add",
    iso,
    data: {
      title: "",
      date: iso || todayISO(),
      startTime: "09:00",
      endTime: "10:00",
      description: ""
    }
  });
}

function openEditModal(id) {
  const ev = getEventById(id);
  if (!ev) return;

  openEventFormModal({
    mode: "edit",
    eventId: ev.id,
    iso: ev.date,
    data: ev
  });
}

function openEventFormModal({ mode, iso, data, eventId }) {
    const isEdit = mode === "edit";
    const safeData = {
        title: data?.title || "",
        date: data?.date || iso || todayISO(),
        startTime: data?.startTime || "09:00",
        endTime: data?.endTime || "10:00",
        description: data?.description || ""
    };

    openModal(`
        <div class="modal-hdr">
            <div class="modal-hdr-text">
                <div class="modal-icon-wrap ${isEdit ? "edit" : "add"}">
                    <i class="fa-solid ${isEdit ? "fa-pen-to-square" : "fa-calendar-plus"}"></i>
                </div>
                <div>
                    <h3>${isEdit ? "Edit Event" : "New Event"}</h3>
                    <p class="modal-sub">${isEdit ? "Update the details below" : "Schedule a new event on your calendar"}</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="eventForm" onsubmit="event.preventDefault(); ${isEdit ? `updateEvent(${eventId})` : "saveCalendarEvent()"}">
            <div class="form-section">
                <label class="field">
                    <span>Event Title</span>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-heading"></i>
                        <input id="evTitle" placeholder="e.g. Document Review Meeting" value="${escapeHtml(safeData.title)}" required>
                    </div>
                </label>
            </div>

            <div class="form-section">
                <span class="form-section-label">Date & Time</span>
                <div class="form-row">
                    <label class="field">
                        <span>Date</span>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-calendar"></i>
                            <input id="evDate" type="date" value="${escapeHtml(safeData.date)}" required>
                        </div>
                    </label>
                </div>
                <div class="form-row time-row">
                    <label class="field">
                        <span>Start</span>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-clock"></i>
                            <input id="evStart" type="time" value="${escapeHtml(safeData.startTime)}" required>
                        </div>
                    </label>
                    <div class="time-separator">
                        <span>to</span>
                    </div>
                    <label class="field">
                        <span>End</span>
                        <div class="input-with-icon">
                            <i class="fa-regular fa-clock"></i>
                            <input id="evEnd" type="time" value="${escapeHtml(safeData.endTime)}" required>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-section">
                <label class="field">
                    <span>Notes <span class="optional-tag">Optional</span></span>
                    <div class="textarea-wrap">
                        <textarea id="evDesc" rows="2" placeholder="Any additional details...">${escapeHtml(safeData.description)}</textarea>
                    </div>
                </label>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="openDayModal('${escapeHtml(safeData.date)}')">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn-save">
                    <i class="fa-solid ${isEdit ? "fa-check" : "fa-plus"}"></i>
                    ${isEdit ? "Save Changes" : "Create Event"}
                </button>
            </div>
        </form>
    `);
}


function collectFormValues() {
    const title = document.getElementById("evTitle")?.value.trim();
    const date = document.getElementById("evDate")?.value;
    const startTime = document.getElementById("evStart")?.value || "09:00";
    const endTime = document.getElementById("evEnd")?.value || "10:00";
    const description = document.getElementById("evDesc")?.value.trim() || "";

    if (!title) {
        alert("Event title is required.");
        return null;
    }

    if (!date) {
        alert("Event date is required.");
        return null;
    }

    if (endTime < startTime) {
        alert("End time cannot be earlier than start time.");
        return null;
    }

    return { title, date, startTime, endTime, description };
}


function saveCalendarEvent() {
  const data = collectFormValues();
  if (!data) return;

  events.push({
    id: nextId,
    ...data,
    color: DEFAULT_EVENT_COLOR
  });
  nextId += 1;

  saveEvents();
  renderCal();
  openDayModal(data.date);
}

function updateEvent(id) {
  const data = collectFormValues();
  if (!data) return;

  const idx = events.findIndex((ev) => ev.id === Number(id));
  if (idx === -1) return;

  events[idx] = {
    ...events[idx],
    ...data
  };

  saveEvents();
  renderCal();
  openDayModal(data.date);
}

function deleteEvent(id, iso) {
  const confirmed = window.confirm("Delete this event?");
  if (!confirmed) return;

  events = events.filter((ev) => ev.id !== Number(id));
  saveEvents();
  renderCal();
  openDayModal(iso);
}

function openModal(html) {
  const modalBox = document.getElementById("modalBox");
  const overlay = document.getElementById("overlay");
  if (!modalBox || !overlay) return;

  modalBox.innerHTML = html;
  overlay.style.display = "flex";
}

function closeModal() {
  const overlay = document.getElementById("overlay");
  const modalBox = document.getElementById("modalBox");

  if (!overlay || !modalBox) return;

  overlay.style.display = "none";
  modalBox.innerHTML = "";
}

window.onclick = function onWindowClick(e) {
  if (e.target && e.target.id === "overlay") {
    window.closeModal();
  }
};

document.addEventListener("DOMContentLoaded", () => {
  const now = new Date();
  calYear = now.getFullYear();
  calMonth = now.getMonth();
  renderCal();
});

window.saveCalendarEvent = saveCalendarEvent;
window.updateEvent = updateEvent;
window.deleteEvent = deleteEvent;
window.openEditModal = openEditModal;
window.openDayModal = openDayModal;
window.openAddModal = openAddModal;
window.closeModal = closeModal;
window.changeMonth = changeMonth;
window.todayISO = todayISO;