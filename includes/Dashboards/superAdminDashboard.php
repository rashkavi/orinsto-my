<?php
class SuperAdminDashboard
{
    public static function loadContent()
    {
        $sessionUserRole = SessionUser::getCurrentUserRole();
        $sessionUserObject = SessionUser::getCurrentUserObject();

        if (SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole != "Lecturer") {

            require_once "./includes/classes/AdminDashboardSummary.php";

            $timezone = new DateTimeZone("Asia/Colombo");
            $now = new DateTime("now", $timezone);
            $todayIso = $now->format('Y-m-d');

            // Default: current month start to today
            $defaultFrom = $now->format('Y-m-01');
            $defaultTo   = $todayIso;

            $startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : $defaultFrom;
            $endDate   = !empty($_GET['end_date'])   ? $_GET['end_date']   : $defaultTo;
            $progFilter = isset($_GET['programme'])   ? (int)$_GET['programme'] : 0;
            $searchFilter = isset($_GET['search'])    ? trim($_GET['search']) : '';

            $hasActiveFilter = ($startDate !== $defaultFrom || $endDate !== $defaultTo || $progFilter > 0 || !empty($searchFilter));

            // Fetch comprehensive data directly in PHP
            $data = AdminDashboardSummary::getComprehensiveDashboardData([
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'programme_id' => $progFilter,
                'search'       => $searchFilter
            ]);

            $studentCards = $data['student_cards'];
            $financeCards = $data['finance_cards'];
            $systemCards  = $data['system_cards'];
            $liveClasses  = $data['live'];
            $actionItems  = $data['actions'];
            $batchesList  = $data['batches'];
            $eventsList   = $data['events'];
            $deadlinesList = $data['deadlines'];
            $lecturesList = $data['lectures'];
            $programmes   = $data['programmes'];

            // Formatted date range label for header
            $fmtStart = (new DateTime($startDate))->format('d M Y');
            $fmtEnd   = (new DateTime($endDate))->format('d M Y');
            $rangeLabel = ($startDate === $endDate && $startDate === $todayIso)
                ? "Today, " . (new DateTime($todayIso))->format('d M Y')
                : $fmtStart . " – " . $fmtEnd;

            // User Info
            $userFname = $sessionUserObject['user_fname'] ?? 'Admin';
            $userLname = $sessionUserObject['user_lname'] ?? '';
            $userName  = trim($userFname . ' ' . $userLname);
            $userInitials = strtoupper(substr($userFname, 0, 1) . substr($userLname, 0, 1));

            // Helper: sparkline mini bar chart
            $renderBars = function ($spark) {
                if (empty($spark)) return '';
                $max = max(max($spark), 1);
                $html = '<div class="stats-mini-chart">';
                $tot = count($spark);
                foreach ($spark as $i => $v) {
                    $h = max(6, (int)round(($v / $max) * 42));
                    $act = ($i === $tot - 1) ? ' active' : '';
                    $html .= '<span class="stats-mini-bar' . $act . '" style="height:' . $h . 'px"></span>';
                }
                $html .= '</div>';
                return $html;
            };

            // Helper: status tone class
            $toneCls = function ($tone) {
                if ($tone === 'g') return 'positive';
                if ($tone === 'r') return 'negative';
                return 'neutral';
            };

            $actionToneCls = function ($tone) {
                if ($tone === 'warn') return 'status-orange';
                if ($tone === 'bad') return 'status-red';
                return 'status-blue';
            };

            $batchStateCls = function ($state) {
                if ($state === 'Running') return 'status-green';
                if ($state === 'New intake') return 'status-blue';
                if ($state === 'Ending soon') return 'status-orange';
                return 'status-gray';
            };

            $lectureStatusCls = function ($status) {
                if ($status === 'Ongoing') return 'status-green';
                if ($status === 'Upcoming') return 'status-blue';
                return 'status-gray';
            };
?>

            <!-- Component styles ensuring single-row filter bar & theme harmony -->
            <style>
                .adx-page {
                    width: 100%;
                    color: var(--main-text-color);
                }

                /* Header Greeting Bar */
                .adx-top-bar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                }

                .adx-greeting {
                    font-size: 20px;
                    font-weight: 700;
                    color: var(--dark-color);
                    margin: 0;
                }

                .adx-ver-badge {
                    font-size: 11.5px;
                    font-weight: 600;
                    background-color: var(--table-header-color);
                    color: var(--sub-text);
                    padding: 4px 10px;
                    border: 1px solid var(--line-color);
                }

                .adx-avatar-box {
                    width: 36px;
                    height: 36px;
                    background: var(--light-theme);
                    color: var(--dark-theme);
                    font-weight: 700;
                    font-size: 13px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                /* ────────────────────────────────────────────────────────────
                   SINGLE-ROW FILTER TOOLBAR (Guaranteed 1 single row on desktop)
                   ──────────────────────────────────────────────────────────── */
                .adx-filter-toolbar {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    width: 100%;
                    margin-bottom: 22px;
                    flex-wrap: nowrap;
                    /* STRICTLY 1 ROW */
                }

                .adx-filter-col-date {
                    flex: 0 0 230px;
                    width: 230px;
                    position: relative;
                }

                .adx-filter-col-search {
                    flex: 1 1 auto;
                    min-width: 200px;
                    max-width: 460px;
                }

                .adx-filter-col-prog {
                    flex: 0 0 200px;
                    width: 200px;
                }

                .adx-filter-col-actions {
                    flex: 0 0 auto;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    white-space: nowrap;
                }

                /* Uniform 42px height controls */
                .adx-date-btn {
                    height: 42px;
                    width: 100%;
                    background: var(--custom-white);
                    border: 1px solid var(--line-color);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0 12px;
                    font-size: 13.5px;
                    font-weight: 500;
                    color: var(--main-text-color);
                    cursor: pointer;
                    user-select: none;
                }

                .adx-date-btn:hover {
                    border-color: var(--dark-theme);
                }

                .adx-search-wrap {
                    position: relative;
                    display: flex;
                    align-items: center;
                    width: 100%;
                }

                .adx-search-wrap .adx-s-icon {
                    position: absolute;
                    left: 12px;
                    pointer-events: none;
                    color: var(--sub-text);
                }

                .adx-search-input {
                    height: 42px !important;
                    width: 100% !important;
                    background: var(--custom-white) !important;
                    border: 1px solid var(--line-color) !important;
                    border-radius: 0 !important;
                    padding-left: 36px !important;
                    padding-right: 30px !important;
                    font-size: 13.5px !important;
                    color: var(--main-text-color) !important;
                    outline: none !important;
                    box-shadow: none !important;
                }

                .adx-search-input:focus {
                    border-color: var(--dark-theme) !important;
                    box-shadow: 0 0 0 2px var(--light-theme) !important;
                }

                .adx-search-clear {
                    position: absolute;
                    right: 8px;
                    color: var(--sub-text);
                    text-decoration: none;
                    padding: 4px;
                    display: flex;
                    align-items: center;
                }

                .adx-search-clear:hover {
                    color: var(--dark-red);
                }

                .adx-prog-select {
                    height: 42px !important;
                    width: 100% !important;
                    background: var(--custom-white) !important;
                    border: 1px solid var(--line-color) !important;
                    border-radius: 0 !important;
                    padding: 0 12px !important;
                    font-size: 13.5px !important;
                    color: var(--main-text-color) !important;
                    outline: none !important;
                    cursor: pointer;
                }

                .adx-prog-select:focus {
                    border-color: var(--dark-theme) !important;
                }

                .adx-btn-filter {
                    height: 42px !important;
                    padding: 0 18px !important;
                    font-size: 13.5px !important;
                    font-weight: 600 !important;
                }

                .adx-btn-reset {
                    color: var(--sub-text);
                    font-size: 13px;
                    font-weight: 500;
                    text-decoration: none;
                }

                .adx-btn-reset:hover {
                    color: var(--dark-red);
                }

                .adx-btn-icon {
                    height: 42px;
                    width: 42px;
                    border: 1px solid var(--line-color);
                    background: var(--custom-white);
                    color: var(--sub-text);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    text-decoration: none;
                }

                .adx-btn-icon:hover {
                    color: var(--dark-theme);
                    border-color: var(--dark-theme);
                }

                /* Date Dropdown Popover */
                .adx-date-popover {
                    display: none;
                    position: absolute;
                    top: 48px;
                    left: 0;
                    z-index: 1050;
                    width: 320px;
                    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.15);
                    border: 1px solid var(--line-color);
                    background: var(--custom-white);
                }

                .adx-date-popover.show {
                    display: block;
                }

                .adx-preset-btn {
                    padding: 5px 8px;
                    font-size: 12px;
                    font-weight: 500;
                    border: 1px solid var(--line-color);
                    background: var(--table-header-color);
                    color: var(--main-text-color);
                    cursor: pointer;
                }

                .adx-preset-btn:hover {
                    background: var(--light-theme);
                    color: var(--dark-theme);
                    border-color: var(--dark-theme);
                }

                /* Pill tags */
                .adx-pill {
                    display: inline-block;
                    min-width: 80px;
                    text-align: center;
                    font-size: 12.5px;
                    padding: 3px 10px;
                    white-space: nowrap;
                    font-weight: 600;
                }

                .status-gray {
                    background: var(--table-header-color);
                    color: var(--sub-text);
                    border: 1px solid var(--line-color);
                }

                .status-purple {
                    background: var(--light-purple);
                    color: var(--dark-purple);
                }

                /* Live Class featured box */
                .lv {
                    display: grid;
                    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
                    gap: 12px;
                }

                @media (max-width: 768px) {
                    .lv {
                        grid-template-columns: 1fr;
                    }

                    .adx-filter-toolbar {
                        flex-wrap: wrap;
                    }
                }

                .lv-main {
                    border: 1px solid var(--line-color);
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                }

                .lv-prev {
                    position: relative;
                    aspect-ratio: 16 / 9;
                    background: var(--dark-theme);
                    padding: 8px;
                    display: grid;
                    grid-template-columns: 1fr 46px;
                    gap: 6px;
                }

                .lv-slide {
                    background: #F7F7F2;
                    color: var(--dark-theme);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    gap: 4px;
                    padding: 10px;
                    text-align: center;
                    font-size: 12px;
                }

                .lv-slide b {
                    font-size: 14px;
                }

                .lv-side {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .lv-side span {
                    flex: 1;
                    background: var(--light-theme);
                }

                .lv-lect {
                    position: absolute;
                    left: 14px;
                    bottom: 14px;
                    width: 58px;
                    height: 40px;
                    background: var(--main-button-hover);
                    border: 2px solid #2FA35A;
                    color: #fff;
                    display: grid;
                    place-items: center;
                    font-weight: 700;
                    font-size: 13px;
                }

                .lv-badge {
                    position: absolute;
                    left: 12px;
                    top: 12px;
                    background: #17803D;
                    color: #fff;
                    font-size: 11px;
                    font-weight: 700;
                    padding: 2px 8px;
                    z-index: 1;
                }

                .lv-badge.off {
                    background: #3A3A3C;
                }

                .lv-info {
                    padding: 12px;
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }

                .lv-list {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }

                .lv-item {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    padding: 8px 10px;
                }

                .lv-vi {
                    width: 36px;
                    height: 36px;
                    flex: 0 0 36px;
                }

                .livetxt {
                    font-size: 12.5px;
                    font-weight: 700;
                    color: var(--dark-red);
                }

                .ldot {
                    display: inline-block;
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background: #E5484D;
                    margin-right: 5px;
                    vertical-align: middle;
                    animation: adxPulse 1.4s infinite;
                }

                @keyframes adxPulse {
                    50% {
                        opacity: .3;
                    }
                }

                /* Action Centre items */
                .mac {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    min-height: 46px;
                    padding: 8px 12px;
                    color: var(--main-text-color);
                    font-size: 13.5px;
                    text-decoration: none;
                    transition: border-color 0.2s;
                }

                .mac:hover {
                    border-color: var(--dark-theme) !important;
                    color: var(--dark-theme);
                }

                .mac-ic {
                    width: 32px;
                    height: 32px;
                    flex: 0 0 32px;
                }

                .mac .cnt {
                    margin-left: auto;
                    min-width: 36px;
                    text-align: center;
                    padding: 2px 8px;
                    font-size: 13px;
                    font-weight: 600;
                }

                /* Important dates */
                .adx-seg {
                    display: inline-flex;
                    border: 1px solid var(--line-color);
                }

                .adx-seg button {
                    min-height: 32px;
                    padding: 0 10px;
                    border: 0;
                    background: transparent;
                    color: var(--sub-text);
                    font-weight: 600;
                    font-size: 12.5px;
                    cursor: pointer;
                }

                .adx-seg button.active {
                    background: var(--dark-theme);
                    color: #fff;
                }

                .cal-row {
                    display: flex;
                    gap: 12px;
                    align-items: center;
                    padding: 8px 10px;
                }

                .cal-row.hl {
                    background: var(--light-red);
                }

                .cal-row.hl .ct {
                    color: var(--dark-red);
                }

                .adx-dt {
                    width: 50px;
                    flex: 0 0 50px;
                    text-align: center;
                    padding: 4px 0;
                    font-weight: 700;
                    font-size: 17px;
                    line-height: 1.1;
                }

                .adx-dt small {
                    display: block;
                    font-size: 10px;
                    font-weight: 700;
                }
            </style>

            <div class="adx-page mt-0 p-0">

                <!-- 2. Single-Row Filter Toolbar (ALL IN ONE ROW) -->
                <form method="GET" class="adx-filter-toolbar" id="filterForm">
                    <!-- Date Range Box with Popover -->
                    <div class="adx-filter-col-date">
                        <button type="button" class="adx-date-btn" id="dateToggleBtn" onclick="toggleDatePopover()">
                            <span class="d-flex align-items-center gap-2">
                                <iconify-icon icon="mdi:calendar-range" width="17" height="17" class="text-secondary"></iconify-icon>
                                <span id="labelRangeText"><?= htmlspecialchars($rangeLabel) ?></span>
                            </span>
                            <iconify-icon icon="mdi:chevron-down" width="16" height="16" class="text-muted"></iconify-icon>
                        </button>

                        <div class="adx-date-popover p-3" id="datePopover">
                            <span class="text-12 fw-semibold text-secondary d-block mb-2">QUICK PRESETS</span>
                            <div class="d-grid gap-1 mb-3" style="grid-template-columns: repeat(3, 1fr);">
                                <button type="button" class="adx-preset-btn" onclick="applyPreset('today')">Today</button>
                                <button type="button" class="adx-preset-btn" onclick="applyPreset('week')">This Week</button>
                                <button type="button" class="adx-preset-btn" onclick="applyPreset('month')">This Month</button>
                                <button type="button" class="adx-preset-btn" onclick="applyPreset('last')">Last Month</button>
                                <button type="button" class="adx-preset-btn" onclick="applyPreset('3m')">3 Months</button>
                                <button type="button" class="adx-preset-btn" onclick="applyPreset('6m')">6 Months</button>
                            </div>

                            <span class="text-12 fw-semibold text-secondary d-block mb-2">CUSTOM RANGE</span>
                            <div class="mb-2">
                                <label class="text-11 text-muted d-block">From</label>
                                <input type="date" id="popFrom" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control form-control-sm">
                            </div>
                            <div class="mb-3">
                                <label class="text-11 text-muted d-block">To</label>
                                <input type="date" id="popTo" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control form-control-sm">
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-light border text-12" onclick="toggleDatePopover()">Cancel</button>
                                <button type="submit" class="custom-btn px-3 text-12" style="height:32px;">Apply</button>
                            </div>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="adx-filter-col-search">
                        <div class="adx-search-wrap">
                            <iconify-icon icon="mdi:magnify" width="18" height="18" class="adx-s-icon"></iconify-icon>
                            <input type="text" name="search" value="<?= htmlspecialchars($searchFilter) ?>" class="adx-search-input form-control custom-input" placeholder="Search intake, subject or lecturer...">
                            <?php if (!empty($searchFilter)) { ?>
                                <a href="?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&programme=<?= urlencode($progFilter) ?>" class="adx-search-clear" title="Clear">
                                    <iconify-icon icon="mdi:close" width="16" height="16"></iconify-icon>
                                </a>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Programme Select -->
                    <div class="adx-filter-col-prog">
                        <select name="programme" class="adx-prog-select" onchange="this.form.submit()">
                            <option value="0">All Programmes</option>
                            <?php foreach ($programmes as $p) { ?>
                                <option value="<?= $p['id'] ?>" <?= $progFilter == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Filter Actions -->
                    <div class="adx-filter-col-actions">
                        <button type="submit" class="custom-btn adx-btn-filter">Filter</button>
                        <?php if ($hasActiveFilter) { ?>
                            <a href="superAdminDashboard" class="adx-btn-reset">Reset</a>
                        <?php } ?>
                        <a href="javascript:void(0)" onclick="window.location.reload()" class="adx-btn-icon" title="Refresh">
                            <iconify-icon icon="mdi:refresh" width="18" height="18"></iconify-icon>
                        </a>
                    </div>
                </form>

                <!-- 3. ROW 1: Student Status Cards (Rendered in PHP) -->
                <div class="row g-3 mb-3">
                    <?php foreach ($studentCards as $c) { ?>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <div class="stats-mini-card"
                                <?= !empty($c['href'])
                                    ? 'onclick="window.location.href=\'' . htmlspecialchars($c['href'], ENT_QUOTES, 'UTF-8') . '\'" style="cursor:pointer;"'
                                    : '' ?>>
                                <div class="stats-mini-top">
                                    <div class="stats-mini-left">
                                        <div class="stats-mini-icon">
                                            <iconify-icon icon="<?= htmlspecialchars($c['icon']) ?>" width="16" height="16"></iconify-icon>
                                        </div>
                                        <span class="stats-mini-title"><?= htmlspecialchars($c['title']) ?></span>
                                    </div>
                                    <button type="button" class="stats-mini-menu">
                                        <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                    </button>
                                </div>
                                <div class="stats-mini-body">
                                    <div class="stats-mini-content">
                                        <div class="stats-mini-value"><?= number_format($c['value']) ?></div>
                                        <div class="stats-mini-meta">
                                            <?php if (isset($c['share'])) { ?>
                                                <span class="stats-mini-change <?= $toneCls($c['share_tone'] ?? 'o') ?>">
                                                    <?= $c['share'] ?>%
                                                </span>
                                            <?php } ?>
                                            <span class="stats-mini-period"><?= htmlspecialchars($c['note']) ?></span>
                                        </div>
                                    </div>
                                    <?= $renderBars($c['spark'] ?? []) ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- 4. ROW 2: Finance Cards (Rendered in PHP) -->
                <div class="row g-3 mb-3">
                    <?php foreach ($financeCards as $c) {
                        $valStr = ($c['format'] ?? '') === 'money'
                            ? '<span class="text-12 sub-text fw-medium">LKR </span>' . number_format($c['value'], 2)
                            : number_format($c['value']);

                        $changeStr = '';
                        $changeCls = 'neutral';
                        if (isset($c['change'])) {
                            if ($c['change'] == 0) {
                                $changeStr = '0%';
                                $changeCls = 'neutral';
                            } else {
                                $changeStr = ($c['change'] > 0 ? '+' : '') . $c['change'] . '%';
                                $good = !empty($c['good_up']) ? ($c['change'] > 0) : ($c['change'] < 0);
                                $changeCls = $good ? 'positive' : 'negative';
                            }
                        }
                    ?>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <div class="stats-mini-card"
                                <?= !empty($c['href'])
                                    ? 'onclick="window.location.href=\'' . htmlspecialchars($c['href'], ENT_QUOTES, 'UTF-8') . '\'" style="cursor:pointer;"'
                                    : '' ?>>
                                <div class="stats-mini-top">
                                    <div class="stats-mini-left">
                                        <div class="stats-mini-icon">
                                            <iconify-icon icon="<?= htmlspecialchars($c['icon']) ?>" width="16" height="16"></iconify-icon>
                                        </div>
                                        <span class="stats-mini-title"><?= htmlspecialchars($c['title']) ?></span>
                                    </div>
                                    <button type="button" class="stats-mini-menu">
                                        <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                    </button>
                                </div>
                                <div class="stats-mini-body">
                                    <div class="stats-mini-content">
                                        <div class="stats-mini-value"><?= $valStr ?></div>
                                        <div class="stats-mini-meta">
                                            <?php if ($changeStr !== '') { ?>
                                                <span class="stats-mini-change <?= $changeCls ?>"><?= $changeStr ?></span>
                                            <?php } ?>
                                            <span class="stats-mini-period">vs previous period</span>
                                        </div>
                                    </div>
                                    <?= $renderBars($c['spark'] ?? []) ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- 5. ROW 3: System Overview Cards (Rendered in PHP) -->
                <div class="row g-3 mb-4">
                    <?php foreach ($systemCards as $c) { ?>
                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                            <div class="stats-mini-card"
                                <?= !empty($c['href'])
                                    ? 'onclick="window.location.href=\'' . htmlspecialchars($c['href'], ENT_QUOTES, 'UTF-8') . '\'" style="cursor:pointer;"'
                                    : '' ?>>
                                <div class="stats-mini-top">
                                    <div class="stats-mini-left">
                                        <div class="stats-mini-icon">
                                            <iconify-icon icon="<?= htmlspecialchars($c['icon']) ?>" width="16" height="16"></iconify-icon>
                                        </div>
                                        <span class="stats-mini-title"><?= htmlspecialchars($c['title']) ?></span>
                                    </div>
                                    <button type="button" class="stats-mini-menu">
                                        <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                    </button>
                                </div>
                                <div class="stats-mini-body">
                                    <div class="stats-mini-content">
                                        <div class="stats-mini-value"><?= number_format($c['value']) ?></div>
                                        <div class="stats-mini-meta">
                                            <span class="stats-mini-period"><?= htmlspecialchars($c['note']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- 6. MIDDLE SECTION 1: Live Classes (Today) & Management Action Centre -->
                <div class="row g-4 mb-4">
                    <!-- Live Classes (Today) -->

                    <!-- Management Action Centre -->
                    <div class="col-12 col-xl-6">
                        <section class="component-theme p-4 h-100 d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="sub-topic">Management Action Centre</span>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($actionItems as $a) {
                                    $tone = $actionToneCls($a['tone']);
                                ?>
                                    <a class="mac bl-solid component-theme" href="<?= htmlspecialchars($a['href']) ?>">
                                        <span class="mac-ic d-flex align-items-center justify-content-center <?= $tone ?>">
                                            <iconify-icon icon="<?= htmlspecialchars($a['icon']) ?>" width="18" height="18"></iconify-icon>
                                        </span>
                                        <span><?= htmlspecialchars($a['label']) ?></span>
                                        <b class="cnt <?= $a['count'] ? $tone : 'status-green' ?>">
                                            <?= number_format($a['count']) ?>
                                        </b>
                                    </a>
                                <?php } ?>
                            </div>
                        </section>
                    </div>

                    <div class="col-12 col-xl-6">
                        <section class="component-theme p-4 h-100 d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="sub-topic">Important Dates</span>
                                <div class="d-flex align-items-center flex-wrap gap-3">
                                    <div class="adx-seg" role="group">
                                        <button type="button" id="tabBtnEvents" class="active" onclick="showCalTab('events')">
                                            Events (<?= count($eventsList) ?>)
                                        </button>
                                        <button type="button" id="tabBtnDeadlines" onclick="showCalTab('deadlines')">
                                            Deadlines (<?= count($deadlinesList) ?>)
                                        </button>
                                    </div>
                                    <a class="text-14 dark-theme text-decoration-none" href="event-calender">View calendar</a>
                                </div>
                            </div>

                            <!-- Events List Container -->
                            <div id="eventsTabContent" class="d-flex flex-column gap-2">
                                <?php if (!empty($eventsList)) {
                                    foreach ($eventsList as $e) {
                                        $ed = new DateTime($e['date']);
                                ?>
                                        <div class="cal-row <?= !empty($e['highlight']) ? 'hl' : '' ?>">
                                            <span class="adx-dt status-blue">
                                                <small><?= strtoupper($ed->format('M')) ?></small>
                                                <?= $ed->format('d') ?>
                                            </span>
                                            <span>
                                                <b class="ct text-14 text-dark-color d-block"><?= htmlspecialchars($e['title']) ?></b>
                                                <span class="text-12 sub-text"><?= htmlspecialchars($e['time']) ?></span>
                                            </span>
                                        </div>
                                    <?php }
                                } else { ?>
                                    <div class="col-12 px-4 py-3 component-theme">
                                        <div class="row align-items-center justify-content-center">
                                            <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                <div class="no-data-state">
                                                    <div class="no-data-icon-wrap">
                                                        <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                    </div>
                                                    <p class="no-data-text">No data</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                            <!-- Deadlines List Container -->
                            <div id="deadlinesTabContent" class="d-flex flex-column gap-2 d-none">
                                <?php if (!empty($deadlinesList)) {
                                    foreach ($deadlinesList as $d) {
                                        $dd = new DateTime($d['date']);
                                ?>
                                        <div class="cal-row <?= !empty($d['highlight']) ? 'hl' : '' ?>">
                                            <span class="adx-dt status-purple">
                                                <small><?= strtoupper($dd->format('M')) ?></small>
                                                <?= $dd->format('d') ?>
                                            </span>
                                            <span>
                                                <b class="ct text-14 text-dark-color d-block"><?= htmlspecialchars($d['title']) ?></b>
                                            </span>
                                        </div>
                                    <?php }
                                } else { ?>
                                    <div class="col-12 px-4 py-3 component-theme">
                                        <div class="row align-items-center justify-content-center">
                                            <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                <div class="no-data-state">
                                                    <div class="no-data-icon-wrap">
                                                        <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                    </div>
                                                    <p class="no-data-text">No data</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- 8. BOTTOM SECTION: Lectures Schedule Table (Rendered in PHP) -->
                <section class="component-theme p-4 d-flex flex-column gap-3 mb-5">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="sub-topic d-flex align-items-center gap-2">
                            Lectures – <?= htmlspecialchars($rangeLabel) ?> (<?= count($lecturesList) ?>)
                        </span>
                        <a class="text-14 dark-theme text-decoration-none" href="lecture-schedule">View all</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                            <thead>
                                <tr class="bg-table-header">
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">#</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Lecturer’s Name</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Lecture Type</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Intake / Batch</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Subject</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Date &amp; Time</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lecturesList)) {
                                    foreach ($lecturesList as $idx => $l) {
                                        $ldt = new DateTime($l['date']);
                                        $st = (new DateTime($l['start']))->format('h:i A');
                                        $et = (new DateTime($l['end']))->format('h:i A');
                                        $typeCls = ($l['type'] === 'Online') ? 'status-green' : 'status-blue';
                                        $stCls = $lectureStatusCls($l['status']);
                                ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if (!empty($l['profile'])) { ?>
                                                        <img class="hwr-40" style="object-fit:cover" src="<?= htmlspecialchars($l['profile']) ?>" alt="">
                                                    <?php } else { ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium text-14 flex-shrink-0">
                                                            <?= htmlspecialchars($l['initials']) ?>
                                                        </div>
                                                    <?php } ?>
                                                    <span class="text-dark-color fw-medium"><?= htmlspecialchars($l['lecturer']) ?></span>
                                                </div>
                                            </td>
                                            <td><span class="adx-pill <?= $typeCls ?>"><?= htmlspecialchars($l['type']) ?></span></td>
                                            <td><?= htmlspecialchars($l['intake']) ?></td>
                                            <td><?= htmlspecialchars($l['subject']) ?></td>
                                            <td>
                                                <?= $ldt->format('d M Y') ?><br>
                                                <span class="text-12 sub-text"><?= $st ?> - <?= $et ?></span>
                                            </td>
                                            <td><span class="adx-pill <?= $stCls ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="no-data-state py-4">
                                                <div class="no-data-icon-wrap"><iconify-icon icon="ph:empty-bold"></iconify-icon></div>
                                                <p class="no-data-text">No lectures found</p>
                                                <span class="text-12 sub-text">No lectures scheduled for this date range and filter criteria.</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>

            <!-- Lightweight Popover & Tab Toggle Script (No DOM Generation) -->
            <script>
                function toggleDatePopover() {
                    var pop = document.getElementById('datePopover');
                    if (!pop) return;
                    pop.classList.toggle('show');
                }

                function showCalTab(tab) {
                    var btnE = document.getElementById('tabBtnEvents');
                    var btnD = document.getElementById('tabBtnDeadlines');
                    var boxE = document.getElementById('eventsTabContent');
                    var boxD = document.getElementById('deadlinesTabContent');

                    if (tab === 'events') {
                        btnE.classList.add('active');
                        btnD.classList.remove('active');
                        boxE.style.display = 'flex';
                        $("#eventsTabContent").hasClass("d-none") ? $("#eventsTabContent").removeClass("d-none") : "";
                        $("#deadlinesTabContent").hasClass("d-none") ? "" : $("#deadlinesTabContent").addClass("d-none");

                    } else {
                        btnD.classList.add('active');
                        btnE.classList.remove('active');
                        $("#eventsTabContent").hasClass("d-none") ? "" : $("#eventsTabContent").addClass("d-none");
                        $("#deadlinesTabContent").hasClass("d-none") ? $("#deadlinesTabContent").removeClass("d-none") : "";
                    }

                }

                function applyPreset(key) {
                    var pad = function(n) {
                        return String(n).padStart(2, '0');
                    };
                    var iso = function(d) {
                        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
                    };
                    var today = new Date('<?= $todayIso ?>T12:00:00');
                    var y = today.getFullYear(),
                        m = today.getMonth(),
                        d = today.getDate();
                    var f = '',
                        t = '<?= $todayIso ?>';

                    if (key === 'today') {
                        f = t;
                    } else if (key === 'week') {
                        f = iso(new Date(y, m, d - 6));
                    } else if (key === 'month') {
                        f = iso(new Date(y, m, 1));
                    } else if (key === 'last') {
                        f = iso(new Date(y, m - 1, 1));
                        t = iso(new Date(y, m, 0));
                    } else if (key === '3m') {
                        f = iso(new Date(y, m - 2, 1));
                    } else if (key === '6m') {
                        f = iso(new Date(y, m - 5, 1));
                    }

                    document.getElementById('popFrom').value = f;
                    document.getElementById('popTo').value = t;
                    document.getElementById('filterForm').submit();
                }

                // Close popover when clicking outside
                document.addEventListener('click', function(e) {
                    var wrap = document.querySelector('.adx-filter-col-date');
                    var pop = document.getElementById('datePopover');
                    if (pop && pop.classList.contains('show') && wrap && !wrap.contains(e.target)) {
                        pop.classList.remove('show');
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        var pop = document.getElementById('datePopover');
                        if (pop) pop.classList.remove('show');
                    }
                });
            </script>

<?php
        }
    }
}
