<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once './includes/Project.php';
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        // Used by eventCalender.js to decide whether to show the
        // "Schedule a Lecture" CTA inside the mobile no-lectures state.
        $canScheduleLecture = (
            SessionUser::ifUserIsPro($sessionUserRole)
            || SessionUser::ifUserIsAcademicPro($sessionUserRole)
            || UserPermissions::hasPermission('super_lecture_schedule', 2)
        ) ? 'true' : 'false';
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Lecture Schedule - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/calender.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/newCalender.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            <link rel="stylesheet" href="./assets/css/custom-tooltip.css?v=<?= Project::getVersion() ?>">
            <script>
                // Flag consumed by eventCalender.js -> renderMobileLectureList()
                const canScheduleLecture = <?= $canScheduleLecture ?>;
            </script>
        </head>


        <body class="overflow-hidden" onload="initializeLecturerSchedule();">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("lecture-schedule");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("lecture-schedule");
                                ?>
                            </div>
                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include './includes/components/skeleton-header.php';
                                include './includes/components/skeleton-calender.php'; ?>
                            </div>
                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-12 col-lg-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="lecture-schedule">Lecture Schedule</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="lec-schedule-desc">Manage and schedule academic lectures with ease and flexibility.</span>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="bg-transparent border-0 px-3" onclick="window.location = 'lecture-schedule-permissions'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2)) {
                                                    ?>
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" data-i18n="schedule-lecture" onclick="window.location = 'schedule-lecture'">Schedule a Lecture</button>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <style>
                                        /*
  Fallback styles for the mobile calendar markup (.mobile-cal-*).
  Your student portal already renders elements with these exact class
  names (mobile-cal-nav, mobile-cal-title, mobile-cal-weekdays,
  mobile-cal-grid), so these rules almost certainly already live in
  style.css and this file is likely NOT needed.

  Only add this (to newCalender.css, or inline) if the admin mobile
  calendar looks unstyled after wiring up the markup + JS.
*/

                                        .mobile-cal-nav {
                                            display: flex;
                                            align-items: center;
                                            justify-content: space-between;
                                            width: 100%;
                                            margin-bottom: 12px;
                                        }

                                        .mobile-cal-nav .nav-arrow {
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            width: 32px;
                                            height: 32px;
                                            border-radius: 8px;
                                            cursor: pointer;
                                        }

                                        .mobile-cal-title {
                                            font-size: 15px;
                                            font-weight: 600;
                                        }

                                        .mobile-cal-weekdays {
                                            display: grid;
                                            grid-template-columns: repeat(7, 1fr);
                                            width: 100%;
                                            text-align: center;
                                            font-size: 12px;
                                            opacity: 0.65;
                                            margin-bottom: 6px;
                                        }

                                        .mobile-cal-grid {
                                            display: grid;
                                            grid-template-columns: repeat(7, 1fr);
                                            width: 100%;
                                            row-gap: 4px;
                                        }

                                        .mobile-cal-day {
                                            position: relative;
                                            display: flex;
                                            flex-direction: column;
                                            align-items: center;
                                            justify-content: center;
                                            height: 40px;
                                            border-radius: 8px;
                                            font-size: 13px;
                                            cursor: pointer;
                                        }

                                        .mobile-cal-day.empty {
                                            cursor: default;
                                        }

                                        .mobile-cal-day.today .mobile-cal-day-num {
                                            font-weight: 700;
                                        }

                                        .mobile-cal-day.selected {
                                            background: var(--primary-color, #4f46e5);
                                            color: #fff;
                                        }

                                        /* Light rounded background behind the day number when that day has a lecture */
                                        .mobile-cal-day.has-lecture .mobile-cal-day-num {
                                            display: inline-flex;
                                            align-items: center;
                                            justify-content: center;
                                            width: 26px;
                                            height: 26px;
                                            border-radius: 50%;
                                            background: rgba(79, 70, 229, 0.12);
                                            color: var(--primary-color, #4f46e5);
                                            font-weight: 600;
                                        }

                                        /* Selected + has-lecture: let the existing "selected" pill win, just keep text white */
                                        .mobile-cal-day.selected.has-lecture .mobile-cal-day-num {
                                            background: transparent;
                                            color: #fff;
                                        }

                                        .mobile-cal-selected-strip {
                                            display: flex;
                                            align-items: center;
                                            gap: 10px;
                                            padding: 10px 14px;
                                            margin-top: 12px;
                                        }

                                        .mobile-cal-selected-strip .bar {
                                            width: 4px;
                                            height: 28px;
                                            border-radius: 4px;
                                        }

                                        .mobile-cal-selected-strip .txt {
                                            font-weight: 600;
                                            font-size: 14px;
                                            flex-grow: 1;
                                        }

                                        .mobile-cal-selected-strip .count {
                                            font-size: 12px;
                                            opacity: 0.7;
                                        }

                                        /* Left-aligned mobile lecture card (replaces the desktop lecture-card-header,
   which centers its content when squeezed into a narrow mobile column) */
                                        .mobile-lecture-card {
                                            display: flex;
                                            flex-direction: column;
                                            align-items: stretch;
                                            gap: 12px;
                                            text-align: left;
                                            padding: 14px;
                                            cursor: pointer;
                                            background: var(--custom-white, #ffffff);
                                            border: 1px solid rgba(15, 23, 42, 0.08);
                                            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
                                            border-left: 4px solid var(--primary-color, #4f46e5);
                                            transition: transform 0.15s ease, box-shadow 0.15s ease;
                                        }

                                        .mobile-lecture-card:active {
                                            transform: translateY(1px);
                                            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
                                        }

                                        .mobile-lecture-thumb {
                                            flex-shrink: 0;
                                            width: 100%;
                                            height: auto;
                                            overflow: hidden;
                                            background: #f1f5f9;
                                        }

                                        .mobile-lecture-thumb img {
                                            width: 100%;
                                            height: 100%;
                                            object-fit: cover;
                                        }

                                        .mobile-lecture-info {
                                            display: flex;
                                            flex-direction: column;
                                            align-items: flex-start;
                                            text-align: left;
                                            gap: 5px;
                                            min-width: 0;
                                            flex: 1;
                                        }

                                        .mobile-lecture-title {
                                            font-size: 15px;
                                            font-weight: 700;
                                            color: var(--dark-color, #0f172a);
                                            line-height: 1.3;
                                        }

                                        .mobile-lecture-meta,
                                        .mobile-lecture-time {
                                            font-size: 12.5px;
                                            color: #64748b;
                                            line-height: 1.3;
                                        }

                                        .mobile-lecture-row {
                                            display: flex;
                                            align-items: center;
                                            flex-wrap: wrap;
                                            gap: 6px;
                                            font-size: 13px;
                                            margin-top: 2px;
                                        }

                                        .mobile-lecture-label {
                                            color: #94a3b8;
                                        }

                                        .mobile-lecture-value {
                                            color: #334155;
                                            font-weight: 600;
                                        }

                                        .mobile-lecture-status {
                                            display: inline-flex;
                                            align-items: center;
                                            gap: 6px;
                                            font-size: 12px;
                                            font-weight: 700;
                                            padding: 4px 10px;
                                            margin-top: 4px;
                                            background: var(--light-blue, #eff6ff);
                                            color: var(--dark-blue, #1d4ed8);
                                        }

                                        .mobile-lecture-status.ongoing {
                                            background: var(--light-yellow, #fdfaec);
                                            color: var(--dark-yellow, #dd9f18);
                                        }

                                        .mobile-lecture-status.completed {
                                            background: var(--light-green, #ecfdf5);
                                            color: var(--dark-green, #059669);
                                        }

                                        .mobile-lecture-status.cancelled {
                                            background: var(--light-red, #fef2f2);
                                            color: var(--dark-red, #dc2626);
                                        }

                                        .mobile-lecture-status.study {
                                            background: var(--light-pink, #fdf2f8);
                                            color: var(--dark-pink, #db2777);
                                        }

                                        .mobile-lecture-status.postponed {
                                            background: var(--light-purple, #f5f3ff);
                                            color: var(--dark-purple, #7e22ce);
                                        }

                                        .mobile-lecture-card.ongoing {
                                            border-left-color: var(--dark-yellow, #dd9f18);
                                        }

                                        .mobile-lecture-card.completed {
                                            border-left-color: var(--dark-green, #059669);
                                        }

                                        .mobile-lecture-card.cancelled {
                                            border-left-color: var(--dark-red, #dc2626);
                                        }

                                        .mobile-lecture-card.upcoming {
                                            border-left-color: var(--dark-blue, #1d4ed8);
                                        }

                                        .mobile-lecture-card.study {
                                            border-left-color: var(--dark-pink, #db2777);
                                        }

                                        .mobile-lecture-card.postponed {
                                            border-left-color: var(--dark-purple, #7e22ce);
                                        }

                                        /* ============ RESPONSIVE FIX: Lecture Details Modal (scroll) ============ */
                                        @media (max-width: 768px) {
                                            #lectureDetailsModal .modal-dialog {
                                                margin: 0;
                                                max-width: 100%;
                                                width: 100%;
                                                height: auto;
                                            }

                                            #lectureDetailsModal .modal-content {
                                                height: auto;
                                                border-radius: 0;
                                                display: flex;
                                                flex-direction: column;
                                                overflow: hidden;
                                            }

                                            #lectureDetailsModal .modal-header,
                                            #lectureDetailsModal .modal-footer {
                                                flex-shrink: 0;
                                            }

                                            #lectureDetailsModal .modal-body {
                                                flex: 1 1 auto;
                                                min-height: 0;
                                                overflow-y: auto;
                                                -webkit-overflow-scrolling: touch;
                                                padding: 16px !important;
                                            }

                                            #lectureDetailsModal .lecture-body {
                                                padding: 0 !important;
                                            }

                                            /* Kill the centered look: info-row goes column, but stays left-aligned */
                                            #lectureDetailsModal .info-row {
                                                flex-direction: column;
                                                align-items: flex-start !important;
                                                gap: 4px;
                                                padding: 10px 0;
                                            }

                                            #lectureDetailsModal .info-label,
                                            #lectureDetailsModal .info-value {
                                                text-align: left !important;
                                                min-width: unset;
                                            }

                                            #lectureDetailsModal .info-value {
                                                display: flex;
                                                align-items: center;
                                                gap: 8px;
                                            }

                                            #lectureDetailsModal .status-badge {
                                                margin-top: 2px;
                                            }

                                            #lectureDetailsModal .lecture-main-image {
                                                display: block;
                                                margin: 0 auto;
                                                width: 130px;
                                            }

                                            /* Assigned Intakes section */
                                            #lectureDetailsModal .batch-section-header {
                                                flex-direction: column;
                                                align-items: flex-start;
                                                gap: 4px;
                                            }

                                            #lectureDetailsModal .batch-item {
                                                flex-direction: column;
                                                align-items: stretch;
                                                text-align: left;
                                                gap: 12px;
                                            }

                                            #lectureDetailsModal .batch-left {
                                                flex-direction: column;
                                                align-items: flex-start;
                                                text-align: left;
                                                width: 100%;
                                            }

                                            #lectureDetailsModal .batch-content {
                                                width: 100%;
                                            }

                                            #lectureDetailsModal .batch-cover-wrap {
                                                height: 90px;
                                                width: 100% !important;
                                            }

                                            #lectureDetailsModal .batch-cover {
                                                width: 100%;
                                                height: 100%;
                                                object-fit: cover;
                                            }

                                            #lectureDetailsModal .batch-top-line {
                                                justify-content: flex-start;
                                            }

                                            #lectureDetailsModal .batch-right {
                                                width: 100%;
                                            }

                                            #lectureDetailsModal .batch-right .btn-group,
                                            #lectureDetailsModal .btn-action {
                                                width: 100%;
                                            }
                                        }
                                    </style>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 gy-4 gx-3">
                                            <div class="container p-0 m-0">

                                                <!-- ============ DESKTOP / TABLET CALENDAR (>= md) ============ -->
                                                <div class="d-none d-md-block">
                                                    <div class="col-12 d-flex flex-column flex-lg-row justify-content-center mb-4 mt-2">
                                                        <div class="col-2 col-lg-1 d-none d-lg-block">
                                                            <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 col-lg-10 d-flex justify-content-center align-items-center gap-2">
                                                            <h4 class="text-center" data-i18n="lecture-schedule">Lecture Schedule</h4>
                                                            <h4 class="text-center">-</h4>
                                                            <h4 class="text-center" id="month"></h4>
                                                            <h4 class="text-center" id="year"></h4>
                                                        </div>
                                                        <div class="col-12 col-lg-1 mt-3 d-block d-lg-none">
                                                            <div class="row">
                                                                <div class="col-6 d-flex justify-content-start">
                                                                    <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                    </button>
                                                                </div>
                                                                <div class="col-6 d-flex justify-content-end">
                                                                    <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
                                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-lg-1 text-end d-none d-lg-block">
                                                            <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div id="calendar" class="calendar"></div>
                                                </div>

                                                <!-- ============ MOBILE CALENDAR (< md) ============ -->
                                                <div class="d-block d-md-none" id="mobileScheduleWrapper">
                                                    <div class="row m-0 g-0 component-theme mb-3">
                                                        <div class="mobile-cal-nav">
                                                            <div class="nav-arrow" id="mobileCalPrev">
                                                                <iconify-icon icon="carbon:arrow-left" class="dark-theme"></iconify-icon>
                                                            </div>
                                                            <div class="mobile-cal-title dark-theme" id="mobileCalTitle"></div>
                                                            <div class="nav-arrow" id="mobileCalNext">
                                                                <iconify-icon icon="carbon:arrow-right" class="dark-theme"></iconify-icon>
                                                            </div>
                                                        </div>

                                                        <div class="mobile-cal-weekdays dark-theme">
                                                            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                                                        </div>

                                                        <div class="mobile-cal-grid dark-theme" id="mobileCalGrid">
                                                            <!-- days injected by eventCalender.js -->
                                                        </div>
                                                    </div>

                                                    <div class="mobile-cal-selected-strip dark-theme">
                                                        <div class="bar bg-dark-theme"></div>
                                                        <div class="txt" id="mobileCalSelectedDate"></div>
                                                        <div class="count" id="mobileCalSelectedCount"></div>
                                                    </div>

                                                    <div class="row g-3 mt-1" id="mobileCalLectureList">
                                                        <!-- lecture cards injected by eventCalender.js -->
                                                    </div>
                                                </div>

                                            </div>

                                            <!-- Event Modal -->
                                            <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="eventModalLabel">Events on <span id="selectedDate"></span></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body z-1" id="eventDetails"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="lectureDetailsModal" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-start-0 modal-lg">
                                                    <div class="modal-content lecture-modal-content">
                                                        <div class="modal-header lecture-modal-header">
                                                            <h5 class="modal-title" id="lectureDetailsTitle">Lecture Details</h5>
                                                            <button type="button" class="btn-close text-dark-color" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body" id="lectureDetailsBody"></div>
                                                        <div class="modal-footer" id="lectureDetailsFooter">

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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

            <div class="modal fade" id="removeLectureConfirmModal" tabindex="-1"
                aria-labelledby="removeLectureConfirmModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">

                        <!-- Close button -->
                        <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                            <button type="button"
                                class="btn-close text-end"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>

                        <div class="modal-body text-center p-4">

                            <!-- Warning Icon -->
                            <div class="mb-3">
                                <iconify-icon
                                    icon="bx:error-circle"
                                    width="48"
                                    height="48"
                                    class="text-danger">
                                </iconify-icon>
                            </div>

                            <!-- Title -->
                            <h5 class="fw-semibold mb-2" id="removeLectureConfirmModalTitle">
                                Confirm Scheduled Lecture Deletion
                            </h5>

                            <!-- Main message -->
                            <p class="text-muted mb-3">
                                You are about to delete this <strong>Scheduled Lecture</strong>.
                            </p>

                            <!-- Detailed impact explanation -->
                            <div class="alert alert-warning text-start small mb-4">
                                <div class="d-flex align-items-start gap-2">
                                    <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                    <div>
                                        <strong>This action will:</strong>
                                        <ul class="mb-0 ps-3">
                                            <li>Permanently remove the scheduled lecture</li>
                                            <li>Delete <strong>all associated attachments,attendance and materials</strong></li>
                                            <li>This action <strong>cannot be undone</strong></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="d-flex justify-content-center gap-3">
                                <button type="button"
                                    class="btn btn-outline-secondary px-4 br-0"
                                    data-bs-dismiss="modal">
                                    Cancel
                                </button>

                                <button type="button"
                                    id="confirmRemoveLecture"
                                    class="btn btn-danger px-4 br-0">
                                    <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                    Delete Lecture
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="updateLectureStatusModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="updateLectureStatusLabel">Update Lecture Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="uls-lecture-id">

                            <div class="mb-3">
                                <label class="text-14 sub-text mb-1">Status</label>
                                <select class="form-select custom-select br-0 w-100" id="uls-status-select"></select>
                            </div>

                            <!-- Cancelled -->
                            <div class="mb-3 d-none" id="uls-cancel-reason-wrap">
                                <label class="text-14 sub-text mb-1">Cancellation Reason</label>
                                <textarea class="form-control custom-textarea br-0 w-100" id="uls-cancel-reason" rows="3"
                                    placeholder="Enter reason for cancellation"></textarea>
                            </div>

                            <!-- Postponed -->
                            <div class="mb-3 d-none" id="uls-postpone-wrap">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="text-14 sub-text mb-1">Next Date</label>
                                        <input type="date" class="form-control custom-input br-0 w-100" id="uls-postpone-date">
                                    </div>
                                    <div class="col-3">
                                        <label class="text-14 sub-text mb-1">Start Time</label>
                                        <input type="time" class="form-control custom-input br-0 w-100" id="uls-postpone-start">
                                    </div>
                                    <div class="col-3">
                                        <label class="text-14 sub-text mb-1">End Time</label>
                                        <input type="time" class="form-control custom-input br-0 w-100" id="uls-postpone-end">
                                    </div>
                                </div>
                            </div>

                            <!-- Completed -->
                            <div class="mb-3 d-none" id="uls-complete-wrap">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="text-14 sub-text mb-1">Start Time</label>
                                        <input type="time" class="form-control custom-input br-0 w-100" id="uls-complete-start">
                                    </div>
                                    <div class="col-6">
                                        <label class="text-14 sub-text mb-1">End Time</label>
                                        <input type="time" class="form-control custom-input br-0 w-100" id="uls-complete-end">
                                    </div>
                                </div>
                            </div>

                            <div class="text-dark-red text-14 d-none" id="uls-error"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="custom-btn br-0 px-4" id="uls-submit-btn" onclick="submitLectureStatusUpdate()">
                                Update Status
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lightweight confirm modal — skip this if you already have the
     cancel-schedule-payment confirmation modal, and wire confirmAction()
     to call that one instead -->

            <div class="modal fade" id="ulsConfirmModal" tabindex="-1"
                aria-labelledby="ulsConfirmModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">

                        <div class="d-flex justify-content-end pe-3 pt-3">
                            <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>

                        <div class="modal-body text-center p-4">

                            <div class="mb-3">
                                <iconify-icon
                                    icon="mdi:alert-circle-outline"
                                    width="48"
                                    height="48"
                                    class="text-warning">
                                </iconify-icon>
                            </div>

                            <h5 class="fw-semibold mb-2" id="scheduleConfirmModalTitle">
                                Lecture Status Update Confirmation
                            </h5>

                            <p class="text-muted mb-3" id="ulsConfirmMessage">
                                A scheduling conflict was found.
                            </p>


                            <div class="d-flex justify-content-center gap-3">
                                <button type="button"
                                    class="btn btn-outline-secondary px-4 br-0"
                                    data-bs-dismiss="modal">
                                    Cancel
                                </button>

                                <button type="button"
                                    id="ulsConfirmProceedBtn"
                                    class="btn btn-danger px-4 br-0">
                                    <iconify-icon icon="mdi:check-circle-outline" class="me-1"></iconify-icon>
                                    Continue Anyway
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>


            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/eventCalender.js?v=<?= Project::getVersion() ?>"></script>
            <!-- Lecture audience: "Change audience" modal used from the lecture details modal -->
            <script src="./assets/js/lectureAudience.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}
