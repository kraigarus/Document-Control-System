
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
    const formattedDate = formatDisplayDate(iso);

    const rows = evList.length
        ? evList.map((ev) => `
            <div class="ev-card">
                <div class="ev-card-color" style="background: ${escapeHtml(ev.color)};"></div>
                <div class="ev-card-body">
                    <div class="ev-card-top">
                        <div class="ev-card-title">${escapeHtml(ev.title)}</div>
                        <div class="ev-card-btns">
                            <button class="ev-card-btn" onclick="openEditModal(${ev.id})" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="ev-card-btn ev-card-btn-danger" onclick="deleteEvent(${ev.id}, '${iso}')" title="Delete">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                    <div class="ev-card-time">
                        <i class="fa-regular fa-clock"></i>
                        ${formatTime12(ev.startTime)} — ${formatTime12(ev.endTime)}
                    </div>
                    ${ev.description ? `<div class="ev-card-notes">${escapeHtml(ev.description)}</div>` : ""}
                </div>
            </div>
        `).join("")
        : `<div class="ev-empty">
                <i class="fa-regular fa-calendar"></i>
                <span>No events scheduled</span>
           </div>`;

    const holidayBanner = holiday
        ? `<div class="ev-holiday"><i class="fa-solid fa-umbrella-beach"></i>${escapeHtml(holiday)}</div>`
        : "";

    openModal(`
        <div class="ev-modal">
            <div class="ev-modal-top">
                <div class="ev-modal-icon is-view">
                    <i class="fa-regular fa-calendar"></i>
                </div>
                <button class="ev-modal-close" onclick="closeModal()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <h3 class="ev-modal-title">${formattedDate}</h3>
            <p class="ev-modal-desc">${evList.length} event${evList.length !== 1 ? "s" : ""} scheduled</p>

            ${holidayBanner}

            <div class="ev-card-list">${rows}</div>

            <div class="ev-actions-row">
                <button onclick="openAddModal('${iso}')" class="ev-btn ev-btn-primary" style="width:100%;">
                    <i class="fa-solid fa-plus"></i> Add Event
                </button>
            </div>
        </div>
    `);
}

function formatTime12(time24) {
    if (!time24) return "";
    const [h, m] = time24.split(":").map(Number);
    const period = h >= 12 ? "PM" : "AM";
    const hour = h % 12 || 12;
    return `${hour}:${String(m).padStart(2, "0")} ${period}`;
}

// Add to exports
window.formatTime12 = formatTime12;

function formatDisplayDate(iso) {
    const d = new Date(iso + "T00:00:00");
    const options = { weekday: "long", month: "long", day: "numeric", year: "numeric" };
    return d.toLocaleDateString("en-US", options);
}

// Add to window exports
window.formatDisplayDate = formatDisplayDate;


function openAddModal(iso) {
    const targetDate = iso || todayISO();
    const isToday = targetDate === todayISO();

    let suggestedStart = "09:00";
    let suggestedEnd = "10:00";

    if (isToday) {
        const now = new Date();
        let h = now.getHours();
        let m = Math.ceil(now.getMinutes() / 15) * 15;

        if (m >= 60) {
            h += 1;
            m = 0;
        }
        if (h >= 24) {
            h = 23;
            m = 45;
        }

        suggestedStart = pad(h) + ":" + pad(m);

        let endH = h;
        let endM = m + 30;
        if (endM >= 60) {
            endH += 1;
            endM -= 60;
        }
        if (endH >= 24) {
            endH = 23;
            endM = 59;
        }

        suggestedEnd = pad(endH) + ":" + pad(endM);
    }

    openEventFormModal({
        mode: "add",
        iso: targetDate,
        data: {
            title: "",
            date: targetDate,
            startTime: suggestedStart,
            endTime: suggestedEnd,
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

    const formattedDate = formatDisplayDate(safeData.date);

    openModal(`
        <div class="ev-modal">
            <div class="ev-modal-top">
                <div class="ev-modal-icon ${isEdit ? "is-edit" : "is-add"}">
                    <i class="fa-solid ${isEdit ? "fa-pen" : "fa-plus"}"></i>
                </div>
                <button class="ev-modal-close" onclick="closeModal()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <h3 class="ev-modal-title">${isEdit ? "Edit Event" : "New Event"}</h3>
            <p class="ev-modal-desc">${formattedDate}</p>

            <form id="eventForm" onsubmit="event.preventDefault(); ${isEdit ? `updateEvent(${eventId})` : "saveCalendarEvent()"}">

                <div class="ev-field">
                    <label class="ev-label">Title</label>
                    <input id="evTitle" class="ev-input" placeholder="What's the event?" value="${escapeHtml(safeData.title)}" required autocomplete="off">
                </div>

                <div class="ev-field">
                    <label class="ev-label">Date</label>
                    <input id="evDate" type="date" class="ev-input" value="${escapeHtml(safeData.date)}" required>
                </div>

                <div class="ev-time-row">
                    <div class="ev-field ev-field-half">
                        <label class="ev-label">Start</label>
                        <input id="evStart" type="time" class="ev-input" value="${escapeHtml(safeData.startTime)}" required>
                    </div>
                    <div class="ev-field ev-field-half">
                        <label class="ev-label">End</label>
                        <input id="evEnd" type="time" class="ev-input" value="${escapeHtml(safeData.endTime)}" required>
                    </div>
                </div>

                <div class="ev-field">
                    <label class="ev-label">
                        Notes
                        <span class="ev-optional">Optional</span>
                    </label>
                    <textarea id="evDesc" class="ev-textarea" rows="2" placeholder="Add details...">${escapeHtml(safeData.description)}</textarea>
                </div>

                <div class="ev-actions-row">
                    <button type="button" class="ev-btn ev-btn-ghost" onclick="openDayModal('${escapeHtml(safeData.date)}')">
                        Cancel
                    </button>
                    <button type="submit" class="ev-btn ev-btn-primary">
                        <i class="fa-solid ${isEdit ? "fa-check" : "fa-plus"}"></i>
                        ${isEdit ? "Save" : "Create"}
                    </button>
                </div>
            </form>
        </div>
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