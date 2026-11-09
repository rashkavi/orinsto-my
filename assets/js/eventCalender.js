var currentSelectedMonth;
var currentSelectedYear;
const now = new Date();
let isSubjectLectureSchedule = false;
let subject = null;

// ===== Mobile calendar state =====
let mobileSelectedDate = null;
let mobileNavBound = false;

const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

// Sample data structure for lectures
// Updated data structure with more details
let lectures = {};
renderCalendarViews(now.getFullYear(), now.getMonth());

const getLectureScheduleData = () => {
    fetch('api/getLectureScheduleData')
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {

                lectures = response.lectures;
                renderCalendarViews(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const getSubjectLectureScheduleData = (id) => {
    isSubjectLectureSchedule = true;
    subject = id;
    fetch(`api/getLectureScheduleData?subject=${id}`)
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                renderCalendarViews(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const getSelectedLecturerScheduleData = (lecturerd) => {
    fetch('api/getSelectedLecturerScheduleData?lecturer=' + lecturerd)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                renderCalendarViews(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const getBatchStudentAttendanceData = (id) => {
    fetch('api/getBatchStudentAttendanceData?student=' + id)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {

                lectures = response.lectures;
                generateAttendnaceCalendar(now.getFullYear(), now.getMonth());
                initAttendancePopovers();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterLectureSchedule = () => {
    const search_text = $("#search-input").val();
    fetch('api/getLectureScheduleData?text=' + search_text)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                renderCalendarViews(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

function generateCalendar(year, month) {
    $("#month").attr("data-i18n", `${monthNames[month].toLowerCase()}`);
    $("#year").text(`${year}`);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    currentSelectedMonth = month;
    currentSelectedYear = year;

    let table = `<table class='calendar-grid'><thead><tr>`;
    const daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    daysOfWeek.forEach(day => table += `<th>${day}</th>`);
    table += "</tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay.getDay(); i++) {
        table += "<td class='empty-day'></td>";
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dayLectures = lectures[dateStr] || [];

        const cellHeight = dayLectures.length > 0 ? `${100 + (dayLectures.length * 70)}px` : "100px";

        table += `<td class='calendar-cell ${dayLectures.length > 0 ? 'has-lectures' : ''}' 
                  style='height:${cellHeight}' data-date='${dateStr}'>`;

        table += `<div class='day-header'>${day}</div>`;

        if (dayLectures.length > 0) {
            table += `<div class="lectures-container">`;

            dayLectures.forEach(lecture => {
                const isRescheduleRelated = !!(lecture.isRescheduled || lecture.hasRescheduleTarget);
                table += `
                <div class="lecture-item ${lecture.status.toLowerCase()} ${isRescheduleRelated ? 'has-reschedule' : ''}"
                     data-lecture-id="${lecture.lectureId}"
                     data-bs-toggle="tooltip"
                     data-id="${lecture.lectureId}"
                     
                     data-lecturer="${lecture.lecturerName}"
                     >
        
                    <div class="subject-cover">
                        <img src="${lecture.programmeImage}" alt="${lecture.programme}"
                         onerror="this.src='https://dashboard.summersetlms.com/assets/imgs/common/empty-thumbnail.webp'">
                    </div>
        
                    <div class="lecture-details">
                        <div class="lecture-subject">
                            ${isRescheduleRelated ? '<iconify-icon icon="solar:refresh-circle-bold" class="reschedule-icon" title="Rescheduled lecture"></iconify-icon>' : ''}${lecture.title}
                        </div>
                        <div class="lecture-meta">
                            <span class="lecturer-name">${lecture.lectureType}</span>
                            <span class="attendance-status">${lecture.status}</span>
                        </div>
                    </div>
                </div>`;
            });

            table += `</div>`;

        }

        table += `</td>`;

        if ((firstDay.getDay() + day) % 7 === 0) {
            table += "</tr><tr>";
        }


    }

    table += "</tr></tbody></table>";
    document.getElementById("calendar").innerHTML = table;
    initTooltips2();
    document.querySelectorAll("td[data-date]").forEach(cell => {
        cell.addEventListener("click", function () {
            const date = this.getAttribute("data-date");
            const dayLectures = lectures[date] || [];
            if (dayLectures.length === 0) {
                showLectureModal(date, dayLectures);
            }

        });
    });

    document.querySelectorAll(".lecture-item").forEach(item => {
        item.addEventListener("click", function () {
            const lectureId = this.getAttribute("data-lecture-id");
            showLectureDetails(lectureId);
        });
    });

}

// =====================================================================
// MOBILE CALENDAR (small devices) — mirrors the student portal's
// dateContainer/mobileCalGrid pattern, driven by the same `lectures`
// object used by generateCalendar() above.
// =====================================================================

function formatDateStr(year, month, day) {
    return `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
}

// Single entry point: keeps the desktop table and the mobile grid in sync
// whenever data loads or the user navigates months.
function renderCalendarViews(year, month) {
    if (document.getElementById("calendar")) {
        generateCalendar(year, month);
    }
    generateMobileCalendarGrid(year, month);
    bindMobileCalendarNav();
}

function generateMobileCalendarGrid(year, month) {
    const grid = document.getElementById("mobileCalGrid");
    const titleEl = document.getElementById("mobileCalTitle");
    if (!grid || !titleEl) return; // mobile markup not present on this page

    titleEl.textContent = `${monthNames[month]} ${year}`;

    currentSelectedMonth = month;
    currentSelectedYear = year;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    const todayStr = formatDateStr(now.getFullYear(), now.getMonth(), now.getDate());
    const monthPrefix = `${year}-${(month + 1).toString().padStart(2, '0')}`;

    // Keep the selected date pinned to today when browsing the current
    // month; otherwise default to the 1st of whatever month is shown.
    if (!mobileSelectedDate || !mobileSelectedDate.startsWith(monthPrefix)) {
        mobileSelectedDate = (year === now.getFullYear() && month === now.getMonth())
            ? todayStr
            : formatDateStr(year, month, 1);
    }

    let html = "";
    for (let i = 0; i < firstDay.getDay(); i++) {
        html += `<div class="mobile-cal-day empty"></div>`;
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = formatDateStr(year, month, day);
        const dayLectures = lectures[dateStr] || [];
        const isToday = dateStr === todayStr;
        const isSelected = dateStr === mobileSelectedDate;

        html += `
        <div class="mobile-cal-day ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''} ${dayLectures.length > 0 ? 'has-lecture' : ''}" data-date="${dateStr}">
            <span class="mobile-cal-day-num">${day}</span>
        </div>`;
    }

    grid.innerHTML = html;

    grid.querySelectorAll(".mobile-cal-day[data-date]").forEach(cell => {
        cell.addEventListener("click", function () {
            mobileSelectedDate = this.getAttribute("data-date");
            grid.querySelectorAll(".mobile-cal-day").forEach(d => d.classList.remove("selected"));
            this.classList.add("selected");
            renderMobileLectureList(mobileSelectedDate);
        });
    });

    renderMobileLectureList(mobileSelectedDate);
}

function renderMobileLectureList(dateStr) {
    const listEl = document.getElementById("mobileCalLectureList");
    const selectedDateEl = document.getElementById("mobileCalSelectedDate");
    const selectedCountEl = document.getElementById("mobileCalSelectedCount");
    if (!listEl) return;

    const dayLectures = lectures[dateStr] || [];

    if (selectedDateEl) {
        const dObj = new Date(dateStr);
        selectedDateEl.textContent = dObj.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric'
        });
    }

    if (selectedCountEl) {
        selectedCountEl.textContent = dayLectures.length > 0
            ? `${dayLectures.length} Lecture${dayLectures.length > 1 ? 's' : ''}`
            : 'No Lectures';
    }

    if (dayLectures.length === 0) {
        const todayObj = new Date();
        todayObj.setHours(0, 0, 0, 0);
        const selObj = new Date(dateStr);
        selObj.setHours(0, 0, 0, 0);
        const isFutureOrToday = selObj.getTime() >= todayObj.getTime();
        const canSchedule = typeof canScheduleLecture !== 'undefined' && canScheduleLecture;

        const scheduleUrl = isSubjectLectureSchedule
            ? `subject-schedule-lecture?subject-id=${subject}&date=${dateStr}`
            : `schedule-lecture?date=${dateStr}`;

        listEl.innerHTML = `
        <div class="col-12">
            <div class="no-lectures">
                <div class="no-lectures-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#6c757d" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </div>
                <h5>No Lectures Scheduled</h5>
                <p>There are no classes scheduled for this day</p>
                ${isFutureOrToday && canSchedule ? `
                <button class="custom-btn mt-2 px-4" onclick="window.location.href = '${scheduleUrl}'">
                    Schedule a Lecture
                </button>` : ''}
            </div>
        </div>`;
        return;
    }

    listEl.innerHTML = dayLectures.map(lecture => {
        const isRescheduleRelated = !!(lecture.isRescheduled || lecture.hasRescheduleTarget);
        return `
        <div class="col-12">
            <div class="mobile-lecture-card ${lecture.status.toLowerCase()} ${isRescheduleRelated ? 'has-reschedule' : ''}" data-lecture-id="${lecture.lectureId}" role="button">
                <div class="mobile-lecture-thumb">
                    <img src="${lecture.programmeImage}" alt="${lecture.title}"
                         onerror="this.src='https://dashboard.summersetlms.com/assets/imgs/common/empty-thumbnail.webp'">
                </div>
                <div class="mobile-lecture-info">
                    <div class="mobile-lecture-title">
                        ${isRescheduleRelated ? '<span class="reschedule-badge"><iconify-icon icon="solar:refresh-circle-bold"></iconify-icon> Rescheduled</span>' : ''}
                        ${lecture.title}
                    </div>
                    <div class="mobile-lecture-meta">Lecture Type : ${lecture.lectureType}</div>
                    <div class="mobile-lecture-time">${lecture.startTime} - ${lecture.endTime}</div>
                    <div class="mobile-lecture-row">
                        <span class="mobile-lecture-label">Lecturer</span>
                        <span class="mobile-lecture-value">${lecture.lecturerName}</span>
                    </div>
                    <div class="mobile-lecture-row">
                        <span class="mobile-lecture-label">Audience</span>
                        <span class="mobile-lecture-value">${escAudience(lectureAudienceText(lecture))}</span>
                    </div>
                    <div class="mobile-lecture-status ${lecture.status.toLowerCase()}">
                        <span class="status-dot ${lecture.status.toLowerCase()}"></span>
                        <span>${lecture.status}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    }).join('');

    listEl.querySelectorAll("[data-lecture-id]").forEach(card => {
        card.addEventListener("click", function () {
            showLectureDetails(this.getAttribute("data-lecture-id"));
        });
    });
}

function bindMobileCalendarNav() {
    if (mobileNavBound) return;

    const prevBtn = document.getElementById("mobileCalPrev");
    const nextBtn = document.getElementById("mobileCalNext");
    if (!prevBtn || !nextBtn) return;

    prevBtn.addEventListener("click", () => {
        const year = currentSelectedMonth === 0 ? currentSelectedYear - 1 : currentSelectedYear;
        const month = currentSelectedMonth === 0 ? 11 : currentSelectedMonth - 1;
        renderCalendarViews(year, month);
    });

    nextBtn.addEventListener("click", () => {
        const year = currentSelectedMonth === 11 ? currentSelectedYear + 1 : currentSelectedYear;
        const month = currentSelectedMonth === 11 ? 0 : currentSelectedMonth + 1;
        renderCalendarViews(year, month);
    });

    mobileNavBound = true;
}

// =====================================================================
// End mobile calendar
// =====================================================================

function generateAttendnaceCalendar(year, month) {
    $("#monthYear").text('Attendance - ' + `${monthNames[month]} ${year}`);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    currentSelectedMonth = month;
    currentSelectedYear = year;

    let table = `<table class='calendar-grid'><thead><tr>`;
    const daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    daysOfWeek.forEach(day => table += `<th>${day}</th>`);
    table += "</tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay.getDay(); i++) {
        table += "<td class='empty-day'></td>";
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dayLectures = lectures[dateStr] || [];

        const cellHeight = dayLectures.length > 0 ? `${100 + (dayLectures.length * 70)}px` : "100px";

        table += `<td class='calendar-cell ${dayLectures.length > 0 ? 'has-lectures' : ''}' 
                  style='height:${cellHeight}' data-date='${dateStr}'>`;

        table += `<div class='day-header'>${day}</div>`;

        if (dayLectures.length > 0) {
            table += `<div class='events-stack'>`;
            dayLectures.forEach(lecture => {

                const statusKey = (lecture.status || '').toLowerCase(); // completed/upcoming/cancelled/ongoing
                const isRescheduleRelated = !!(lecture.isRescheduled || lecture.hasRescheduleTarget);

                table += `
                  <div class="tt-mini ${statusKey} ${isRescheduleRelated ? 'has-reschedule' : ''}" data-lecture-id="${lecture.lectureId}">
                    <div class="tt-mini-top">
                      <span class="tt-mini-icon">
                        ${ttStatusIcon(statusKey)}
                      </span>
                      ${isRescheduleRelated ? '<iconify-icon icon="solar:refresh-circle-bold" class="tt-mini-reschedule-icon" title="Rescheduled lecture"></iconify-icon>' : ''}
                    </div>
                 
                    <div class="tt-mini-title">${lecture.subject}</div>
                    <div class="tt-mini-time">${lecture.startTime} - ${lecture.endTime}</div>
                  </div>
                `;
            });

            table += `</div>`;
        }
        table += `</td>`;

        if ((firstDay.getDay() + day) % 7 === 0) {
            table += "</tr><tr>";
        }
    }

    table += "</tr></tbody></table>";
    document.getElementById("calendar").innerHTML = table;

    document.querySelectorAll("td[data-date]").forEach(cell => {
        cell.addEventListener("click", function () {
            const date = this.getAttribute("data-date");
            const dayLectures = lectures[date] || [];
            if (dayLectures.length === 0) {
                showLectureModal(date, dayLectures);
            }

        });
    });

    document.querySelectorAll(".tt-mini").forEach(item => {
        item.addEventListener("click", function (e) {
            e.stopPropagation();
            const lectureId = this.getAttribute("data-lecture-id");
            showLectureDetails(lectureId);
        });
    });


    document.querySelectorAll(".lecture-item").forEach(item => {
        item.addEventListener("click", function () {
            const lectureId = this.getAttribute("data-lecture-id");
            showLectureDetails(lectureId);
        });
    });

}


const prevMonthSchedule = () => {
    const year = currentSelectedMonth === 0 ? currentSelectedYear - 1 : currentSelectedYear;
    const month = currentSelectedMonth === 0 ? 11 : currentSelectedMonth - 1;
    renderCalendarViews(year, month);
    initializeLanguage();
};

const nextMonthSchedule = () => {
    const year = currentSelectedMonth === 11 ? currentSelectedYear + 1 : currentSelectedYear;
    const month = currentSelectedMonth === 11 ? 0 : currentSelectedMonth + 1;
    renderCalendarViews(year, month);
    initializeLanguage();
};
const prevMonth = (status = 1) => {
    const year = currentSelectedMonth === 0 ? currentSelectedYear - 1 : currentSelectedYear;
    const month = currentSelectedMonth === 0 ? 11 : currentSelectedMonth - 1;
    generateAttendnaceCalendar(year, month);
    initAttendancePopovers();

};

const nextMonth = (status = 1) => {
    const year = currentSelectedMonth === 11 ? currentSelectedYear + 1 : currentSelectedYear;
    const month = currentSelectedMonth === 11 ? 0 : currentSelectedMonth + 1;
    generateAttendnaceCalendar(year, month);
    initAttendancePopovers();
};

// =====================================================================
// Lecture audience (intake groups)
// Data comes from api/getLectureScheduleData (saved DB relationships):
//   lecture.audienceLabel / lecture.audienceAll       — whole calendar item
//   batch.audience_groups / batch.audience_all        — per intake (time_table row)
// No saved groups = all students in the intake.
// =====================================================================
const AUDIENCE_ALL_TEXT = 'All students in this intake';

function escAudience(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function lectureAudienceText(lecture) {
    return lecture.audienceLabel || AUDIENCE_ALL_TEXT;
}

function batchAudienceText(batch) {
    const groups = batch.audience_groups || [];
    return groups.length ? groups.map(g => g.name).join(', ') : AUDIENCE_ALL_TEXT;
}

// Used after the audience modal saves, so the calendar shows saved data.
function refreshLectureCalendar() {
    if (isSubjectLectureSchedule && subject) {
        getSubjectLectureScheduleData(subject);
    } else {
        getLectureScheduleData();
    }
}

// Compact version of the reschedule info used inside hover tooltips/popovers
// (initTooltips2 / initAttendancePopovers) — same data, no action button,
// since bootstrap tooltips/popovers close on mouseleave before a click lands.
// Shown purely when hasRescheduleTarget / isRescheduled is true — i.e. purely
// based on a real linked_time_table_id, never inferred from the status text.
function reschedulePopRowsHtml(lecture) {
    if (!lecture.hasRescheduleTarget && !lecture.isRescheduled) return '';

    let html = `<div class="pro-pop-reschedule">`;

    if (lecture.hasRescheduleTarget) {
        html += `
        <div class="pro-pop-reschedule-title">
            <iconify-icon icon="solar:refresh-circle-bold"></iconify-icon>
            View Rescheduled Lecture
        </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Date</span>
            <span class="pro-pop-value">${lecture.linkedDateDisplay || 'N/A'}</span>
        </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Time</span>
            <span class="pro-pop-value">${lecture.linkedStartTimeDisplay || 'N/A'} - ${lecture.linkedEndTimeDisplay || 'N/A'}</span>
        </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Lecturer</span>
            <span class="pro-pop-value">${lecture.linkedLecturer || 'N/A'}</span>
        </div>`;
    }

    if (lecture.isRescheduled) {
        html += `
        <div class="pro-pop-reschedule-title">
            <iconify-icon icon="solar:refresh-circle-bold"></iconify-icon>
            View Postponed Lecture
        </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Date</span>
            <span class="pro-pop-value">${lecture.originDate || 'N/A'}</span>
        </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Time</span>
            <span class="pro-pop-value">${lecture.originStartTime || 'N/A'} - ${lecture.originEndTime || 'N/A'}</span>
        </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Lecturer</span>
            <span class="pro-pop-value">${lecture.originLecturer || 'N/A'}</span>
        </div>`;
    }

    html += `</div>`;
    return html;
}

function rescheduleInfoHtml(lecture) {
    let html = '';

    if (lecture.hasRescheduleTarget) {
        html += `
        <div class="reschedule-info-box to">
            <div class="reschedule-info-head">
                <iconify-icon icon="solar:refresh-circle-bold"></iconify-icon>
                <span>Rescheduled To</span>
            </div>
            <div class="reschedule-info-body">
                <div class="reschedule-info-row">
                    <span class="reschedule-info-label">Date</span>
                    <span class="reschedule-info-value">${lecture.linkedDateDisplay || 'N/A'}</span>
                </div>
                <div class="reschedule-info-row">
                    <span class="reschedule-info-label">Time</span>
                    <span class="reschedule-info-value">${lecture.linkedStartTimeDisplay || 'N/A'} - ${lecture.linkedEndTimeDisplay || 'N/A'}</span>
                </div>
                <div class="reschedule-info-row">
                    <span class="reschedule-info-label">Lecturer</span>
                    <span class="reschedule-info-value">${lecture.linkedLecturer || 'N/A'}</span>
                </div>
            </div>
            <button type="button" class="table-btn table-btn-dark-theme mt-2" onclick="showLectureDetails(${lecture.linkedTimeTableId})">
                View Rescheduled Lecture
            </button>
        </div>`;
    }

    if (lecture.isRescheduled) {
        html += `
        <div class="reschedule-info-box from">
            <div class="reschedule-info-head">
                <iconify-icon icon="solar:refresh-circle-bold"></iconify-icon>
                <span>Rescheduled From</span>
            </div>
            <div class="reschedule-info-body">
                <div class="reschedule-info-row">
                    <span class="reschedule-info-label">Date</span>
                    <span class="reschedule-info-value">${lecture.originDate || 'N/A'}</span>
                </div>
                <div class="reschedule-info-row">
                    <span class="reschedule-info-label">Time</span>
                    <span class="reschedule-info-value">${lecture.originStartTime || 'N/A'} - ${lecture.originEndTime || 'N/A'}</span>
                </div>
                <div class="reschedule-info-row">
                    <span class="reschedule-info-label">Lecturer</span>
                    <span class="reschedule-info-value">${lecture.originLecturer || 'N/A'}</span>
                </div>
            </div>
            <button type="button" class="table-btn table-btn-dark-theme mt-2" onclick="showLectureDetails(${lecture.originTimeTableId})">
                View Postponed Lecture
            </button>
        </div>`;
    }

    return html;
}

function showLectureModal(date, dayLectures) {
    const modalDateEl = document.getElementById("eventModalLabel");
    const modalContentEl = document.getElementById("eventDetails");

    // Format the date nicely
    const dateObj = new Date(date);
    const formattedDate = dateObj.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    modalDateEl.textContent = formattedDate;

    const inputDate = new Date(date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    inputDate.setHours(0, 0, 0, 0);

    const isFutureOrToday = inputDate.getTime() >= today.getTime();

    if (dayLectures.length > 0) {
        modalContentEl.innerHTML = `
        <div class="lecture-cards-container">
            ${dayLectures.map(lecture => {
            const isRescheduleRelated = !!(lecture.isRescheduled || lecture.hasRescheduleTarget);
            return `
            <div class="lecture-card ${lecture.status.toLowerCase()} ${isRescheduleRelated ? 'has-reschedule' : ''} d-flex flex-column">
                <div class="lecture-card-header ${lecture.status.toLowerCase()}">
                    <div class="subject-cover">
                        <img src="${lecture.programmeImage}" 
                             alt="${lecture.subject}"
                             onerror="this.src='https://dashboard.summersetlms.com/assets/imgs/common/empty-thumbnail.webp'">
                    </div>
                    <div class="lecturer-meta">
                        <h4>
                            ${isRescheduleRelated ? '<span class="reschedule-badge"><iconify-icon icon="solar:refresh-circle-bold"></iconify-icon> Rescheduled</span>' : ''}
                            ${lecture.subject}
                        </h4>
                        <div class="subject-badge">Lecture Type : ${lecture.lectureType}</div>
                    </div>
                    <div class="lecture-time">${lecture.startTime} - ${lecture.endTime}</div>
                </div>
                <div class="lecture-card-body">
                    <div class="d-flex flex-column">
                        <span class="lecture-time">Lecturer</span>
                        <span class="text-14 man-text">${lecture.lecturerName}</span>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="lecture-time">Audience</span>
                        <span class="text-14 man-text">${escAudience(lectureAudienceText(lecture))}</span>
                    </div>

                    <div class="status-indicator">
                        <span class="status-dot ${lecture.status.toLowerCase()}"></span>
                        <span>${lecture.status}</span>
                    </div>
                </div>
               <div class="d-flex justify-content-between align-items-center mb-3 px-3">
               ${lecture.status === 'Ongoing' && lecture.lectureType === 'Online'
                    ? `
                    <button class="table-btn table-btn-success active" onclick="window.open('${lecture.joinLink}', '_blank')">
                        Join Now
                    </button>
                `
                    : ''}

                <div class="flex-grow-1"></div>
                <div class="dropdown">
                    <button class="table-btn table-btn-dark-theme dropdown-toggle br-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Action
                    </button>
                   ${isSubjectLectureSchedule ? `
                    <ul class="dropdown-menu z-3">
                        <li>
                            <a class="dropdown-item" href="lecture-details?lecture-id=${lecture.lectureId}&subject-id=${subject}">
                                Lecture Details
                            </a>
                        </li>

                        ${lecture.status === 'Ongoing'
                        ? `<li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}&subject-id=${subject}">Attendance</a></li>`
                        : ''}

                        ${lecture.status === 'Pending Confirmation'
                        ? `
                                <li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}&subject-id=${subject}">Attendance</a></li>
                                <li><a class="dropdown-item" href="lecture-recording?lecture-id=${lecture.lectureId}&subject-id=${subject}">Lecture Recording</a></li>
                            `
                        : ''}
                    </ul>
                ` : `
                 <ul class="dropdown-menu z-3">
                        <li>
                            <a class="dropdown-item" href="lecture-details?lecture-id=${lecture.lectureId}">
                                Lecture Details
                            </a>
                        </li>

                        ${lecture.status === 'Ongoing'
                    ? `<li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}">Attendance</a></li>`
                    : ''}

                        ${lecture.status === 'Pending Confirmation'
                    ? `
                                <li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}">Attendance</a></li>
                                <li><a class="dropdown-item" href="lecture-recording?lecture-id=${lecture.lectureId}">Lecture Recording</a></li>
                            `
                    : ''}
                    </ul>
                `}
                    </ul>
                </div>
            </div>

            </div>
            `;
        }).join('')}
                ${isFutureOrToday ? `
            <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'subject-schedule-lecture?subject-id=${subject}&date=${date}'">
                        Schedule a Lecture
            </button>
            ` : ''}
        </div>`;
    } else {
        modalContentEl.innerHTML = `
        <div class="no-lectures">
            <div class="no-lectures-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </div>
            <h5>No Lectures Scheduled</h5>
            <p>There are no classes scheduled for this day</p>
            ${isFutureOrToday ?
                isSubjectLectureSchedule ? `
            <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'subject-schedule-lecture?subject-id=${subject}&date=${date}'">
                        Schedule a Lecture
            </button>
            `: `
             <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'schedule-lecture?date=${date}'">
                        Schedule a Lecture
            </button>
            ` : ''}
           
        </div>`;
    }

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
    modal.show();
}

function showLectureDetails(lectureId) {
    const allLecturesArray = Object.values(lectures).flat();
    const lecture = allLecturesArray.find(l => l.lectureId == lectureId);

    if (!lecture) {
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('lectureDetailsModal'));
    const modalTitle = document.getElementById('lectureDetailsTitle');
    const modalBody = document.getElementById('lectureDetailsBody');
    const modalFooter = document.getElementById('lectureDetailsFooter');

    modalTitle.textContent = `${lecture.title}`;

    modalBody.innerHTML = `
    <div class="col-lg-12">
    <div class="lecture-card border-0 shadow-none">
        <div class="lecture-body px-4 py-4">

            <!-- Main Lecture Image -->
            <div class="info-row border-0 justify-content-center mb-4">
                <img src="${lecture.programmeImage}" 
                     alt="${lecture.subject}" 
                     class="lecture-main-image"
                     onerror="this.src='./assets/imgs/common/empty-thumbnail.webp'">
            </div>

            <!-- Lecture Details -->
            <div class="info-row">
                <div class="info-label">Faculty:</div>
                <div class="info-value">${lecture.faculty || 'N/A'}</div>
            </div>

            <div class="info-row">
                <div class="info-label">Programme:</div>
                <div class="info-value">${lecture.programme || 'N/A'}</div>
            </div>

            <div class="info-row">
                <div class="info-label">Lecturer:</div>
                <div class="info-value">
                <img src="${lecture.lecturerProfile}" alt="${lecture.lecturerName}" class="lecturer-profile-img me-2 hwr-40" onerror="this.src='./assets/imgs/common/empty-img.png'">
                ${lecture.lecturerName || 'N/A'}
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Date & Time:</div>
                <div class="info-value">
                    ${lecture.date || 'N/A'}, ${lecture.startTime || 'N/A'} - ${lecture.endTime || 'N/A'}
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Audience:</div>
                <div class="info-value">${escAudience(lectureAudienceText(lecture))}</div>
            </div>

            <div class="info-row m-0">
                <div class="info-label">Conduct Status:</div>
                <div class="info-value">
                    <span class="status-badge status-${(lecture.status || '').toLowerCase().replace(/\s+/g, '-')}">
                        ${lecture.status || 'N/A'}
                    </span>
                </div>
            </div>

               ${(lecture.statusId === '2') ? `
           <div class="info-row">
                <div class="info-label">Cancelled Reason:</div>
                <div class="info-value">
                    ${lecture.reason || 'N/A'}
                </div>
            </div>
            ` : ''}


            ${(lecture.hasRescheduleTarget || lecture.isRescheduled) ? `
            <!-- Reschedule Info -->
            <div class="reschedule-section mt-4">
                ${rescheduleInfoHtml(lecture)}
            </div>
            ` : ''}

            <!-- Batch Section -->
            <div class="batch-section mt-4">
                <div class="batch-section-header">
                    <div class="batch-title-wrap">
                        <div class="batch-section-title">Assigned Intakes</div>
                        <div class="batch-section-subtitle">
                            ${lecture.batchList?.length || 0} intake(s) linked to this lecture
                        </div>
                    </div>
                </div>

                <div class="batch-list">
                    ${lecture.batchList && lecture.batchList.length
            ? lecture.batchList.map(batch => `
                                <div class="batch-item">
                                    <div class="batch-left">
                                        <div class="batch-cover-wrap">
                                            <img src="./assets/imgs/intakes-covers/${batch.batch_cover}"
                                                 alt="${batch.batch_name || 'Batch'}"
                                                 class="batch-cover"
                                                 onerror="this.src='./assets/imgs/common/empty-thumbnail.webp'">
                                        </div>

                                        <div class="batch-content">
                                            <div class="batch-top-line">
                                                <span class="batch-name">${batch.batch_name || 'N/A'}</span>
                                                <span class="batch-level">${batch.subject || 'N/A'}</span>
                                            </div>

                                            <div class="text-12 sub-text mt-1 d-flex align-items-center flex-wrap gap-2">
                                                <iconify-icon icon="mdi:account-group-outline" width="16"></iconify-icon>
                                                <span>Audience: <span class="main-text fw-medium">${escAudience(batchAudienceText(batch))}</span></span>
                                                ${lecture.hasPermissionEditAudience && typeof openLectureAudienceModal === 'function' ? `
                                                <button type="button" class="table-btn table-btn-dark-theme py-0 px-2 text-12"
                                                    onclick="openLectureAudienceModal(${parseInt(batch.lecture_id, 10)})">
                                                    Change audience
                                                </button>` : ''}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="batch-right">
                                        <div class="dropdown">
                                             ${lecture.hasPermissionEditPermission || lecture.hasPermissionConfirmLecturerPayment || lecture.hasPermissionUploadRecording || lecture.hasPermissionMarkAttendance
                    ? `
           
                            <div class="btn-group">
                            <button type="button" class="custom-btn btn-action px-5 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Actions
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                ${lecture.status === 'Ongoing' && (lecture.hasPermissionEditPermission || lecture.hasPermissionMarkAttendance) ? `
                                    ${lecture.hasPermissionEditPermission ? `
                                        <li>
                                            <a class="dropdown-item" 
                                                href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                                <i class="fas fa-edit me-2 text-warning"></i>Edit
                                            </a>
                                        </li>
                                    ` : ''}

                                    ${lecture.hasPermissionMarkAttendance ? `
                                        <li>
                                            <a class="dropdown-item" 
                                                href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                                <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                            </a>
                                        </li>
                                    ` : ''}
                                ` : ''}

                              ${lecture.status === 'Pending Confirmation' ? `

                                ${lecture.hasPermissionEditPermission ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                ` : ''}

                                ${lecture.hasPermissionMarkAttendance ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                        </a>
                                    </li>

                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-feedback-report?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-success"></i>Feedbacks
                                        </a>
                                    </li>
                                ` : ''}
                              
                            
                            ` : ''}

                             ${lecture.status === 'Completed' ? `
                               
                                ${lecture.hasPermissionMarkAttendance ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                        </a>
                                    </li>

                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-feedback-report?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-success"></i>Feedbacks
                                        </a>
                                    </li>
                                ` : ''}
                              
                            
                            ` : ''}

                                ${(lecture.status === 'Postponed' || lecture.status === 'Upcoming' || lecture.status === 'Cancelled' || lecture.status === 'Study Leave') ? `
                                    ${lecture.hasPermissionEditPermission ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                     ${lecture.attendanceCount > 0 ? `
                                    <li>
                                                                            <a class="dropdown-item"
                                            href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-warning"></i>Attendance
                                        </a>
                                    </li>
                                    ` : ''}
                                    <li>
                                        <a class="dropdown-item" onclick="removeLectureOpenModal(${batch.lecture_id})"
                                            href="#">
                                            <i class="fas fa-edit me-2 text-warning"></i>Remove
                                        </a>
                                    </li>
                                    ` : ''}

                                ` : ''}
                            </ul>
                        </div>
                          
            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            `).join('')
            : `
                                <div class="batch-empty-state">
                                    No batches assigned for this lecture yet.
                                </div>
                            `
        }
                </div>
            </div>

        </div>
    </div>
</div>
`;

    let footerHTML = "";

    const timeTableId = lecture.batchList?.[0]?.lecture_id;
    const subject = lecture.batchList?.[0]?.subject_id;

    // ✅ Ongoing + Online → Join button
    if (lecture.status === 'Ongoing' && lecture.lectureType === 'Online') {
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
            <button class="custom-success-btn px-5"
                onclick="window.open('${lecture.joinLink}', '_blank')">
                Join Now
            </button>
        </div>
    `;
    }

    // ✅ Completed → Upload Recording
    if (lecture.status === 'Upcoming') {
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
                <button class="custom-btn px-5" onclick='openUpdateLectureStatusModal(${JSON.stringify(lecture)})'>
                    Update Status
                </button>
        </div>
    `;
    }
    if (lecture.status === 'Pending Confirmation' && lecture.hasPermissionUploadRecording) {
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
                <button class="custom-btn px-5" onclick='openUpdateLectureStatusModal(${JSON.stringify(lecture)})'>
                    Update Status
                </button>
            <button class="custom-btn px-5"
                onclick="window.location.href='lecture-recording?lecture-id=${timeTableId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}'">
                Lecture Recording
            </button>
        </div>
    `;
    }
    if (lecture.status === 'Completed' && lecture.hasPermissionUploadRecording) {
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
              
            <button class="custom-btn px-5"
                onclick="window.location.href='lecture-recording?lecture-id=${timeTableId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}'">
                Lecture Recording
            </button>
        </div>
    `;
    }

    // ✅ Set HTML
    modalFooter.innerHTML = footerHTML;
    modal.show();
}

function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {

        // 🔴 Destroy existing tooltip (VERY IMPORTANT)
        if (bootstrap.Tooltip.getInstance(el)) {
            bootstrap.Tooltip.getInstance(el).dispose();
        }

        const image = el.dataset.image;
        const batch = el.dataset.batch;
        const lecturer = el.dataset.lecturer;

        const tooltipHTML = `
            <div class="lecture-tooltip">
                <img src="${image}" class="tooltip-cover">
                <div class="tooltip-info d-flex flex-column justify-content-center align-content-start">
                    <div class="tooltip-batch">${batch}</div>
                    <div class="tooltip-lecturer">${lecturer}</div>
                </div>
            </div>
        `;

        new bootstrap.Tooltip(el, {
            title: tooltipHTML,
            html: true,
            sanitize: false,
            trigger: 'hover focus',
            container: 'body' // ✅ REQUIRED for dynamic layouts
        });
    });
}

function initAttendancePopovers() {

    // Remove old popovers (important when rerender calendar)
    document.querySelectorAll('.tt-mini').forEach(el => {
        const instance = bootstrap.Popover.getInstance(el);
        if (instance) instance.dispose();
    });

    document.querySelectorAll('.tt-mini').forEach(el => {
        let lectureId = el.dataset.lectureId;
        const allLecturesArray = Object.values(lectures).flat();
        const lecture = allLecturesArray.find(l => l.lectureId == lectureId);

        const name = lecture.subject || 'Subject';
        const lecturer = lecture.lecturer || 'Lecture';
        const type = lecture.lectureType || '';
        const start = lecture.startTime || '';
        const end = lecture.endTime || '';
        const thumb = lecture.subjectImage || '';
        const id = lecture.lectureId || '';
        const date = lecture.date || '';
        const status = lecture.status || '';

        const statusClass = (lecture.status || '').toLowerCase();
        const isRescheduleRelated = !!(lecture.isRescheduled || lecture.hasRescheduleTarget);

        const content = `
        <div class="pro-pop">
          <div class="pro-pop-head">
            <div class="pro-pop-icon">
              <img src="${thumb}" onerror="this.src='https://dashboard.enviiz.com/assets/imgs/common/empty-thumbnail.webp'">
            </div>
  
            <div class="pro-pop-titlewrap">
              <div class="pro-pop-title">${name}</div>
               <div class="pro-pop-sub">
                <span class="pro-pop-pill ${statusClass}">${status}</span>
                ${isRescheduleRelated ? '<span class="pro-pop-pill reschedule">Rescheduled</span>' : ''}
               
              </div>
            </div>
          </div>
  
           <div class="pro-pop-row">
            <span class="pro-pop-label">Lecturer</span>
            <span class="pro-pop-value">${lecturer}</span>
          </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Date</span>
            <span class="pro-pop-value">${date}</span>
          </div>

          <div class="pro-pop-row">
            <span class="pro-pop-label">Time</span>
            <span class="pro-pop-value">${start} - ${end}</span>
          </div>
          
            <div class="pro-pop-row">
            <span class="pro-pop-label">Attendance Status</span>
            <span class="pro-pop-value">${status}</span>
          </div>
          ${lecture.audienceLabel ? `
          <div class="pro-pop-row">
            <span class="pro-pop-label">Audience</span>
            <span class="pro-pop-value">${escAudience(lecture.audienceLabel)}</span>
          </div>` : ''}

          ${reschedulePopRowsHtml(lecture)}
           
        </div>
      `;

        new bootstrap.Popover(el, {
            trigger: 'hover focus',
            html: true,
            placement: 'auto',
            container: 'body',
            customClass: 'pro-popover',
            title: '',
            content
        });
    });
}

function initTooltips2() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {

        // destroy old tooltip
        const old = bootstrap.Tooltip.getInstance(el);
        if (old) old.dispose();

        const lectureId = el.dataset.id;
        const allLecturesArray = Object.values(lectures).flat();
        const lecture = allLecturesArray.find(l => l.lectureId == lectureId);

        const image = lecture.programmeImage || '';
        const title = lecture.title || lecture.batch || 'Lecture';
        const programme = lecture.programme || '';
        const subject = lecture.subject || '';
        const time = lecture.startTime + ' - ' + lecture.endTime || '';

        const name = lecture.title;
        const date = lecture.date;
        const type = lecture.lectureType;
        const status = lecture.status;

        const lecturer = lecture.lecturerName;

        const typeClass = type.toLowerCase();
        const statusClass = status.toLowerCase();
        const isRescheduleRelated = !!(lecture.isRescheduled || lecture.hasRescheduleTarget);

        const content = `
        <div class="pro-pop border-0">
          <div class="pro-pop-head">
            <div class="pro-pop-icon">
              <img src="${image}" onerror="this.src='https://dashboard.enviiz.com/assets/imgs/common/empty-thumbnail.webp'">
            </div>
  
            <div class="pro-pop-titlewrap">
            
              <div class="pro-pop-title text-start pro-pop-value mt-2">${name}</div>
              <div class="pro-pop-sub">
                <span class="pro-pop-pill ${typeClass}">${type} Lecture</span>
                ${isRescheduleRelated ? '<span class="pro-pop-pill reschedule">Rescheduled</span>' : ''}
               
              </div>
            </div>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Programme</span>
            <span class="pro-pop-value">${programme}</span>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Subject</span>
            <span class="pro-pop-value">${subject}</span>
          </div>
          
       
          <div class="pro-pop-row">
            <span class="pro-pop-label">Lcturer</span>
            <span class="pro-pop-value">${lecturer}</span>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Date</span>
            <span class="pro-pop-value">${date}</span>
          </div>

          <div class="pro-pop-row">
            <span class="pro-pop-label">Time</span>
            <span class="pro-pop-value">${time}</span>
          </div>
          <div class="pro-pop-row">
            <span class="pro-pop-label">Status</span>
            <span class="pro-pop-pill ${statusClass}">${status.toUpperCase()}</span>
          </div>
          <div class="pro-pop-row">
            <span class="pro-pop-label">Audience</span>
            <span class="pro-pop-value">${escAudience(lectureAudienceText(lecture))}</span>
          </div>

          ${reschedulePopRowsHtml(lecture)}
          
        </div>
      `;
        new bootstrap.Tooltip(el, {
            title: content,
            html: true,
            sanitize: false,
            trigger: 'hover focus',
            container: 'body',
            placement: 'auto',
            boundary: 'window',
            offset: [0, 10],
            customClass: 'tt-premium-tooltip',
            // IMPORTANT: makes Bootstrap keep your padding/rounded/shadow correctly
            template: `
          <div class="tooltip tt-premium-tooltip" role="tooltip">
            <div class="tooltip-arrow"></div>
            <div class="tooltip-inner"></div>
          </div>
        `
        });
    });
}
function ttStatusIcon(status) {
    if (status.includes("completed")) return `<iconify-icon icon="solar:check-circle-bold"></iconify-icon>`;
    if (status.includes("cancel")) return `<iconify-icon icon="solar:close-circle-bold"></iconify-icon>`;
    if (status.includes("ongoing")) return `<iconify-icon icon="solar:play-circle-bold"></iconify-icon>`;
    if (status.includes("pending")) return `<iconify-icon icon="tabler:reload"></iconify-icon>`;
    if (status.includes("present")) return `<iconify-icon icon="ph:seal-check-bold"></iconify-icon>`;
    if (status.includes("absent")) return `<iconify-icon icon="carbon:close-outline"></iconify-icon>`;
    if (status.includes("watched")) return `<iconify-icon icon="mynaui:video"></iconify-icon>`;
    return `<iconify-icon icon="solar:clock-circle-bold"></iconify-icon>`; // upcoming/default
}

// ===================================================================
// Update Lecture Status
// ===================================================================

const LECTURE_STATUSES = [
    { id: 1, name: 'Active' },
    { id: 2, name: 'Cancelled' },
    { id: 3, name: 'Postponed' },
    { id: 4, name: 'Study Leave' },
    { id: 5, name: 'Completed' }
];

// "Active" isn't offered as a manual target — only the other four are.
// Change this array if you meant something different by "skip one".
const SKIPPED_STATUS_IDS = [1];
let ulsCurrentLecture = null;

const LECTURE_MONTH_NAMES = {
    january: 0, february: 1, march: 2, april: 3,
    may: 4, june: 5, july: 6, august: 7,
    september: 8, october: 9, november: 10, december: 11
};

function parseLectureDateParts(dateStr) {
    if (!dateStr) return null;
    // Expected format: "19 August, 2026"
    const match = dateStr.match(/^(\d{1,2})\s+([A-Za-z]+),?\s+(\d{4})$/);
    if (!match) return null;

    const day = parseInt(match[1], 10);
    const month = LECTURE_MONTH_NAMES[match[2].toLowerCase()];
    const year = parseInt(match[3], 10);

    if (month === undefined || isNaN(day) || isNaN(year)) return null;
    return { day, month, year };
}

function combineLectureDateTime(dateStr, timeStr) {
    const dateParts = parseLectureDateParts(dateStr);
    if (!dateParts || !timeStr) return null;

    const [hours, minutes] = timeStr.split(':').map(Number);
    if (isNaN(hours) || isNaN(minutes)) return null;

    return new Date(dateParts.year, dateParts.month, dateParts.day, hours, minutes, 0, 0);
}

function getAvailableLectureStatuses(lecture) {
    const now = new Date();
    const end = combineLectureDateTime(lecture.date, lecture.endTime24);

    // Active is never offered as a manual target
    const base = LECTURE_STATUSES.filter(s => !SKIPPED_STATUS_IDS.includes(s.id));

    // Lecture has ended -> Completed is always available.
    // Other statuses (Cancelled / Postponed / Study Leave) only with permission.
    if (end && end < now) {
        return lecture.hasPermissioncompleteClassUpdateAnotherStatus
            ? base
            : base.filter(s => s.id === 5);
    }

    // Not ended yet (upcoming or in progress) -> Completed is not valid yet
    return base.filter(s => s.id !== 5);
}

function openUpdateLectureStatusModal(lecture) {
    ulsCurrentLecture = lecture;
    document.getElementById("uls-lecture-id").value = lecture.lectureId;

    const select = document.getElementById("uls-status-select");
    const availableStatuses = getAvailableLectureStatuses(lecture);

    select.innerHTML = availableStatuses
        .map(s => `<option value="${s.id}">${s.name}</option>`)
        .join('');

    // Reset everything first
    document.getElementById("uls-cancel-reason").value = "";
    document.getElementById("uls-postpone-date").value = "";
    document.getElementById("uls-postpone-start").value = "";
    document.getElementById("uls-postpone-end").value = "";
    document.getElementById("uls-complete-start").value = "";
    document.getElementById("uls-complete-end").value = "";
    hideUlsError();

    // If a real status is already set (not the default "Active"),
    // preselect it and reload whatever was previously stored for it —
    // so reopening the modal shows current state, not a blank form.
    const currentStatusId = lecture.timeTableStatusId;
    const isSelectable = availableStatuses.some(s => s.id === currentStatusId);

    if (isSelectable) {
        select.value = currentStatusId;

        if (currentStatusId === 2) { // Cancelled
            document.getElementById("uls-cancel-reason").value = lecture.reason || "";
        }

        if (currentStatusId === 3) { // Postponed
            document.getElementById("uls-postpone-date").value = lecture.linkedDate || "";
            document.getElementById("uls-postpone-start").value = lecture.linkedStartTime || "";
            document.getElementById("uls-postpone-end").value = lecture.linkedEndTime || "";
        }

        if (currentStatusId === 5) { // Completed
            document.getElementById("uls-complete-start").value = lecture.startTime24 || "";
            document.getElementById("uls-complete-end").value = lecture.endTime24 || "";
        }
    } else {
        // No manual status set yet (or previous status no longer valid
        // given timing) — default the Completed fields to the
        // originally scheduled time as a sensible starting point.
        document.getElementById("uls-complete-start").value = lecture.startTime24 || "";
        document.getElementById("uls-complete-end").value = lecture.endTime24 || "";
    }

    toggleUlsConditionalFields();

    new bootstrap.Modal(document.getElementById('updateLectureStatusModal')).show();
}
document.addEventListener("change", (e) => {
    if (e.target && e.target.id === "uls-status-select") {
        toggleUlsConditionalFields();
    }
});

function toggleUlsConditionalFields() {
    const statusId = parseInt(document.getElementById("uls-status-select").value, 10);
    document.getElementById("uls-cancel-reason-wrap").classList.toggle("d-none", statusId !== 2);
    document.getElementById("uls-postpone-wrap").classList.toggle("d-none", statusId !== 3);
    document.getElementById("uls-complete-wrap").classList.toggle("d-none", statusId !== 5);
}

function hideUlsError() {
    const el = document.getElementById("uls-error");
    el.classList.add("d-none");
    el.textContent = "";
}

function showUlsError(msg) {
    const el = document.getElementById("uls-error");
    el.textContent = msg;
    el.classList.remove("d-none");
}

function submitLectureStatusUpdate() {
    hideUlsError();

    const lectureId = document.getElementById("uls-lecture-id").value;
    const statusId = parseInt(document.getElementById("uls-status-select").value, 10);

    const formData = new FormData();
    formData.append("lecture_id", lectureId);
    formData.append("status", statusId);
    if (ulsCurrentLecture?.linkedTimeTableId) {
        formData.append("linked_time_table_id", ulsCurrentLecture.linkedTimeTableId);
    }

    if (statusId === 2) { // Cancelled
        const reason = document.getElementById("uls-cancel-reason").value.trim();
        if (!reason) return showUlsError("Please provide a reason for cancellation.");
        formData.append("reason", reason);
    }

    if (statusId === 3) { // Postponed
        const date = document.getElementById("uls-postpone-date").value;
        const start = document.getElementById("uls-postpone-start").value;
        const end = document.getElementById("uls-postpone-end").value;
        if (!date || !start || !end) return showUlsError("Please provide the next date, start and end time.");
        if (end <= start) return showUlsError("End time must be after start time.");
        formData.append("postponed_date", date);
        formData.append("postponed_start_time", start);
        formData.append("postponed_end_time", end);
    }

    if (statusId === 5) { // Completed
        const start = document.getElementById("uls-complete-start").value;
        const end = document.getElementById("uls-complete-end").value;
        if (!start || !end) return showUlsError("Please provide the actual start and end time.");
        if (end <= start) return showUlsError("End time must be after start time.");
        formData.append("start_time", start);
        formData.append("end_time", end);
    }

    const statusName = LECTURE_STATUSES.find(s => s.id === statusId)?.name || "";
    ulsConfirm(
        `Are you sure you want to mark this lecture as "${statusName}"? This action cannot be undone.`,
        () => sendLectureStatusUpdate(formData)
    );
}

function sendLectureStatusUpdate(formData) {
    const submitBtn = document.getElementById("uls-submit-btn");
    submitBtn.disabled = true;

    fetch('api/updateLectureStatus', { method: 'POST', body: formData })
        .then((response) => response.text())
        .then((data) => {
            console.log("Response from updateLectureStatus:", data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                showToast("success", "Lecture status updated successfully.");
                if (response.paymentWarning) {
                    showToast("warning", response.paymentWarning);
                }
                bootstrap.Modal.getInstance(document.getElementById('updateLectureStatusModal'))?.hide();
                bootstrap.Modal.getInstance(document.getElementById('lectureDetailsModal'))?.hide();
                getLectureScheduleData();
            } else {
                renderUlsErrors(response);
            }
        })
        .catch(() => showUlsError("An error occurred. Please try again later."))
        .finally(() => { submitBtn.disabled = false; });
}

// Backend returns field-named error properties (e.g. reason, postponed_start_time,
// start_time, time_table_status, error) — show the first one it finds.
function renderUlsErrors(response) {
    const order = ['error', 'time_table_status', 'reason', 'postponed_date',
        'postponed_start_time', 'postponed_end_time', 'start_time', 'end_time'];
    for (const key of order) {
        if (response[key]) {
            showUlsError(response[key]);
            return;
        }
    }
    showUlsError("Unable to update lecture status.");
}
// Minimal confirm wrapper — swap the body of this function for your
// existing cancel-schedule-payment confirm modal if you'd rather reuse it.
function ulsConfirm(message, onConfirm) {
    document.getElementById("ulsConfirmMessage").textContent = message;
    const modalEl = document.getElementById("ulsConfirmModal");
    const modal = new bootstrap.Modal(modalEl);
    const btn = document.getElementById("ulsConfirmProceedBtn");

    const handler = () => {
        modal.hide();
        btn.removeEventListener("click", handler);
        onConfirm();
    };
    btn.addEventListener("click", handler);
    modal.show();
}