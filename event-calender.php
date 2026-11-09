<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once './includes/Project.php';

/* ------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------ */
function ec_json($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function ec_esc($v)
{
    return addslashes(trim((string)$v));
}

function ec_valid_date($v)
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$v);
    return ($d && $d->format('Y-m-d') === $v);
}

function ec_valid_time($v)
{
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v);
}

const EC_TYPES = ['event', 'workshop', 'site_visit', 'deadline', 'orientation'];

function ec_ensure_table()
{
    Database::search("CREATE TABLE IF NOT EXISTS `academic_events` (
        `event_id` INT NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `event_type` VARCHAR(30) NOT NULL DEFAULT 'event',
        `start_date` DATE NOT NULL,
        `start_time` TIME NULL,
        `end_time` TIME NULL,
        `location` VARCHAR(255) NULL,
        `is_highlighted` TINYINT(1) NOT NULL DEFAULT 0,
        `status` TINYINT(1) NOT NULL DEFAULT 1,
        `date_added` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`event_id`),
        KEY `idx_start_date` (`start_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ------------------------------------------------------------------
 * Session + permissions
 * ------------------------------------------------------------------ */
$isAjax = isset($_GET['action']) || isset($_POST['action']);

if (!SessionUser::hasValidSession()) {
    if ($isAjax) ec_json(['error' => 'Authentication Failed!'], 401);
    header("Location: ./index");
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    if ($isAjax) ec_json(['error' => 'Authentication Failed!'], 401);
    header("Location: ./index");
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

// Who can add / edit / delete events (adjust to your own permission key if needed)
$canEdit = SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole);

/* ------------------------------------------------------------------
 * AJAX endpoints (same file)
 * ------------------------------------------------------------------ */
if ($isAjax) {
    ec_ensure_table();
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $month = $_GET['month'] ?? '';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            ec_json(['error' => 'Invalid month'], 400);
        }
        $start = $month . '-01';
        $end   = date('Y-m-t', strtotime($start));

        $sql = "SELECT `event_id`, `title`, `event_type`, `start_date`, `start_time`, `end_time`, `location`, `is_highlighted`
                FROM `academic_events`
                WHERE `status` = 1 AND `start_date` BETWEEN '{$start}' AND '{$end}'";

        $type = $_GET['type'] ?? '';
        if ($type !== '' && in_array($type, EC_TYPES, true)) {
            $sql .= " AND `event_type` = '{$type}'";
        }
        $sql .= " ORDER BY `start_date` ASC, `start_time` ASC";

        $rs = Database::search($sql);
        $events = [];
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                $events[] = [
                    'id'         => (int)$r['event_id'],
                    'title'      => $r['title'],
                    'type'       => $r['event_type'],
                    'date'       => $r['start_date'],
                    'start_time' => $r['start_time'] ? substr($r['start_time'], 0, 5) : '',
                    'end_time'   => $r['end_time'] ? substr($r['end_time'], 0, 5) : '',
                    'location'   => $r['location'] ?? '',
                    'highlight'  => (int)$r['is_highlighted'] === 1,
                ];
            }
        }
        ec_json(['done' => true, 'events' => $events]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ec_json(['error' => 'Invalid request'], 400);
    }
    if (!$canEdit) {
        ec_json(['error' => 'You do not have permission to do this.'], 403);
    }

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $type      = $_POST['type'] ?? '';
        $date      = $_POST['date'] ?? '';
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime   = trim($_POST['end_time'] ?? '');
        $location  = trim($_POST['location'] ?? '');
        $highlight = !empty($_POST['highlight']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 255) ec_json(['error' => 'Title is required (max 255 characters).'], 422);
        if (!in_array($type, EC_TYPES, true))          ec_json(['error' => 'Invalid event type.'], 422);
        if (!ec_valid_date($date))                     ec_json(['error' => 'Invalid date.'], 422);
        if ($startTime !== '' && !ec_valid_time($startTime)) ec_json(['error' => 'Invalid start time.'], 422);
        if ($endTime !== '' && !ec_valid_time($endTime))     ec_json(['error' => 'Invalid end time.'], 422);
        if ($endTime !== '' && $startTime === '')            ec_json(['error' => 'Please enter a start time.'], 422);
        if ($startTime !== '' && $endTime !== '' && $endTime <= $startTime) ec_json(['error' => 'End time must be after start time.'], 422);
        if (mb_strlen($location) > 255)                ec_json(['error' => 'Location is too long.'], 422);

        $t   = ec_esc($title);
        $loc = $location === '' ? 'NULL' : "'" . ec_esc($location) . "'";
        $st  = $startTime === '' ? 'NULL' : "'{$startTime}:00'";
        $et  = $endTime === '' ? 'NULL' : "'{$endTime}:00'";

        if ($id > 0) {
            Database::search("UPDATE `academic_events` SET `title`='{$t}', `event_type`='{$type}', `start_date`='{$date}',
                `start_time`={$st}, `end_time`={$et}, `location`={$loc}, `is_highlighted`={$highlight}
                WHERE `event_id`={$id} AND `status`=1");
        } else {
            Database::search("INSERT INTO `academic_events`
                (`title`, `event_type`, `start_date`, `start_time`, `end_time`, `location`, `is_highlighted`, `status`)
                VALUES ('{$t}', '{$type}', '{$date}', {$st}, {$et}, {$loc}, {$highlight}, 1)");
        }
        ec_json(['done' => true]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) ec_json(['error' => 'Invalid event.'], 422);
        Database::search("UPDATE `academic_events` SET `status`=0 WHERE `event_id`={$id}");
        ec_json(['done' => true]);
    }

    ec_json(['error' => 'Unknown action'], 400);
}

/* ------------------------------------------------------------------
 * Page
 * ------------------------------------------------------------------ */
$today = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Event Calendar - Admin Dashboard | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

    <style>
        .ec-wrap { overflow-x: auto; }
        .ec-table { width: 100%; min-width: 760px; table-layout: fixed; border-collapse: collapse; }
        .ec-table th, .ec-table td { border: 1px solid var(--line-color); }
        .ec-table th { text-align: center; font-size: 12px; font-weight: 500; color: var(--sub-text); padding: 14px 4px; border-left: 0; border-right: 0; border-top: 0; }
        .ec-date { text-align: center; font-size: 14px; color: var(--dark-color); padding: 6px 4px; height: 28px; }
        .ec-date span { display: inline-block; min-width: 26px; padding: 0 6px; }
        .ec-date.ec-today span { background: var(--dark-theme); color: #fff; font-weight: 600; }
        .ec-day { height: 78px; vertical-align: top; padding: 4px; }
        .ec-day.ec-clickable { cursor: pointer; }
        .ec-day.ec-clickable:hover { background: var(--light-theme); }
        .ec-blank { background: var(--table-header-color); }
        .ec-chip {
            display: block; padding: 2px 6px; margin-bottom: 3px; font-size: 12px; line-height: 1.5;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer;
            border-left: 3px solid currentColor;
        }
        .ec-more { font-size: 12px; color: var(--dark-theme); cursor: pointer; font-weight: 600; }
        .ec-legend-dot { width: 12px; height: 12px; display: inline-block; margin-right: 6px; }
        input.ec-check { width: 20px; height: 20px; }
        .ec-form input::placeholder { color: var(--sub-text); }
    </style>
</head>

<body class="overflow-hidden">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">
            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include "includes/side-menu.php";
                load_side_menu::load("event-calender");
                ?>
            </div>
            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                <div class="row p-0 m-0 g-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "./includes/header.php";
                        selectItem::setSelected("event-calender");
                        ?>
                    </div>

                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                        <div class="row p-4 m-0 g-0">

                            <!-- Title card -->
                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                <div class="row align-items-center p-0 m-0 g-0">
                                    <div class="col-12 col-xxl-8">
                                        <div class="row">
                                            <span class="main-topic lh-lg">Event Calendar</span>
                                            <span class="text-14 sub-text lh-base mt-1">Track events, workshops, site visits and deadlines at a glance.</span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-xxl-4 mt-3 mt-xxl-0">
                                        <div class="row gx-3 justify-content-end">
                                            <div class="col-12 col-md-6 d-flex flex-column justify-content-end mb-2 mb-md-0">
                                                <select id="type-filter" class="form-select custom-select">
                                                    <option value="">All Event Types</option>
                                                    <option value="event">Events</option>
                                                    <option value="workshop">Workshops</option>
                                                    <option value="site_visit">Site Visits</option>
                                                    <option value="deadline">Deadlines</option>
                                                    <option value="orientation">Orientations</option>
                                                </select>
                                            </div>
                                            <?php if ($canEdit) { ?>
                                                <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                                                    <button class="custom-btn w-100" type="button" onclick="openForm(null, null)">Add Event</button>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Calendar card -->
                            <div class="col-12 p-4 component-theme">
                                <div class="d-flex justify-content-between align-items-center mb-4 gap-2">
                                    <button class="p-2 px-3 bl-solid br-0 d-flex align-items-center component-theme previous-button" type="button" onclick="changeMonth(-1)">
                                        <iconify-icon icon="tabler:arrow-left" width="18" height="18" class="text-dark-color pe-2"></iconify-icon>
                                        <span class="text-14 main-text fw-medium">Previous</span>
                                    </button>
                                    <span class="main-topic text-center" id="cal-title" style="font-size: 20px !important;">Event Calendar</span>
                                    <button class="p-2 px-3 bl-solid br-0 d-flex align-items-center component-theme next-button" type="button" onclick="changeMonth(1)">
                                        <span class="text-14 main-text fw-medium">Next</span>
                                        <iconify-icon icon="tabler:arrow-right" width="18" height="18" class="text-dark-color ps-2"></iconify-icon>
                                    </button>
                                </div>

                                <div class="ec-wrap">
                                    <table class="ec-table">
                                        <thead>
                                            <tr>
                                                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cal-body"></tbody>
                                    </table>
                                </div>

                                <div class="d-flex flex-wrap gap-4 mt-4" id="cal-legend"></div>
                            </div>
                        </div>
                        <div class="flex-grow-1"></div>
                        <footer>
                            <?php include './includes/footer.php' ?>
                        </footer>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View event modal -->
    <div class="modal fade br-0" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered br-0">
            <div class="modal-content p-4 br-0">
                <div class="modal-header p-0 border-0">
                    <h1 class="modal-title text-16 text-dark-color">Event Details</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 mt-3" id="view-body"></div>
                <div class="modal-footer p-0 border-0 mt-3 d-flex gap-2 justify-content-end" id="view-actions"></div>
            </div>
        </div>
    </div>

    <!-- Day list modal -->
    <div class="modal fade br-0" id="dayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered br-0">
            <div class="modal-content p-4 br-0">
                <div class="modal-header p-0 border-0">
                    <h1 class="modal-title text-16 text-dark-color" id="day-title">Events</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 mt-3 d-flex flex-column gap-2" id="day-body"></div>
            </div>
        </div>
    </div>

    <?php if ($canEdit) { ?>
        <!-- Add / edit modal -->
        <div class="modal fade br-0" id="formModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered br-0">
                <div class="modal-content p-4 br-0">
                    <div class="modal-header p-0 border-0">
                        <h1 class="modal-title text-16 text-dark-color" id="form-title">Add Event</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 mt-3 ec-form">
                        <input type="hidden" id="f-id" value="0" style="height:auto;">
                        <div class="mb-3">
                            <label class="text-14 main-text mb-1 required">Title</label>
                            <input type="text" id="f-title" class="form-control custom-input" maxlength="255" placeholder="e.g. Hospital site visit – CG-01">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="text-14 main-text mb-1 required">Type</label>
                                <select id="f-type" class="form-select custom-select">
                                    <option value="event">Event</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="site_visit">Site Visit</option>
                                    <option value="deadline">Deadline</option>
                                    <option value="orientation">Orientation</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="text-14 main-text mb-1 required">Date</label>
                                <input type="date" id="f-date" class="form-control custom-input">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="text-14 main-text mb-1 not-required">Start Time</label>
                                <input type="time" id="f-start" class="form-control custom-input">
                            </div>
                            <div class="col-6">
                                <label class="text-14 main-text mb-1 not-required">End Time</label>
                                <input type="time" id="f-end" class="form-control custom-input">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-14 main-text mb-1 not-required">Location</label>
                            <input type="text" id="f-location" class="form-control custom-input" maxlength="255" placeholder="Optional">
                        </div>
                        <div class="d-flex align-items-center">
                            <input type="checkbox" id="f-highlight" class="form-check-input ec-check me-2 mt-0">
                            <label for="f-highlight" class="text-14 main-text">Highlight this event</label>
                        </div>
                        <span id="f-error" class="text-dark-red text-12 d-block mt-3"></span>
                    </div>
                    <div class="modal-footer p-0 border-0 mt-3 d-flex gap-2 justify-content-end">
                        <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="custom-btn px-4" id="f-save" onclick="saveEvent()">Save</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

    <script>
        const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
        const TODAY = '<?= $today ?>';
        const ENDPOINT = window.location.pathname;
        const MAX_VISIBLE = 2;

        const TYPES = {
            event:       { label: 'Event',       cls: 'status-blue' },
            workshop:    { label: 'Workshop',    cls: 'status-purple' },
            site_visit:  { label: 'Site Visit',  cls: 'status-green' },
            deadline:    { label: 'Deadline',    cls: 'status-red' },
            orientation: { label: 'Orientation', cls: 'status-orange' }
        };

        let cur = new Date(TODAY + 'T00:00:00');
        cur = new Date(cur.getFullYear(), cur.getMonth(), 1);
        let events = [];
        let loadToken = 0;

        const $body = document.getElementById('cal-body');

        /* ---------- helpers ---------- */
        const pad = n => String(n).padStart(2, '0');
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const monthKey = d => d.getFullYear() + '-' + pad(d.getMonth() + 1);

        function fmtTime(t) {
            if (!t) return '';
            const [h, m] = t.split(':').map(Number);
            return (h % 12 || 12) + ':' + pad(m) + ' ' + (h >= 12 ? 'PM' : 'AM');
        }
        function fmtDate(s) {
            const d = new Date(s + 'T00:00:00');
            return d.toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        }
        function timeRange(ev) {
            if (!ev.start_time) return 'All Day';
            return fmtTime(ev.start_time) + (ev.end_time ? ' – ' + fmtTime(ev.end_time) : '');
        }

        function toast(type, msg) {
            const box = document.querySelector('.notifications');
            if (!box) return;
            const el = document.createElement('li');
            el.className = 'custom-toast ' + type;
            el.innerHTML = '<div class="column"><iconify-icon icon="' + (type === 'success' ? 'mdi:check-circle' : 'mdi:alert-circle') +
                '"></iconify-icon><div class="custom-toast-text"><span class="custom-toast-type">' + (type === 'success' ? 'Success' : 'Error') +
                '</span><span class="custom-toast-message">' + esc(msg) + '</span></div></div>';
            box.appendChild(el);
            setTimeout(() => { el.classList.add('hide'); setTimeout(() => el.remove(), 300); }, 3000);
        }

        /* ---------- data ---------- */
        async function loadEvents() {
            const token = ++loadToken;
            const params = new URLSearchParams({ action: 'list', month: monthKey(cur), type: document.getElementById('type-filter').value });
            try {
                const res = await fetch(ENDPOINT + '?' + params.toString(), { credentials: 'same-origin' });
                const data = await res.json();
                if (token !== loadToken) return; // a newer request replaced this one
                if (!data.done) throw new Error(data.error || 'Failed to load events');
                events = data.events;
            } catch (e) {
                if (token !== loadToken) return;
                events = [];
                toast('error', e.message || 'Failed to load events');
            }
            render();
        }

        /* ---------- calendar ---------- */
        function render() {
            const y = cur.getFullYear(), m = cur.getMonth();
            document.getElementById('cal-title').textContent =
                'Event Calendar - ' + cur.toLocaleString('en-US', { month: 'long', year: 'numeric' });

            const first = new Date(y, m, 1).getDay();
            const days = new Date(y, m + 1, 0).getDate();
            const weeks = Math.ceil((first + days) / 7);

            const byDate = {};
            events.forEach(ev => { (byDate[ev.date] = byDate[ev.date] || []).push(ev); });

            let html = '';
            for (let w = 0; w < weeks; w++) {
                let dateRow = '<tr>', evRow = '<tr>';
                for (let c = 0; c < 7; c++) {
                    const day = w * 7 + c - first + 1;
                    if (day < 1 || day > days) {
                        dateRow += '<td class="ec-date ec-blank"></td>';
                        evRow += '<td class="ec-day ec-blank"></td>';
                        continue;
                    }
                    const key = y + '-' + pad(m + 1) + '-' + pad(day);
                    const list = byDate[key] || [];
                    const isToday = key === TODAY;

                    dateRow += '<td class="ec-date' + (isToday ? ' ec-today' : '') + '"><span>' + day + '</span></td>';

                    let cell = '';
                    list.slice(0, MAX_VISIBLE).forEach(ev => {
                        const t = TYPES[ev.type] || TYPES.event;
                        cell += '<span class="ec-chip ' + t.cls + (ev.highlight ? ' fw-bold' : '') + '" data-event="' + ev.id +
                            '" title="' + esc(ev.title) + '">' + (ev.start_time ? esc(fmtTime(ev.start_time)) + ' ' : '') + esc(ev.title) + '</span>';
                    });
                    if (list.length > MAX_VISIBLE) {
                        cell += '<span class="ec-more" data-more="' + key + '">+' + (list.length - MAX_VISIBLE) + ' more</span>';
                    }
                    evRow += '<td class="ec-day' + (CAN_EDIT ? ' ec-clickable' : '') + '" data-date="' + key + '">' + cell + '</td>';
                }
                html += dateRow + '</tr>' + evRow + '</tr>';
            }
            $body.innerHTML = html;
        }

        function renderLegend() {
            document.getElementById('cal-legend').innerHTML = Object.keys(TYPES).map(k =>
                '<div class="d-flex align-items-center"><span class="ec-legend-dot ' + TYPES[k].cls + '"></span><span class="text-12 sub-text">' +
                TYPES[k].label + '</span></div>').join('');
        }

        function changeMonth(delta) {
            cur = new Date(cur.getFullYear(), cur.getMonth() + delta, 1);
            loadEvents();
        }

        /* ---------- clicks ---------- */
        $body.addEventListener('click', function (e) {
            const chip = e.target.closest('[data-event]');
            if (chip) { openView(parseInt(chip.dataset.event, 10)); return; }
            const more = e.target.closest('[data-more]');
            if (more) { openDay(more.dataset.more); return; }
            const cell = e.target.closest('[data-date]');
            if (cell && CAN_EDIT) openForm(null, cell.dataset.date);
        });

        document.getElementById('type-filter').addEventListener('change', loadEvents);

        /* ---------- modals ---------- */
        const modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
        function swapModal(fromId, toFn) {
            const el = document.getElementById(fromId);
            if (el.classList.contains('show')) {
                el.addEventListener('hidden.bs.modal', toFn, { once: true });
                modal(fromId).hide();
            } else {
                toFn();
            }
        }

        function openView(id) {
            const ev = events.find(x => x.id === id);
            if (!ev) return;
            const t = TYPES[ev.type] || TYPES.event;
            const row = (label, val) => '<div class="d-flex mb-3"><span class="text-14 sub-text" style="width:110px;">' + label +
                '</span><span class="text-14 main-text flex-grow-1">' + val + '</span></div>';

            document.getElementById('view-body').innerHTML =
                '<div class="mb-3"><span class="table-btn ' + t.cls + '" style="padding:4px 14px;">' + t.label + '</span>' +
                (ev.highlight ? ' <span class="table-btn status-red ms-1" style="padding:4px 14px;">Highlighted</span>' : '') + '</div>' +
                '<h2 class="text-16 text-dark-color mb-4">' + esc(ev.title) + '</h2>' +
                row('Date', esc(fmtDate(ev.date))) +
                row('Time', esc(timeRange(ev))) +
                row('Location', ev.location ? esc(ev.location) : '<span class="sub-text">—</span>');

            document.getElementById('view-actions').innerHTML = CAN_EDIT
                ? '<button class="custom-danger-btn px-4" onclick="deleteEvent(' + ev.id + ')">Delete</button>' +
                  '<button class="custom-btn px-4" onclick="editFromView(' + ev.id + ')">Edit</button>'
                : '';
            swapModal('dayModal', () => modal('viewModal').show());
        }

        function editFromView(id) {
            const ev = events.find(x => x.id === id);
            swapModal('viewModal', () => openForm(ev, null));
        }

        function openDay(dateKey) {
            const list = events.filter(e => e.date === dateKey);
            document.getElementById('day-title').textContent = fmtDate(dateKey);
            document.getElementById('day-body').innerHTML = list.map(ev => {
                const t = TYPES[ev.type] || TYPES.event;
                return '<div class="bl-solid px-3 py-2 menuItem" onclick="openView(' + ev.id + ')">' +
                    '<div class="d-flex justify-content-between align-items-center gap-2"><span class="text-14 main-text fw-medium">' + esc(ev.title) +
                    '</span><span class="table-btn ' + t.cls + '" style="padding:2px 10px;font-size:12px;">' + t.label + '</span></div>' +
                    '<span class="text-12 sub-text">' + esc(timeRange(ev)) + '</span></div>';
            }).join('');
            modal('dayModal').show();
        }

        function openForm(ev, dateKey) {
            if (!CAN_EDIT) return;
            document.getElementById('form-title').textContent = ev ? 'Edit Event' : 'Add Event';
            document.getElementById('f-id').value = ev ? ev.id : 0;
            document.getElementById('f-title').value = ev ? ev.title : '';
            document.getElementById('f-type').value = ev ? ev.type : 'event';
            document.getElementById('f-date').value = ev ? ev.date : (dateKey || TODAY);
            document.getElementById('f-start').value = ev ? ev.start_time : '';
            document.getElementById('f-end').value = ev ? ev.end_time : '';
            document.getElementById('f-location').value = ev ? ev.location : '';
            document.getElementById('f-highlight').checked = ev ? ev.highlight : false;
            document.getElementById('f-error').textContent = '';
            modal('formModal').show();
        }

        async function post(fd) {
            const res = await fetch(ENDPOINT, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (!data.done) throw new Error(data.error || 'Request failed');
            return data;
        }

        async function saveEvent() {
            const err = document.getElementById('f-error');
            err.textContent = '';

            const title = document.getElementById('f-title').value.trim();
            const date = document.getElementById('f-date').value;
            const start = document.getElementById('f-start').value;
            const end = document.getElementById('f-end').value;

            if (!title) { err.textContent = 'Title is required.'; return; }
            if (!date) { err.textContent = 'Date is required.'; return; }
            if (end && !start) { err.textContent = 'Please enter a start time.'; return; }
            if (start && end && end <= start) { err.textContent = 'End time must be after start time.'; return; }

            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('id', document.getElementById('f-id').value);
            fd.append('title', title);
            fd.append('type', document.getElementById('f-type').value);
            fd.append('date', date);
            fd.append('start_time', start);
            fd.append('end_time', end);
            fd.append('location', document.getElementById('f-location').value.trim());
            if (document.getElementById('f-highlight').checked) fd.append('highlight', '1');

            const btn = document.getElementById('f-save');
            btn.disabled = true;
            try {
                await post(fd);
                modal('formModal').hide();
                toast('success', 'Event saved.');
                // jump to the month of the saved event
                const d = new Date(date + 'T00:00:00');
                cur = new Date(d.getFullYear(), d.getMonth(), 1);
                loadEvents();
            } catch (e) {
                err.textContent = e.message;
            } finally {
                btn.disabled = false;
            }
        }

        async function deleteEvent(id) {
            if (!confirm('Delete this event?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            try {
                await post(fd);
                modal('viewModal').hide();
                toast('success', 'Event deleted.');
                loadEvents();
            } catch (e) {
                toast('error', e.message);
            }
        }

        /* ---------- init ---------- */
        renderLegend();
        render();
        loadEvents();
    </script>
</body>

</html>