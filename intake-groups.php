<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/classes/IntakeGroups.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        $id = isset($_GET['intake-id']) && ctype_digit((string)$_GET['intake-id']) ? (int)$_GET['intake-id'] : 0;
        if ($id <= 0) {
            header("Location: ./intakes");
        } else {
            $intakeData = IntakeGroups::row(
                "SELECT `intakes`.`intake_id`, `intakes`.`intake`, `programmes`.`programme_id`, `programmes`.`programme`,
                        `programmes`.`faculties_faculty_id`
                 FROM `intakes`
                 INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                 WHERE `intakes`.`intake_id` = ?",
                'i',
                [$id]
            );
            if (!$intakeData) {
                header("Location: ./intakes");
            } else {
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!IntakeGroups::canView($sessionUserRole, $id)) {
                    header("Location: ./intake-details?intake-id=" . $id);
                } else {
                    $igCanManage = IntakeGroups::canManage($sessionUserRole, $id);
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Student Groups - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeIntakeGroupsPage();">
                        <ul class="notifications"></ul>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("course-overview");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("course-overview");
                                            ?>
                                        </div>

                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-dashboards.php"; ?>
                                        </div>

                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0" id="igPage" data-intake-id="<?= (int)$id ?>" data-can-manage="<?= $igCanManage ? '1' : '0' ?>">

                                                <!-- Page header -->
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Student Groups</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= (int)$intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programe-id=<?= (int)$intakeData['programme_id'] ?>'><?= htmlspecialchars($intakeData["programme"]) ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intake-details?intake-id=<?= (int)$id ?>'><?= htmlspecialchars($intakeData["intake"]) ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Student Groups</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-4 mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
                                                          
                                                            <?php if ($igCanManage) { ?>
                                                                <button type="button" class="custom-btn px-4 text-nowrap" onclick="igOpenGroupForm()">
                                                                    <iconify-icon icon="mdi:plus" width="18" class="me-1"></iconify-icon>Add Group
                                                                </button>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Summary cards (filled by intakeGroups.js) -->
                                                <div class="container-fluid p-0 mb-4">
                                                    <div class="row g-3">
                                                        <?php
                                                        $igCards = [
                                                            ['id' => 'groups',     'title' => 'Groups',              'icon' => 'mdi:account-group-outline',  'period' => 'In this intake'],
                                                            ['id' => 'grouped',    'title' => 'Students in Groups',  'icon' => 'mdi:account-check-outline',  'period' => 'Assigned to a group'],
                                                            ['id' => 'unassigned', 'title' => 'Not in Any Group',    'icon' => 'mdi:account-question-outline', 'period' => 'Excludes discontinued'],
                                                            ['id' => 'seats',      'title' => 'Available Seats',     'icon' => 'mdi:seat-outline',           'period' => 'Across all groups'],
                                                        ];
                                                        foreach ($igCards as $card) { ?>
                                                            <div class="col-12 col-md-6 col-lg-3">
                                                                <div class="stats-mini-card">
                                                                    <div class="stats-mini-top">
                                                                        <div class="stats-mini-left">
                                                                            <div class="stats-mini-icon"><iconify-icon icon="<?= $card['icon'] ?>" width="16" height="16"></iconify-icon></div>
                                                                            <span class="stats-mini-title"><?= $card['title'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="stats-mini-body">
                                                                        <div class="stats-mini-content">
                                                                            <div class="stats-mini-value" id="ig-card-<?= $card['id'] ?>">-</div>
                                                                            <div class="stats-mini-meta"><span class="stats-mini-period"><?= $card['period'] ?></span></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php } ?>
                                                    </div>
                                                </div>

                                                <!-- Group list -->
                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0 mb-3 align-items-center">
                                                        <div class="col">
                                                            <span class="text-14 main-text fw-medium d-block">Groups</span>
                                                            <span class="text-12 sub-text" id="ig-unassigned-text">-</span>
                                                        </div>
                                                        <div class="col-12 col-md-4 mt-2 mt-md-0">
                                                            <div class="d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="18" height="18" class="text-dark-color px-3"></iconify-icon>
                                                                <input type="text" class="sub-text form-control border-0 shadow-none" id="ig-group-search" placeholder="Search groups..." oninput="igRenderGroups()">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div id="ig-groups-body"></div>
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

                        <?php include './includes/components/intake-groups-modals.php'; ?>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/intakeGroups.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>
