<?php
/**
 * Intake student group modals, used by intake-groups.php (the group list
 * itself is on that page). Expects: $id (intake id), $sessionUserRole.
 * Behaviour: assets/js/intakeGroups.js   Backend: api/intakeGroups.php
 *
 * Bootstrap 5 does not stack modals, so intakeGroups.js hides the current
 * modal before opening the next one (e.g. Students → Move) and re-opens it
 * when the next closes.
 */
require_once __DIR__ . '/../classes/IntakeGroups.php';
$igCanManage = IntakeGroups::canManage($sessionUserRole, $id);
?>
<style>
    .ig-capacity-bar { height: 6px; background: var(--light-theme, #eef2ff); }
    .ig-capacity-bar > div { height: 100%; background: var(--dark-theme, #4f46e5); }
    .ig-capacity-bar.full > div { background: var(--dark-red, #dc2626); }
    .ig-student-list { max-height: 420px; }
    .ig-student-list thead th { position: sticky; top: 0; z-index: 1; }
    .ig-history-list { max-height: 480px; }
    .ig-action-badge { white-space: nowrap; }
</style>

<?php if ($igCanManage) { ?>
    <!-- ========== 2. Add / Edit Group ========== -->
    <div class="modal fade" id="igGroupFormModal" tabindex="-1" aria-labelledby="igGroupFormTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-2">
                <div class="modal-header">
                    <h5 class="modal-title main-text" id="igGroupFormTitle">Add Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ig-form-group-id" value="0">
                    <div class="d-flex flex-column mb-3">
                        <label class="text-14 main-text required" for="ig-form-name">Group Name</label>
                        <input type="text" class="form-control custom-input mt-2" id="ig-form-name" maxlength="100" placeholder="e.g. Group A">
                        <span class="text-12 text-dark-red" id="ig-form-name-feedback"></span>
                    </div>
                    <div class="d-flex flex-column">
                        <label class="text-14 main-text required" for="ig-form-max">Maximum Students</label>
                        <input type="number" class="form-control custom-input mt-2" id="ig-form-max" min="1" step="1" placeholder="e.g. 30">
                        <span class="text-12 sub-text mt-1 d-none" id="ig-form-max-hint"></span>
                        <span class="text-12 text-dark-red" id="ig-form-max-feedback"></span>
                    </div>
                    <span class="text-12 text-dark-red d-block mt-2" id="ig-form-error"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="custom-btn px-4" id="ig-form-save" onclick="igSaveGroup()">Save Group</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== 3. Add Students to Group ========== -->
    <div class="modal fade" id="igAddStudentsModal" tabindex="-1" aria-labelledby="igAddTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content p-2">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title main-text" id="igAddTitle">Add Students</h5>
                        <span class="text-12 sub-text" id="ig-add-sub"></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-12 col-md-8">
                            <div class="d-flex align-items-center p-1 bl-solid">
                                <iconify-icon icon="teenyicons:search-outline" width="18" height="18" class="text-dark-color px-3"></iconify-icon>
                                <input type="text" class="sub-text form-control border-0 shadow-none" id="ig-add-search" placeholder="Search name, registration number or email..." oninput="igRenderEligible()">
                            </div>
                        </div>
                        <div class="col-12 col-md-4 text-md-end">
                            <span class="text-14 main-text fw-medium" id="ig-add-count">0 selected</span>
                        </div>
                    </div>
                    <div class="responsive-table ig-student-list">
                        <table class="table bl-solid m-0">
                            <thead>
                                <tr>
                                    <th class="px-3 py-3 bg-table-header" style="width:48px">
                                        <input type="checkbox" class="form-check-input text-20 m-0" id="ig-add-check-all" title="Select all shown" onchange="igToggleAllEligible(this.checked)">
                                    </th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Student</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Registration No</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Email</th>
                                </tr>
                            </thead>
                            <tbody id="ig-add-body"></tbody>
                        </table>
                    </div>
                    <span class="text-12 text-dark-red d-block mt-2" id="ig-add-error"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="custom-btn px-4" id="ig-add-save" onclick="igAddSelected()" disabled>Add Selected Students</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== 5. Move Student ========== -->
    <div class="modal fade" id="igMoveModal" tabindex="-1" aria-labelledby="igMoveTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-2">
                <div class="modal-header">
                    <h5 class="modal-title main-text" id="igMoveTitle">Move to Another Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light-theme mb-3">
                        <div class="text-14 main-text fw-medium" id="ig-move-student"></div>
                        <div class="text-12 sub-text mt-1">Current group: <span class="main-text fw-medium" id="ig-move-current"></span></div>
                    </div>
                    <div id="ig-move-target-wrap">
                        <label class="text-14 main-text required" for="ig-move-target">Destination Group</label>
                        <select class="form-select custom-select mt-2" id="ig-move-target"></select>
                    </div>
                    <span class="text-12 text-dark-red d-block mt-2" id="ig-move-error"></span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="custom-btn px-4" id="ig-move-save" onclick="igMoveStudent()">Move Student</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== Confirm (remove from group) ========== -->
    <div class="modal fade" id="igConfirmModal" tabindex="-1" aria-labelledby="igConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="d-flex justify-content-end pe-3 pt-3">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="mb-3"><iconify-icon icon="mdi:alert-circle-outline" width="48" height="48" class="text-warning"></iconify-icon></div>
                    <h5 class="fw-semibold mb-2" id="igConfirmTitle">Remove from group?</h5>
                    <p class="text-muted mb-3" id="ig-confirm-msg"></p>
                    <span class="text-12 text-dark-red d-block mb-3" id="ig-confirm-error"></span>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-outline-secondary px-4 br-0" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger px-4 br-0" id="ig-confirm-btn">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- ========== 4. Group Students ========== -->
<div class="modal fade" id="igMembersModal" tabindex="-1" aria-labelledby="igMembersTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content p-2">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title main-text" id="igMembersTitle">Group Students</h5>
                    <span class="text-12 sub-text" id="ig-members-sub"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center p-1 bl-solid mb-3">
                    <iconify-icon icon="teenyicons:search-outline" width="18" height="18" class="text-dark-color px-3"></iconify-icon>
                    <input type="text" class="sub-text form-control border-0 shadow-none" id="ig-members-search" placeholder="Search name, registration number or email..." oninput="igRenderMembers()">
                </div>
                <div class="table-responsive ig-student-list">
                    <table class="table bl-solid m-0">
                        <thead>
                            <tr>
                                <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Student</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Registration No</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody id="ig-members-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== 6. Group History ========== -->
<div class="modal fade" id="igHistoryModal" tabindex="-1" aria-labelledby="igHistoryTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content p-2">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title main-text" id="igHistoryTitle">Group History</h5>
                    <span class="text-12 sub-text" id="ig-history-sub"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive ig-history-list">
                    <table class="table bl-solid m-0">
                        <thead>
                            <tr>
                                <th class="px-3 py-3 bg-table-header text-14 main-text fw-medium">Date &amp; Time</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Student</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">From</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">To</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">By</th>
                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Details</th>
                            </tr>
                        </thead>
                        <tbody id="ig-history-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
