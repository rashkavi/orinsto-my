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

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 25))) {
            header("Location: ./dashboard");
            exit();
        }

        $user_id = isset($_GET['user-id']) ? $_GET['user-id'] : 0;

        $users = Database::search("
    SELECT 
        u.user_id,
        u.user_fname,
        u.user_lname,
        u.profile_img,
        t.title
        FROM users AS u
        INNER JOIN title AS t 
            ON t.title_id = u.title_title_id
        WHERE u.status_status_id = 1
        AND u.user_roles_user_role_id != 1
");


?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Add Leave - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                        load_side_menu::load("leave-requests");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("leave-requests");
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
                                                    <span class="main-topic lh-lg" data-i18n="">Add Leave</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./leave-requests">Leave Requests</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Add Leave</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-2 mt-3 mt-lg-0">
                                                <div class="row align-items-center justify-content-end gx-3 h-100">
                                                    <div class="col-12 mt-3 mt-lg-0 m-md-0 text-end">
                                                        <button class="custom-btn w-100" type="button"
                                                            onclick="addLeave(this);">
                                                            Save Leave </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row gx-4 gy-3">
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">User</label>
                                                <div class="col-12 position-relative mt-2">
                                                    <input
                                                        type="text"
                                                        id="userSearch"
                                                        class="form-control custom-input"
                                                        placeholder="Search user..."
                                                        onkeyup="searchLeaveUser(this.value)"
                                                        autocomplete="off">

                                                    <!-- Hidden input for storing selected user_id -->
                                                    <input type="hidden" id="selectedUserId" value="">
                                                    <input type="hidden" id="selectedUserName" value="">

                                                    <div id="userSuggestions"
                                                        class="list-group position-absolute w-100"
                                                        style="z-index: 999;"></div>
                                                </div>
                                                <span id="user-feedback" class="text-dark-red text-12"></span>

                                            </div>

                                            <!-- Leave Type -->
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">Leave Type</label>
                                                <select class="form-select custom-select mt-2" id="leave-type">
                                                    <option value="0" selected disabled>- Select -</option>
                                                    <?php
                                                    $types = Database::search("SELECT * FROM leave_types WHERE status_status_id=1");
                                                    while ($t = $types->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?= $t['leave_type_id'] ?>">
                                                            <?= $t['leave_type'] ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <span id="leave-type-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <!-- Leave Day Type -->
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">Leave Day Type</label>
                                                <select class="form-select custom-select mt-2" id="leave-day-type">
                                                    <option value="0" selected disabled>- Select -</option>
                                                    <?php
                                                    $dayTypes = Database::search("SELECT * FROM leave_day_type");
                                                    while ($d = $dayTypes->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?= $d['leave_day_type_id'] ?>">
                                                            <?= $d['leave_day_type'] ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <span id="day-type-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <!-- From Date -->
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">From Date</label>
                                                <input type="date" class="form-control custom-input mt-2" id="from-date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                                                <span id="from-date-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <!-- To Date -->
                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                <label class="text-14 main-text required">To Date</label>
                                                <input type="date" class="form-control custom-input mt-2" id="to-date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                                                <span id="to-date-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <!-- Reason -->
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text required">Reason for Leave</label>
                                                <textarea class="form-control custom-input mt-2" id="leave-reason" rows="4"></textarea>
                                                <span id="reason-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-16 main-text" for="">Lecture Attachment</label>
                                                <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                    <span>Drag & Drop your files here</span>
                                                    <span>OR</span>
                                                    <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                    <input type="file" class="d-none" id="file-selector-input" accept=".pdf,image/*">
                                                    <div class="col-12 px-1 mt-4">
                                                        <div class="row" id="list-section">
                                                        </div>
                                                    </div>
                                                </div>
                                                <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                                <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                            </div>

                                            <!-- Submit Button -->
                                            <div class="col-12 d-flex justify-content-end mt-3">
                                                <div class="col-12 col-md-3">
                                                    <button class="custom-btn px-4 w-100" onclick="addLeave(this);">
                                                        Save Leave
                                                    </button>
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

            <script id="v1r2js">
                const users = [
                    <?php while ($u = $users->fetch_assoc()) { ?> {
                            id: "<?= $u['user_id'] ?>",
                            name: "<?= addslashes($u['title'] . '.' . $u['user_fname'] . ' ' . $u['user_lname']) ?>",
                            fname: "<?= addslashes($u['user_fname']) ?>",
                            lname: "<?= addslashes($u['user_lname']) ?>",
                            title: "<?= addslashes($u['title']) ?>",
                            img: "assets/imgs/users/<?= $u['profile_img'] ?>"
                        },
                    <?php } ?>
                ];

                // Pre-populate user if passed via URL parameter
                const passedUserId = "<?= $user_id ?>";
                
                document.addEventListener('DOMContentLoaded', function() {
                    if (passedUserId && passedUserId !== '0') {
                        // Find user in array
                        const selectedUser = users.find(u => u.id === passedUserId);
                        if (selectedUser) {
                            // Pre-populate the form with selected user
                            selectLeaveUser(selectedUser.id, selectedUser.name);
                        }
                    }
                });

                /**
                 * Search and filter users in the dropdown
                 */
                function searchLeaveUser(value) {
                    const input = document.getElementById('userSearch');
                    const suggestionsDiv = document.getElementById('userSuggestions');
                    const searchTerm = value.toLowerCase().trim();

                    // Clear suggestions if input is empty
                    if (!searchTerm) {
                        suggestionsDiv.innerHTML = '';
                        suggestionsDiv.classList.remove('show');
                        return;
                    }

                    // Filter users based on search term
                    const filtered = users.filter(u => 
                        u.name.toLowerCase().includes(searchTerm) ||
                        u.fname.toLowerCase().includes(searchTerm) ||
                        u.lname.toLowerCase().includes(searchTerm)
                    );

                    // Build suggestions HTML
                    if (filtered.length > 0) {
                        let html = '';
                        filtered.forEach(user => {
                            const initials = (user.fname.charAt(0) + user.lname.charAt(0)).toUpperCase();
                            const hasImg = user.img && user.img !== 'assets/imgs/users/';
                            
                            html += `
                                <button 
                                    type="button" 
                                    class="list-group-item list-group-item-action p-2 mb-1"
                                    style="border: 1px solid var(--border-color); border-radius: 6px; text-align: left;"
                                    onclick="selectLeaveUser('${user.id}', '${user.name}'); return false;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--light-theme); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; flex-shrink: 0;">
                                            ${hasImg ? `<img src="${user.img}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" alt="">` : initials}
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div class="text-13 fw-medium" style="color: var(--main-text-color);">${user.name}</div>
                                            <div class="text-11" style="color: var(--sub-text-color);">ID: ${user.id}</div>
                                        </div>
                                    </div>
                                </button>
                            `;
                        });
                        suggestionsDiv.innerHTML = html;
                        suggestionsDiv.classList.add('show');
                    } else {
                        suggestionsDiv.innerHTML = '<div class="p-3 text-center text-12 sub-text">No users found</div>';
                        suggestionsDiv.classList.add('show');
                    }
                }

                /**
                 * Select a user from dropdown and populate the field
                 */
                function selectLeaveUser(userId, userName) {
                    document.getElementById('userSearch').value = userName;
                    document.getElementById('selectedUserId').value = userId;
                    document.getElementById('selectedUserName').value = userName;
                    document.getElementById('userSuggestions').innerHTML = '';
                    document.getElementById('userSuggestions').classList.remove('show');
                    document.getElementById('user-feedback').innerHTML = '';
                }

                /**
                 * Get selected user ID for form submission
                 */
                function getSelectedUserId() {
                    return document.getElementById('selectedUserId').value;
                }

                /**
                 * Clear user selection
                 */
                function clearUserSelection() {
                    document.getElementById('userSearch').value = '';
                    document.getElementById('selectedUserId').value = '';
                    document.getElementById('selectedUserName').value = '';
                    document.getElementById('userSuggestions').innerHTML = '';
                }

                // Close suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    const suggestions = document.getElementById('userSuggestions');
                    const input = document.getElementById('userSearch');
                    if (e.target !== input && !suggestions.contains(e.target)) {
                        suggestions.innerHTML = '';
                        suggestions.classList.remove('show');
                    }
                });
            </script>
            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php

    }
}
