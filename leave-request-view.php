<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['leave-id']) && !empty($_GET['leave-id']))) {
            header("Location: ./leave-requests");
        } else {

            $status = isset($_GET['status']) ? $_GET['status'] : 0;

            if (!($status == 0 && (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 25)))) {
                header("Location: ./dashboard");
                exit();
            }

            $query = "
        SELECT 
            lr.leave_request_id,
            lr.from_date,
            lr.to_date,
            lr.total_days,
            lr.reason,
            lr.requested_at,
        
            lr.attachment,
            lr.review_note,
            lr.reviewed_at,

                    
            lrs.leave_request_status,
            lrs.leave_request_status_id,
        
            lt.leave_type,
        
            ldt.leave_day_type,
        
            u.user_id,
            u.user_fname,
            u.user_lname,
            u.profile_img,

            ru.user_fname AS reviewed_by_fname,
            ru.user_lname AS reviewed_by_lname,
                  
            t.title
        
        FROM leave_requests AS lr
        
        INNER JOIN leave_types AS lt
            ON lt.leave_type_id = lr.leave_types_leave_type_id
        
        INNER JOIN leave_request_status AS lrs
            ON lrs.leave_request_status_id = lr.leave_request_status_leave_request_status_id
        
        INNER JOIN leave_day_type AS ldt
            ON ldt.leave_day_type_id = lr.leave_day_type_leave_day_type_id
        
        INNER JOIN users AS u
            ON u.user_id = lr.users_user_id

        LEFT JOIN users AS ru
            ON lr.reviewed_by = ru.user_id
        
        INNER JOIN title AS t
            ON t.title_id = u.title_title_id
        
        WHERE lr.leave_request_id='" . $_GET['leave-id'] . "'
       
        ";

            if ($status != 0) {
                $query .= " AND u.user_id = '" . $sessionUserObject['user_id'] . "'";
            }

            $leaveRequest = Database::search($query);
            if ($leaveRequest->num_rows == 0) {
                header("Location: ./leave-requests");
            } else {
                $leaveRequest = $leaveRequest->fetch_assoc();

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Leave Request View - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">

                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body>
                    <div class="notifications"></div>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
                                $status == 0 ? load_side_menu::load("leave-requests") : load_side_menu::load("my-leave-requests");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        $status == 0 ? selectItem::setSelected("leave-requests") : selectItem::setSelected("my-leave-requests");
                                        ?>
                                    </div>

                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-table.php"; ?>
                                    </div>

                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg" data-i18n="">Leave Request View</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <?php
                                                                    if ($status == 0) {
                                                                    ?>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./leave-requests">Leave Requests</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Leave Request View</a></li>
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./my-leave-requests">My Leave Requests</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Leave Request View</a></li>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                        <?php
                                                        if ($leaveRequest['leave_request_status_id'] == 1) {
                                                        ?>
                                                            <button class="custom-btn w-100" type="button" data-bs-target="#updatePaymentStatus" data-bs-toggle="modal">
                                                                Update Status
                                                            </button>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="row p-4 m-0 g-0 component-theme w-100">
                                                    <div class="col-12">
                                                        <div class="payment-timeline">
                                                            <div class="timeline-progress">
                                                                <?php
                                                                $query = "SELECT * FROM `leave_request_status`";
                                                                $progress = 0;
                                                                $progressColor = "";
                                                                switch ($leaveRequest['leave_request_status_id']) {
                                                                    case 1:
                                                                        $progressColor = "completed";
                                                                        $progress = 100 / 2 * 0;
                                                                        $query .= " WHERE `leave_request_status_id` IN (1,2)";
                                                                        break;
                                                                    case 2:
                                                                        $progressColor = "accepted";
                                                                        $progress = 100 / 2 * 2;
                                                                        $query .= " WHERE `leave_request_status_id` IN (1,2)";
                                                                        break;
                                                                    case 3:
                                                                        $progressColor = "reject";
                                                                        $progress = 100 / 2 * 2;
                                                                        $query .= " WHERE `leave_request_status_id` IN (1,32)";
                                                                        break;
                                                                    case 4:
                                                                        $progressColor = "reject";
                                                                        $progress = 100 / 2 * 2;
                                                                        $query .= " WHERE `leave_request_status_id` IN (1,4)";
                                                                        break;
                                                                    case 5:
                                                                        $progressColor = "accepted";
                                                                        $progress = 100 / 2 * 2;
                                                                        $query .= " WHERE `leave_request_status_id` IN (1,5)";
                                                                        break;
                                                                }

                                                                ?>
                                                                <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                            </div>
                                                            <div class="timeline-items">
                                                                <?php

                                                                $icons = ["majesticons:clock", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];

                                                                $paymentStatus = Database::search($query);
                                                                while ($status = $paymentStatus->fetch_assoc()) {
                                                                    $timelineItemColor = "";
                                                                    $bubbleAction = "";

                                                                    if ($leaveRequest['leave_request_status_id'] >= $status['leave_request_status_id']) {
                                                                        if ($leaveRequest['leave_request_status_id'] == 3 || $leaveRequest['leave_request_status_id'] == 4) {
                                                                            $timelineItemColor = "reject";
                                                                        } else if ($leaveRequest['leave_request_status_id'] == 2 || $leaveRequest['leave_request_status_id'] == 5) {
                                                                            $timelineItemColor = "accepted";
                                                                        } else {
                                                                            if ($leaveRequest['leave_request_status_id']  ==  $status['leave_request_status_id']) {
                                                                                $bubbleAction = "pulse";
                                                                            } else {
                                                                                $timelineItemColor = "completed";
                                                                            }
                                                                        }
                                                                    }

                                                                ?>
                                                                    <div class="timeline-item <?= $timelineItemColor ?>">
                                                                        <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                            <iconify-icon icon="<?= $icons[$status['leave_request_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                        </div>
                                                                        <div class="timeline-label">
                                                                            <span><?= $status['leave_request_status'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                <?php

                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="payment-card-container mt-4">
                                                        <div class="payment-card">
                                                            <!-- Background elements -->
                                                            <div class="card-background"></div>

                                                            <!-- Card content -->
                                                            <div class="card-content">
                                                                <div class="payment-header reference-section">
                                                                    <!-- Reference on left -->
                                                                    <div class="reference-section">
                                                                        <span class="reference-label">REQUESTED BY</span>
                                                                        <h2 class="reference-number"><?= $leaveRequest['user_fname'] . " " . $leaveRequest['user_lname']; ?></h2>
                                                                        <hr>
                                                                    </div>
                                                                </div>

                                                                <span class="reference-label my-3">REQUEST DETAILS</span>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <!-- Date -->
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="ic:twotone-merge-type" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Leave Type</span>
                                                                            <span class="detail-value"><?= $leaveRequest['leave_type'] ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Type -->
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="mdi:time-of-day" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Leave Day Type</span>
                                                                            <span class="detail-value"><?= $leaveRequest['leave_day_type'] ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="solar:calendar-date-broken" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">From Date</span>
                                                                            <span class="detail-value"><?= $leaveRequest['from_date'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="solar:calendar-date-broken" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">To Date</span>
                                                                            <span class="detail-value"><?= $leaveRequest['to_date'] ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="tabler:sum" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Total Days</span>
                                                                            <span class="detail-value"><?= $leaveRequest['total_days'] ?></span>

                                                                        </div>
                                                                    </div>
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="akar-icons:file" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Attachment</span>
                                                                            <?php
                                                                            if ($leaveRequest['attachment'] != null) {
                                                                            ?>
                                                                                <span class="detail-value cursor-pointer" onclick="window.open('./assets/uploads/leave-request-attachments/<?= $leaveRequest['attachment'] ?>', '_blank')">
                                                                                    <?= $leaveRequest['attachment'] ?>
                                                                                </span>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <span class="detail-value">No Attachment</span>
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </div>
                                                                    </div>
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="solar:calendar-date-broken" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Requested At</span>
                                                                            <span class="detail-value"><?= date("Y-m-d h:i A", strtotime($leaveRequest['requested_at'])) ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="pajamas:status" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Requested Status</span>
                                                                            <span class="detail-value"><?= $leaveRequest['leave_request_status'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <!-- Date -->
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="simple-icons:reasonstudios" width="18" height="18"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Reason</span>
                                                                            <span class="detail-value"><?= $leaveRequest['reason'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <?php
                                                                if ($leaveRequest['leave_request_status_id'] != 1) {
                                                                ?>
                                                                    <span class="reference-label my-3">REVIEW DETAILS</span>
                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                        <!-- Date -->
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <iconify-icon icon="solar:user-broken" width="18" height="18"></iconify-icon>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Reviewed By</span>
                                                                                <span class="detail-value"><?= $leaveRequest['reviewed_by_fname'] . " " . $leaveRequest['reviewed_by_lname'] ?></span>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Type -->
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <iconify-icon icon="solar:calendar-date-broken" width="18" height="18"></iconify-icon>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Reviewed At</span>
                                                                                <span class="detail-value"><?= $leaveRequest['reviewed_at'] ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                        <!-- Date -->
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <iconify-icon icon="mingcute:tag-fill" width="18" height="18"></iconify-icon>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Note</span>
                                                                                <span class="detail-value"><?= $leaveRequest['review_note'] ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>


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

                    <?php
                    if ($leaveRequest['leave_request_status_id'] == 1) {
                    ?>
                        <div class="modal fade" id="updatePaymentStatus" tabindex="-1" role="dialog" aria-labelledby="updatePaymentStatusTitle" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLongTitle">Update Payment Status</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="col-12 p-0 g-0">
                                            <div class="row gx-4 gy-3">
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="">Payment Status</label>
                                                    <select class="form-select custom-select mt-2" id="status-selector">
                                                        <?php
                                                        $status = Database::search("SELECT * FROM `leave_request_status` WHERE `leave_request_status_id` != 1");
                                                        while ($data = $status->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $data["leave_request_status_id"]; ?>"><?= $data["leave_request_status"]; ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="">Note</label>
                                                    <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                                    <span id="note-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="col-12 col-md-4 custom-btn" onclick="updateLeaveRequestStatus(this,<?= $leaveRequest['leave_request_id'] ?>);">Update</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    }
                    ?>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php
            }
        }
    }
}
?>