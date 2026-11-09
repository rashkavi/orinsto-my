<?php
class SuperAdminDashboard
{
    public static function loadContent()
    {
        $sessionUserRole = SessionUser::getCurrentUserRole();

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole != "Lecturer")) {
            return;
        }

        require_once "./includes/classes/AdminDashboardSummary.php";

        $timezone = new DateTimeZone("Asia/Colombo");
        $now = new DateTime("now", $timezone);
        $todayIso = $now->format('Y-m-d');

        $defaultFrom = $now->format('Y-m-01');
        $defaultTo = $todayIso;

        $startDate = !empty($_GET['start_date']) ? (string)$_GET['start_date'] : $defaultFrom;
        $endDate = !empty($_GET['end_date']) ? (string)$_GET['end_date'] : $defaultTo;
        $progFilter = isset($_GET['programme']) ? (int)$_GET['programme'] : 0;
        $searchFilter = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

        $data = AdminDashboardSummary::getComprehensiveDashboardData([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'programme_id' => $progFilter,
            'search' => $searchFilter,
        ]);

        // The data class validates/normalises the dates; use what it actually applied.
        $startDate = $data['range']['from'];
        $endDate = $data['range']['to'];
        $hasActiveFilter = ($startDate !== $defaultFrom || $endDate !== $defaultTo || $progFilter > 0 || $searchFilter !== '');

        $studentCards = $data['student_cards'];
        $financeCards = $data['finance_cards'];
        $systemCards = $data['system_cards'];
        $insights = $data['insights'];
        $trend = $data['trend'];
        $programmeMix = $data['programme_mix'];
        $liveClasses = $data['live'];
        $actionItems = $data['actions'];
        $batchesList = $data['batches'];
        $eventsList = $data['events'];
        $deadlinesList = $data['deadlines'];
        $lecturesList = $data['lectures'];
        $programmes = $data['programmes'];

        $fmtDate = function ($d, $f = 'd M Y') {
            return (new DateTime($d))->format($f);
        };
        $rangeLabel = ($startDate === $endDate && $startDate === $todayIso)
            ? "Today, " . $fmtDate($todayIso)
            : $fmtDate($startDate) . " – " . $fmtDate($endDate);
        $prevLabel = $fmtDate($data['range']['prev_from'], 'd M') . " – " . $fmtDate($data['range']['prev_to']);

        $selectedProgramme = 'All programmes';
        foreach ($programmes as $p) {
            if ((int)$p['id'] === $progFilter) {
                $selectedProgramme = $p['name'];
            }
        }

        $e = function ($v) {
            return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        };
        $money = function ($v, $decimals = 2) {
            return number_format((float)$v, $decimals);
        };
        // Compact money for tight spaces: 1.25M, 640.5K
        $moneyShort = function ($v) {
            $v = (float)$v;
            if (abs($v) >= 1000000) return rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.') . 'M';
            if (abs($v) >= 1000) return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'K';
            return number_format($v, 0);
        };
        $time = function ($t) {
            return date('h:i A', strtotime($t));
        };

        // Change indicator vs previous period.
        $changeHtml = function ($change, $goodUp = true) {
            if ($change === null) {
                return '<span class="sad-delta neutral">New</span>';
            }
            if ((float)$change == 0) {
                return '<span class="sad-delta neutral">0%</span>';
            }
            $up = $change > 0;
            $good = $goodUp ? $up : !$up;
            $icon = $up ? 'mdi:arrow-top-right' : 'mdi:arrow-bottom-right';
            return '<span class="sad-delta ' . ($good ? 'positive' : 'negative') . '">'
                . '<iconify-icon icon="' . $icon . '" width="13" height="13"></iconify-icon>'
                . ($up ? '+' : '') . $change . '%</span>';
        };

        // Mini bar sparkline (last 6 months, current month highlighted).
        $spark = function ($values, $isMoney = false) use ($moneyShort) {
            if (empty($values)) return '';
            $max = max(max($values), 1);
            $html = '<div class="sad-spark" aria-hidden="true">';
            $last = count($values) - 1;
            foreach ($values as $i => $v) {
                $h = $v > 0 ? max(4, (int)round(($v / $max) * 34)) : 2;
                $label = $isMoney ? 'LKR ' . $moneyShort($v) : number_format($v);
                $html .= '<span class="' . ($i === $last ? 'on' : '') . '" style="height:' . $h . 'px" title="' . $label . '"></span>';
            }
            return $html . '</div>';
        };

        $toneVar = [
            'good' => 'var(--dark-green)',
            'warn' => 'var(--dark-orange)',
            'bad' => 'var(--dark-red)',
            'info' => 'var(--dark-theme)',
        ];
        $actionToneCls = ['warn' => 'status-orange', 'bad' => 'status-red', 'info' => 'status-blue'];
        $batchStateCls = [
            'Running' => 'status-green',
            'New intake' => 'status-blue',
            'Ending soon' => 'status-orange',
            'Completed' => 'status-gray',
        ];
        // Same badge classes as the system's other tables.
        $lectureStatusCls = [
            'Ongoing' => 'table-btn-success',
            'Upcoming' => 'table-btn-primary',
            'Completed' => 'table-btn-green-blue',
            'Cancelled' => 'table-btn-danger',
            'Postponed' => 'table-btn-orange',
        ];

        $liveNow = count(array_filter($liveClasses, function ($l) {
            return $l['status'] === 'Live';
        }));
        $openActions = array_sum(array_column($actionItems, 'count'));
        $mixMax = max(1, max(array_merge([0], array_map('intval', array_column($programmeMix, 'students')))));

        $baseQuery = function ($overrides = []) use ($startDate, $endDate, $progFilter, $searchFilter) {
            return http_build_query(array_merge([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'programme' => $progFilter,
                'search' => $searchFilter,
            ], $overrides));
        };

        // System empty state (same component used across the ERP).
        $emptyState = function ($text = 'No data') use ($e) {
            return '<div class="no-data-state">'
                . '<div class="no-data-icon-wrap"><iconify-icon icon="ph:empty-bold"></iconify-icon></div>'
                . '<p class="no-data-text">' . $e($text) . '</p>'
                . '</div>';
        };
?>
        <style>
            /* ─── Super Admin dashboard ─── white theme · square corners · no tinted card backgrounds ─── */
            .sad {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                color: var(--main-text-color);
                /* Chart series: collected = theme blue, expected = lighter blue (validated pair) */
                --sad-s1: var(--dark-theme);
                --sad-s2: #9AAFFF;
                --sad-muted: #C9D5FF;
                --sad-grid: #F0F2F6;
            }

            [data-custom-theme="dark"] .sad {
                --sad-muted: #34487E;
                --sad-grid: #263247;
            }

            .sad *,
            .sad *::before,
            .sad *::after {
                border-radius: 0;
            }

            .sad .min-w-0 {
                min-width: 0;
            }

            .sad .text-13 {
                font-size: 13px;
            }

            .sad-panel {
                background: var(--custom-white);
                min-width: 0;
                max-width: 100%;
            }

            .sad-panel-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                padding: 18px 20px 10px;
            }

            .sad-panel-body {
                padding: 16px 20px;
            }


            .sad-scope {
                font-size: 11px;
                font-weight: 600;
                letter-spacing: .4px;
                text-transform: uppercase;
                color: var(--sub-text);
                padding: 0;
                white-space: nowrap;
            }

            .sad-link {
                font-size: 13px;
                font-weight: 500;
                color: var(--dark-theme);
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 2px;
                white-space: nowrap;
            }

            .sad-link:hover {
                color: var(--main-button-hover);
                text-decoration: underline;
            }

            /* Page header */
            .sad-head {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }

            .sad-head h1 {
                font-size: 22px;
                font-weight: 700;
                color: var(--dark-color);
                margin: 0 0 4px;
            }

            .sad-head p {
                margin: 0;
                font-size: 13px;
                color: var(--sub-text);
            }

            .sad-head-stats {
                display: flex;
                gap: 8px;
            }

            .sad-head-stats div {
                padding: 8px 16px;
                background: var(--custom-white);
                display: flex;
                flex-direction: column;
                min-width: 110px;
            }

            .sad-head-stats b {
                font-size: 18px;
                color: var(--dark-color);
                line-height: 1.2;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .sad-head-stats span {
                font-size: 11.5px;
                color: var(--sub-text);
            }

            /* Filter toolbar */
            .sad-filter {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                margin-bottom: 20px;
                flex-wrap: nowrap;
            }

            .sad-f-date {
                flex: 0 0 260px;
                position: relative;
            }

            .sad-f-search {
                flex: 1 1 auto;
                min-width: 200px;
                position: relative;
            }

            .sad-f-prog {
                flex: 0 0 220px;
            }

            .sad-f-actions {
                flex: 0 0 auto;
                display: flex;
                gap: 8px;
            }

            /* Date range toggle: a button styled to sit with the system custom-input / custom-select controls */
            .sad-control {
                height: 48px;
                width: 100%;
                background: var(--custom-white);
                border: 1px solid var(--line-color);
                font-size: 13.5px;
                color: var(--main-text-color);
                padding: 0 12px;
                outline: none;
            }

            .sad-control:hover {
                border-color: var(--dark-theme);
            }

            .sad-control:focus {
                border-color: var(--dark-theme);
                box-shadow: 0 0 0 0.25rem var(--light-theme);
            }

            button.sad-control {
                white-space: nowrap;
                gap: 8px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                cursor: pointer;
            }

            .sad-f-search .sad-search-input {
                height: 48px;
                padding-left: 40px;
                padding-right: 34px;
            }

            .sad-f-search .ico {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--sub-text);
            }

            .sad-f-search .clear {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--sub-text);
                display: flex;
            }

            .sad-btn {
                height: 48px;
                padding: 0 18px;
                border: 1px solid var(--dark-theme);
                background: var(--dark-theme);
                color: #fff;
                font-size: 14px;
                font-weight: 500;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                text-decoration: none;
                cursor: pointer;
                white-space: nowrap;
            }

            .sad-btn:hover {
                background: var(--main-button-hover);
                color: #fff;
            }

            .sad-btn.ghost {
                background: var(--custom-white);
                color: var(--main-text-color);
                border-color: var(--line-color);
            }

            .sad-btn.ghost:hover {
                color: var(--dark-theme);
                border-color: var(--dark-theme);
            }

            .sad-btn.icon {
                width: 48px;
                padding: 0;
                justify-content: center;
            }

            .sad-popover {
                display: none;
                position: absolute;
                top: 52px;
                left: 0;
                z-index: 1050;
                width: 320px;
                padding: 16px;
                background: var(--custom-white);
                box-shadow: 0 14px 35px rgba(15, 23, 42, .12);
            }

            .sad-popover.show {
                display: block;
            }

            .sad-popover .cap {
                display: block;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: .4px;
                color: var(--sub-text);
                margin-bottom: 8px;
            }

            .sad-presets {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 6px;
                margin-bottom: 14px;
            }

            .sad-presets button {
                height: 30px;
                font-size: 12px;
                border: 1px solid transparent;
                background: var(--table-header-color);
                color: var(--main-text-color);
            }

            .sad-presets button:hover {
                color: var(--dark-theme);
                border-color: var(--dark-theme);
            }

            .sad-popover input[type=date] {
                height: 36px;
                font-size: 13px;
            }

            /* KPI grid – one rectangle split into cells */














            .sad-delta {
                display: inline-flex;
                align-items: center;
                gap: 1px;
                font-size: 12px;
                font-weight: 700;
            }

            .sad-delta.positive {
                color: var(--dark-green);
            }

            .sad-delta.negative {
                color: var(--dark-red);
            }

            .sad-delta.neutral {
                color: var(--sub-text);
            }

            /* Summary cards (project stats-mini-card) */
            .sad-card {
                display: block;
                height: 100%;
                color: inherit;
                text-decoration: none;
            }

            .sad-card:hover {
                color: inherit;
            }

            .sad-card .stats-mini-value {
                font-size: 28px;
                white-space: nowrap;
            }

            .sad-card .stats-mini-change {
                display: inline-flex;
                align-items: center;
            }

            /* Thin meter */
            .sad-meter {
                height: 4px;
                background: var(--line-color);
                width: 100%;
                overflow: hidden;
            }

            .sad-meter i {
                display: block;
                height: 100%;
                background: var(--dark-theme);
            }

            .sad-spark {
                display: flex;
                align-items: flex-end;
                gap: 3px;
                height: 34px;
                flex-shrink: 0;
            }

            .sad-spark span {
                width: 8px;
                background: var(--light-theme);
            }

            .sad-spark span.on {
                background: var(--dark-theme);
            }

            /* Pills */
            .sad-pill {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 12px;
                font-weight: 600;
                padding: 3px 9px;
                white-space: nowrap;
            }

            .sad .status-gray {
                background: var(--table-header-color);
                color: var(--sub-text);
            }

            .sad-dot {
                width: 7px;
                height: 7px;
                border-radius: 50% !important;
                background: currentColor;
                display: inline-block;
            }

            .sad-dot.pulse {
                animation: sadPulse 1.4s ease-in-out infinite;
            }

            @keyframes sadPulse {
                0%, 100% { opacity: 1; }
                50% { opacity: .25; }
            }

            /* Live classes */
            .sad-live {
                display: grid;
                grid-template-columns: 112px minmax(0, 1fr) auto;
                gap: 16px;
                align-items: center;
                padding: 12px 20px;
                border-bottom: 1px solid var(--line-color);
            }

            .sad-live:last-child {
                border-bottom: 0;
            }

            .sad-live.is-live {
                box-shadow: inset 3px 0 0 var(--dark-green);
            }

            .sad-live.is-ended {
                opacity: .65;
            }

            .sad-live-time b {
                display: block;
                font-size: 14px;
                color: var(--dark-color);
            }

            .sad-live-time span {
                font-size: 12px;
                color: var(--sub-text);
            }

            .sad-live-main b {
                display: block;
                font-size: 14px;
                color: var(--dark-color);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sad-live-main span {
                font-size: 12.5px;
                color: var(--sub-text);
                display: block;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sad-live-side {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .sad-type {
                font-size: 12px;
                color: var(--sub-text);
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            .sad-join {
                height: 30px;
                padding: 0 12px;
                font-size: 12.5px;
            }

            /* Action centre */
            .sad-action {
                display: grid;
                grid-template-columns: 36px minmax(0, 1fr) auto 16px;
                gap: 12px;
                align-items: center;
                padding: 12px 20px;
                border-bottom: 1px solid var(--line-color);
                color: inherit;
                text-decoration: none;
            }

            .sad-action:last-child {
                border-bottom: 0;
            }

            .sad-action:hover {
                background: var(--table-header-color);
                color: inherit;
            }

            .sad-action-ic {
                width: 36px;
                height: 36px;
                background: var(--light-theme);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sad-action b {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: var(--dark-color);
            }

            .sad-action small {
                font-size: 12px;
                color: var(--sub-text);
            }

            .sad-action .cnt {
                font-size: 20px;
                font-weight: 700;
                color: var(--dark-color);
                min-width: 32px;
                text-align: right;
            }

            .sad-action .cnt.zero {
                color: var(--sub-text);
                font-weight: 500;
            }

            /* Snapshot list */
            .sad-snap {
                display: flex;
                flex-direction: column;
            }

            .sad-snap-row {
                padding: 14px 20px;
                border-bottom: 1px solid var(--line-color);
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .sad-snap-row:last-child {
                border-bottom: 0;
            }

            .sad-snap-top {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                gap: 8px;
            }

            .sad-snap-top span {
                font-size: 13px;
                color: var(--sub-text);
                font-weight: 500;
            }

            .sad-snap-top b {
                font-size: 20px;
                color: var(--dark-color);
            }

            .sad-split {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }

            .sad-split div {
                padding: 8px 10px;
                background: var(--table-header-color);
            }

            .sad-split b {
                display: block;
                font-size: 16px;
                color: var(--dark-color);
            }

            .sad-split span {
                font-size: 11.5px;
                color: var(--sub-text);
            }

            /* Chart */
            .sad-chart {
                position: relative;
                height: 280px;
            }

            .sad-chart.sm {
                height: 220px;
            }

            .sad-chart.fill {
                height: 100%;
                min-height: 280px;
            }

            .sad-legend {
                display: flex;
                gap: 16px;
                font-size: 12.5px;
                color: var(--main-text-color);
                flex-wrap: wrap;
            }

            .sad-legend span {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .sad-legend i {
                width: 10px;
                height: 10px;
                display: inline-block;
            }

            /* Lectures table uses the system table classes; keep it scrollable on small screens */
            #dashboard-lectures-table {
                min-width: 920px;
            }

            /* Batches */
            .sad-batch {
                padding: 14px 20px;
                border-bottom: 1px solid var(--line-color);
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .sad-batch:last-child {
                border-bottom: 0;
            }

            .sad-batch-top {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                align-items: flex-start;
            }

            .sad-batch-top b {
                display: block;
                font-size: 14px;
                color: var(--dark-color);
            }

            .sad-batch-top small {
                font-size: 12px;
                color: var(--sub-text);
            }

            .sad-batch-foot {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                color: var(--sub-text);
                gap: 8px;
                flex-wrap: wrap;
            }

            /* Tabs */
            .sad-seg {
                display: inline-flex;
                background: var(--table-header-color);
            }

            .sad-seg button {
                border: 0;
                background: transparent;
                font-size: 12.5px;
                font-weight: 500;
                color: var(--sub-text);
                padding: 6px 12px;
            }

            .sad-seg button.active {
                background: var(--custom-white);
                color: var(--dark-theme);
                box-shadow: inset 0 -2px 0 var(--dark-theme);
            }

            /* Dates */
            .sad-date-row {
                display: grid;
                grid-template-columns: 48px minmax(0, 1fr) auto;
                gap: 14px;
                align-items: center;
                padding: 12px 20px;
                border-bottom: 1px solid var(--line-color);
            }

            .sad-date-row:last-child {
                border-bottom: 0;
            }

            .sad-date-row.hl {
                box-shadow: inset 3px 0 0 var(--dark-theme);
            }

            .sad-cal {
                width: 48px;
                background: var(--light-theme);
                text-align: center;
                line-height: 1.1;
                padding: 4px 0;
            }

            .sad-cal small {
                display: block;
                font-size: 10.5px;
                font-weight: 700;
                color: var(--dark-theme);
                letter-spacing: .5px;
            }

            .sad-cal b {
                font-size: 18px;
                color: var(--dark-color);
            }

            .sad-date-row .t {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: var(--dark-color);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sad-date-row .s {
                font-size: 12px;
                color: var(--sub-text);
            }

            .sad-away {
                font-size: 12px;
                color: var(--sub-text);
                white-space: nowrap;
            }

            .sad-away.soon {
                color: var(--dark-orange);
                font-weight: 600;
            }

            /* Programme mix */
            .sad-mix {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 70px;
                gap: 6px 14px;
                align-items: center;
                padding: 12px 20px;
                border-bottom: 1px solid var(--line-color);
            }

            .sad-mix:last-child {
                border-bottom: 0;
            }

            .sad-mix .n {
                font-size: 13.5px;
                font-weight: 500;
                color: var(--dark-color);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sad-mix .v {
                text-align: right;
                font-size: 14px;
                font-weight: 700;
                color: var(--dark-color);
            }

            .sad-mix .sad-meter {
                height: 6px;
                grid-column: 1 / 2;
            }

            .sad-mix .sad-meter i.active {
                background: var(--dark-theme);
            }

            .sad-mix .sad-meter i.rest {
                background: var(--sad-s2);
            }

            .sad-mix small {
                text-align: right;
                font-size: 11.5px;
                color: var(--sub-text);
            }

            /* Table */






            /* Keep the system empty-state icon round (the dashboard squares everything else) */
            .sad .no-data-icon-wrap {
                border-radius: 50%;
            }

            @media (max-width: 1199.98px) {
                .sad-filter {
                    flex-wrap: wrap;
                }

                .sad-f-search {
                    flex-basis: calc(100% - 250px);
                }
            }

            @media (max-width: 767.98px) {
                .sad-f-date,
                .sad-f-search,
                .sad-f-prog {
                    flex: 1 1 100%;
                }

                .sad-live {
                    grid-template-columns: 1fr;
                    gap: 6px;
                }

                .sad-head-stats {
                    width: 100%;
                }

                .sad-head-stats div {
                    flex: 1;
                    min-width: 0;
                }
            }
        </style>

        <div class="sad p-0">

            <!-- Header -->
            <div class="sad-head">
                <div>
                    <h1>Campus overview</h1>
                    <p>
                        <?= $e($rangeLabel) ?> · <?= $e($selectedProgramme) ?>
                        <?php if ($searchFilter !== '') { ?> · “<?= $e($searchFilter) ?>”<?php } ?>
                        · compared with <?= $e($prevLabel) ?>
                    </p>
                </div>
                <div class="sad-head-stats">
                    <div>
                        <b><?php if ($liveNow > 0) { ?><span class="sad-dot pulse" style="color:var(--dark-green)"></span><?php } ?><?= $liveNow ?></b>
                        <span>Classes live now</span>
                    </div>
                    <div>
                        <b><?= number_format($insights['campus_active']) ?></b>
                        <span>Active enrolments</span>
                    </div>
                    <div>
                        <b><?= number_format($openActions) ?></b>
                        <span>Items need action</span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="sad-filter" id="filterForm" autocomplete="off">
                <div class="sad-f-date">
                    <button type="button" class="sad-control" id="dateToggleBtn" onclick="sadToggleDate()" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center gap-2">
                            <iconify-icon icon="mdi:calendar-range" width="17" height="17" class="sub-text"></iconify-icon>
                            <span><?= $e($rangeLabel) ?></span>
                        </span>
                        <iconify-icon icon="mdi:chevron-down" width="16" height="16" class="sub-text"></iconify-icon>
                    </button>
                    <div class="sad-popover" id="datePopover">
                        <span class="cap">QUICK PRESETS</span>
                        <div class="sad-presets">
                            <button type="button" onclick="sadPreset('today')">Today</button>
                            <button type="button" onclick="sadPreset('week')">Last 7 days</button>
                            <button type="button" onclick="sadPreset('month')">This month</button>
                            <button type="button" onclick="sadPreset('last')">Last month</button>
                            <button type="button" onclick="sadPreset('3m')">3 months</button>
                            <button type="button" onclick="sadPreset('6m')">6 months</button>
                        </div>
                        <span class="cap">CUSTOM RANGE</span>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="text-12 sub-text d-block mb-1" for="popFrom">From</label>
                                <input type="date" id="popFrom" name="start_date" value="<?= $e($startDate) ?>" max="<?= $e($todayIso) ?>" class="form-control custom-input">
                            </div>
                            <div class="col-6">
                                <label class="text-12 sub-text d-block mb-1" for="popTo">To</label>
                                <input type="date" id="popTo" name="end_date" value="<?= $e($endDate) ?>" class="form-control custom-input">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="sad-btn ghost" style="height:34px" onclick="sadToggleDate()">Cancel</button>
                            <button type="submit" class="sad-btn" style="height:34px">Apply</button>
                        </div>
                    </div>
                </div>

                <div class="sad-f-search">
                    <iconify-icon icon="mdi:magnify" width="18" height="18" class="ico"></iconify-icon>
                    <input type="text" name="search" value="<?= $e($searchFilter) ?>" class="form-control custom-input sad-search-input" placeholder="Search student, intake, subject or lecturer...">
                    <?php if ($searchFilter !== '') { ?>
                        <a href="?<?= $e($baseQuery(['search' => ''])) ?>" class="clear" title="Clear search">
                            <iconify-icon icon="mdi:close" width="16" height="16"></iconify-icon>
                        </a>
                    <?php } ?>
                </div>

                <div class="sad-f-prog">
                    <select name="programme" class="form-select custom-select" onchange="this.form.submit()" aria-label="Programme">
                        <option value="0">All programmes</option>
                        <?php foreach ($programmes as $p) { ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $progFilter === (int)$p['id'] ? 'selected' : '' ?>><?= $e($p['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="sad-f-actions">
                    <button type="submit" class="sad-btn">
                        <iconify-icon icon="mdi:filter-variant" width="17" height="17"></iconify-icon>Filter
                    </button>
                    <?php if ($hasActiveFilter) { ?>
                        <a href="dashboard" class="sad-btn ghost">Reset</a>
                    <?php } ?>
                    <a href="javascript:void(0)" onclick="window.location.reload()" class="sad-btn ghost icon" title="Refresh">
                        <iconify-icon icon="mdi:refresh" width="18" height="18"></iconify-icon>
                    </a>
                </div>
            </form>

            <!-- Summary cards -->
            <?php
            $renderCard = function ($c, $valueHtml, $metaHtml, $sparkHtml = '') use ($e) {
            ?>
                <div class="col-12 col-md-6 col-xl-3">
                    <a class="stats-mini-card sad-card" href="<?= $e($c['href']) ?>">
                        <div class="stats-mini-top">
                            <div class="stats-mini-left">
                                <div class="stats-mini-icon">
                                    <iconify-icon icon="<?= $e($c['icon']) ?>" width="16" height="16"></iconify-icon>
                                </div>
                                <span class="stats-mini-title"><?= $e($c['title']) ?></span>
                            </div>
                        </div>
                        <div class="stats-mini-body">
                            <div class="stats-mini-content">
                                <div class="stats-mini-value"><?= $valueHtml ?></div>
                                <div class="stats-mini-meta"><?= $metaHtml ?></div>
                            </div>
                            <?= $sparkHtml ?>
                        </div>
                    </a>
                </div>
            <?php
            };
            ?>
            <div class="row g-3 mb-4">
                <?php
                // Students (admitted in the selected period)
                foreach ($studentCards as $c) {
                    if (isset($c['share'])) {
                        $meta = '<span class="stats-mini-change" style="color:' . $toneVar[$c['tone']] . '">' . $c['share'] . '%</span>'
                            . '<span class="stats-mini-period">of admitted students</span>';
                        $renderCard($c, number_format($c['value']), $meta);
                    } else {
                        $meta = '<span class="stats-mini-period">Admitted in period · ' . number_format($insights['campus_total']) . ' all time</span>';
                        $renderCard($c, number_format($c['value']), $meta, $spark($trend['admissions']));
                    }
                }

                // Admissions & finance (selected period)
                foreach ($financeCards as $c) {
                    $isMoney = ($c['format'] ?? '') === 'money';
                    $value = $isMoney ? $money($c['value']) : number_format($c['value']);
                    if (array_key_exists('change', $c)) {
                        $meta = $changeHtml($c['change'], $c['good_up']) . '<span class="stats-mini-period">vs previous period</span>';
                    } else {
                        $meta = '<span class="stats-mini-change" style="color:var(--dark-theme)">' . $c['rate'] . '%</span>'
                            . '<span class="stats-mini-period">collected of due</span>';
                    }
                    $renderCard($c, $value, $meta, isset($c['spark']) ? $spark($c['spark'], $isMoney) : '');
                }

                // System (all campus)
                foreach ($systemCards as $c) {
                    $renderCard($c, number_format($c['value']), '<span class="stats-mini-period">' . $e($c['note']) . '</span>');
                }
                ?>
            </div>

            <div class="row g-4 mb-4">
                <!-- Live classes (today) -->
                <div class="col-12 col-xl-7">
                    <section class="sad-panel h-100 d-flex flex-column">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Live Classes</span> <span class="sad-scope">Today · <?= $e($fmtDate($todayIso, 'd M')) ?></span></div>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($liveNow > 0) { ?>
                                    <span class="sad-pill status-green"><span class="sad-dot pulse"></span><?= $liveNow ?> live now</span>
                                <?php } ?>
                                <a class="sad-link" href="ongoing-lecture-attendance">Monitor<iconify-icon icon="mdi:chevron-right" width="16" height="16"></iconify-icon></a>
                            </div>
                        </div>
                        <div class="flex-grow-1 <?= empty($liveClasses) ? 'd-flex align-items-center' : '' ?>">
                            <?php if (!empty($liveClasses)) {
                                foreach ($liveClasses as $l) {
                                    $rowCls = $l['status'] === 'Live' ? 'is-live' : ($l['status'] === 'Ended' ? 'is-ended' : '');
                            ?>
                                    <div class="sad-live <?= $rowCls ?>">
                                        <div class="sad-live-time">
                                            <b><?= $time($l['start']) ?></b>
                                            <span>to <?= $time($l['end']) ?></span>
                                        </div>
                                        <div class="sad-live-main">
                                            <b><?= $e($l['title']) ?></b>
                                            <span><?= $e($l['intake']) ?> · <?= $e($l['lecturer']) ?></span>
                                        </div>
                                        <div class="sad-live-side">
                                            <span class="sad-type">
                                                <iconify-icon icon="<?= $l['type'] === 'Online' ? 'mdi:video-outline' : 'mdi:map-marker-outline' ?>" width="15" height="15"></iconify-icon><?= $e($l['type']) ?>
                                            </span>
                                            <?php if ($l['status'] === 'Live') { ?>
                                                <span class="sad-pill status-green"><span class="sad-dot pulse"></span>Live</span>
                                                <?php if ($l['link'] !== '') { ?>
                                                    <a class="sad-btn sad-join" href="<?= $e($l['link']) ?>" target="_blank" rel="noopener">Join</a>
                                                <?php } ?>
                                            <?php } elseif ($l['status'] === 'Upcoming') { ?>
                                                <span class="sad-pill status-blue">Upcoming</span>
                                            <?php } else { ?>
                                                <span class="sad-pill status-gray">Ended</span>
                                            <?php } ?>
                                        </div>
                                    </div>
                            <?php }
                            } else {
                                echo $emptyState('No classes scheduled for today');
                            } ?>
                        </div>
                    </section>
                </div>

                <!-- Management action centre -->
                <div class="col-12 col-xl-5">
                    <section class="sad-panel h-100">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Management Action Centre</span></div>
                            <span class="sad-scope">Right now</span>
                        </div>
                        <div>
                            <?php foreach ($actionItems as $a) {
                                $tone = $actionToneCls[$a['tone']] ?? 'status-blue';
                                $tag = $a['href'] !== '' ? 'a' : 'div';
                            ?>
                                <<?= $tag ?> class="sad-action" <?= $a['href'] !== '' ? 'href="' . $e($a['href']) . '"' : '' ?>>
                                    <span class="sad-action-ic" style="color:<?= $toneVar[$a['tone']] ?? 'var(--dark-theme)' ?>">
                                        <iconify-icon icon="<?= $e($a['icon']) ?>" width="19" height="19"></iconify-icon>
                                    </span>
                                    <span class="min-w-0">
                                        <b><?= $e($a['label']) ?></b>
                                        <small><?= $e($a['hint']) ?></small>
                                    </span>
                                    <?php if ($a['count'] > 0) { ?>
                                        <span class="cnt"><?= number_format($a['count']) ?></span>
                                    <?php } else { ?>
                                        <span class="cnt zero" title="All clear"><iconify-icon icon="mdi:check" width="18" height="18" style="color:var(--dark-green)"></iconify-icon></span>
                                    <?php } ?>
                                    <iconify-icon icon="mdi:chevron-right" width="16" height="16" class="sub-text"></iconify-icon>
                                </<?= $tag ?>>
                            <?php } ?>
                        </div>
                    </section>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Fee collection trend -->
                <div class="col-12 col-xl-8">
                    <section class="sad-panel h-100 d-flex flex-column">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Fee collection</span> <span class="sad-scope">Last 6 months</span></div>
                            <div class="sad-legend">
                                <span><i style="background:var(--sad-s2)"></i>Expected (due)</span>
                                <span><i style="background:var(--sad-s1)"></i>Collected</span>
                            </div>
                        </div>
                        <div class="sad-panel-body flex-grow-1 d-flex flex-column justify-content-center">
                            <?php if (array_sum($trend['expected']) + array_sum($trend['received']) > 0) { ?>
                                <div class="sad-chart fill">
                                    <canvas id="sadFeeChart" role="img" aria-label="Expected versus collected fees per month for the last six months"></canvas>
                                </div>
                            <?php } else {
                                echo $emptyState('No fee data for the last 6 months');
                            } ?>
                        </div>
                    </section>
                </div>

                <!-- Snapshot -->
                <div class="col-12 col-xl-4">
                    <section class="sad-panel h-100 d-flex flex-column">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Period snapshot</span></div>
                            <span class="sad-scope">Selected period</span>
                        </div>
                        <div class="sad-snap flex-grow-1">
                            <div class="sad-snap-row">
                                <div class="sad-snap-top">
                                    <span>Collection rate</span>
                                    <b><?= $insights['collection_rate'] ?>%</b>
                                </div>
                                <span class="sad-meter"><i style="width:<?= (float)$insights['collection_rate'] ?>%"></i></span>
                                <span class="text-12 sub-text">LKR <?= $money($insights['collected_on_due']) ?> collected against instalments due in period</span>
                            </div>
                            <div class="sad-snap-row">
                                <div class="sad-snap-top">
                                    <span>Outstanding today</span>
                                    <b><span class="text-12 sub-text fw-medium">LKR</span> <?= $money($insights['outstanding']) ?></b>
                                </div>
                                <span class="text-12 sub-text"><?= number_format($insights['outstanding_students']) ?> active students with overdue balances</span>
                            </div>
                            <div class="sad-snap-row">
                                <div class="sad-snap-top">
                                    <span>Attendance rate</span>
                                    <b><?= $insights['attendance_rate'] !== null ? $insights['attendance_rate'] . '%' : '—' ?></b>
                                </div>
                                <span class="sad-meter"><i style="width:<?= (float)($insights['attendance_rate'] ?? 0) ?>%; background:<?= ($insights['attendance_rate'] !== null && $insights['attendance_rate'] < AdminDashboardSummary::ATTENDANCE_THRESHOLD) ? 'var(--dark-orange)' : 'var(--dark-theme)' ?>"></i></span>
                                <span class="text-12 sub-text">
                                    <?php if ($insights['attendance_change'] !== null) { ?>
                                        <?php $ac = $insights['attendance_change']; ?>
                                        <b class="<?= $ac >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($ac > 0 ? '+' : '') . $ac ?> pts</b> vs previous period ·
                                    <?php } ?>
                                    <?= number_format($insights['attendance_marked']) ?> marks recorded
                                </span>
                            </div>
                            <div class="sad-snap-row">
                                <div class="sad-snap-top">
                                    <span>Lectures held</span>
                                    <b><?= number_format($insights['lectures_total']) ?></b>
                                </div>
                                <div class="sad-split">
                                    <div><b><?= number_format($insights['lectures_physical']) ?></b><span>Physical</span></div>
                                    <div><b><?= number_format($insights['lectures_online']) ?></b><span>Online</span></div>
                                    <div><b><?= number_format($insights['lectures_cancelled']) ?></b><span>Cancelled</span></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Batches -->
                <div class="col-12 col-xl-6">
                    <section class="sad-panel h-100 d-flex flex-column">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Batches</span> <span class="sad-scope">Course progress</span></div>
                            <a class="sad-link" href="intakes">All intakes<iconify-icon icon="mdi:chevron-right" width="16" height="16"></iconify-icon></a>
                        </div>
                        <div class="flex-grow-1 <?= empty($batchesList) ? 'd-flex align-items-center' : '' ?>">
                            <?php if (!empty($batchesList)) {
                                foreach ($batchesList as $b) {
                            ?>
                                    <div class="sad-batch">
                                        <div class="sad-batch-top">
                                            <span class="min-w-0">
                                                <b><?= $e($b['intake']) ?></b>
                                                <small><?= $e($b['programme']) ?></small>
                                            </span>
                                            <span class="sad-pill <?= $batchStateCls[$b['state']] ?? 'status-gray' ?>"><?= $e($b['state']) ?></span>
                                        </div>
                                        <span class="sad-meter" title="<?= $b['progress'] !== null ? $b['progress'] . '% of course duration' : 'Dates not set' ?>">
                                            <i style="width:<?= (int)($b['progress'] ?? 0) ?>%"></i>
                                        </span>
                                        <div class="sad-batch-foot">
                                            <span>
                                                <b class="text-dark-color"><?= number_format($b['active']) ?></b> active of <?= number_format($b['students']) ?> students
                                            </span>
                                            <span>
                                                <?php if ($b['progress'] === null) { ?>
                                                    Start / end dates not set
                                                <?php } else { ?>
                                                    <b class="text-dark-color"><?= $b['progress'] ?>%</b>
                                                    · <?= $e($fmtDate($b['start'], 'd M y')) ?> – <?= $e($fmtDate($b['end'], 'd M y')) ?>
                                                    <?php if ($b['days_left'] !== null) { ?> · <?= number_format($b['days_left']) ?> days left<?php } ?>
                                                <?php } ?>
                                            </span>
                                        </div>
                                    </div>
                            <?php }
                            } else {
                                echo $emptyState('No batches found');
                            } ?>
                        </div>
                    </section>
                </div>

                <!-- Important dates -->
                <div class="col-12 col-xl-6">
                    <section class="sad-panel h-100">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Important Dates</span></div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="sad-seg" role="tablist">
                                    <button type="button" id="tabBtnEvents" class="active" role="tab" onclick="sadCalTab('events')">Events (<?= count($eventsList) ?>)</button>
                                    <button type="button" id="tabBtnDeadlines" role="tab" onclick="sadCalTab('deadlines')">Deadlines (<?= count($deadlinesList) ?>)</button>
                                </div>
                                <a class="sad-link" href="event-calender">Calendar<iconify-icon icon="mdi:chevron-right" width="16" height="16"></iconify-icon></a>
                            </div>
                        </div>
                        <?php
                        $renderDates = function ($list, $emptyText) use ($e, $emptyState) {
                            if (empty($list)) {
                                echo $emptyState($emptyText);
                                return;
                            }
                            foreach ($list as $d) {
                                $dt = new DateTime($d['date']);
                                $away = $d['days_away'];
                                $awayTxt = $away === 0 ? 'Today' : ($away === 1 ? 'Tomorrow' : ($away === -1 ? 'Yesterday' : ($away > 1 ? 'In ' . $away . ' days' : abs($away) . ' days ago')));
                        ?>
                                <div class="sad-date-row <?= $d['highlight'] ? 'hl' : '' ?>">
                                    <span class="sad-cal"><small><?= strtoupper($dt->format('M')) ?></small><b><?= $dt->format('d') ?></b></span>
                                    <span class="min-w-0">
                                        <span class="t"><?= $e($d['title']) ?></span>
                                        <span class="s">
                                            <?= $e(ucwords(str_replace('_', ' ', $d['type']))) ?> · <?= $e($d['time']) ?>
                                            <?php if (!empty($d['location'])) { ?> · <?= $e($d['location']) ?><?php } ?>
                                        </span>
                                    </span>
                                    <span class="sad-away <?= ($away >= 0 && $away <= 3) ? 'soon' : '' ?>"><?= $e($awayTxt) ?></span>
                                </div>
                        <?php
                            }
                        };
                        ?>
                        <div id="eventsTabContent"><?php $renderDates($eventsList, 'No upcoming events'); ?></div>
                        <div id="deadlinesTabContent" class="d-none"><?php $renderDates($deadlinesList, 'No upcoming deadlines'); ?></div>
                    </section>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Admissions trend -->
                <div class="col-12 col-xl-6">
                    <section class="sad-panel h-100 d-flex flex-column">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">New admissions</span> <span class="sad-scope">Last 6 months</span></div>
                            <span class="text-13 sub-text"><b class="text-dark-color"><?= number_format(array_sum($trend['admissions'])) ?></b> students</span>
                        </div>
                        <div class="sad-panel-body flex-grow-1 d-flex flex-column justify-content-center">
                            <?php if (array_sum($trend['admissions']) > 0) { ?>
                                <div class="sad-chart sm">
                                    <canvas id="sadAdmissionChart" role="img" aria-label="New admissions per month for the last six months"></canvas>
                                </div>
                            <?php } else {
                                echo $emptyState('No admissions in the last 6 months');
                            } ?>
                        </div>
                    </section>
                </div>

                <!-- Students by programme -->
                <div class="col-12 col-xl-6">
                    <section class="sad-panel h-100">
                        <div class="sad-panel-head">
                            <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Students by programme</span> <span class="sad-scope">All time</span></div>
                            <div class="sad-legend">
                                <span><i style="background:var(--sad-s1)"></i>Active</span>
                                <span><i style="background:var(--sad-s2)"></i>Other</span>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($programmeMix)) {
                                foreach ($programmeMix as $m) {
                                    $total = (int)$m['students'];
                                    $act = (int)$m['active'];
                                    $wAct = round(($act / $mixMax) * 100, 1);
                                    $wRest = round((($total - $act) / $mixMax) * 100, 1);
                            ?>
                                    <a class="sad-mix text-decoration-none" href="?<?= $e($baseQuery(['programme' => (int)$m['id']])) ?>" title="Filter dashboard by this programme">
                                        <span class="n"><?= $e($m['name']) ?></span>
                                        <span class="v"><?= number_format($total) ?></span>
                                        <span class="sad-meter d-flex" style="gap:2px;background:var(--table-header-color)">
                                            <i class="active" style="width:<?= $wAct ?>%"></i>
                                            <?php if ($wRest > 0) { ?><i class="rest" style="width:<?= $wRest ?>%"></i><?php } ?>
                                        </span>
                                        <small><?= number_format($act) ?> active</small>
                                    </a>
                            <?php }
                            } else {
                                echo $emptyState('No programmes found');
                            } ?>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Lectures -->
            <section class="sad-panel mb-5">
                <div class="sad-panel-head">
                    <div class="d-flex align-items-center gap-2 flex-wrap"><span class="sub-topic">Lectures</span> <span class="sad-scope"><?= $e($rangeLabel) ?></span></div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-13 sub-text">Showing <?= count($lecturesList) ?><?= count($lecturesList) >= 25 ? ' latest' : '' ?></span>
                        <a class="sad-link" href="lecture-schedule">View all<iconify-icon icon="mdi:chevron-right" width="16" height="16"></iconify-icon></a>
                    </div>
                </div>
                <div class="table-responsive-wrapper">
                    <div class="table-responsive">
                        <table class="table bl-solid m-0 p-0 g-0" id="dashboard-lectures-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">#</th>
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
                                ?>
                                        <tr>
                                            <td class="px-4 py-3 align-middle"><span class="text-14 main-text"><?= $idx + 1 ?></span></td>
                                            <td class="py-2 align-middle">
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($l['profile'])) { ?>
                                                        <img src="<?= $e($l['profile']) ?>" class="hwr-40" style="object-fit:cover" alt="Lecturer image">
                                                    <?php } else { ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $e($l['initials']) ?></div>
                                                    <?php } ?>
                                                    <span class="text-14 main-text mx-2"><?= $e($l['lecturer']) ?></span>
                                                </div>
                                            </td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $e($l['type']) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $e($l['intake']) ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $e($l['subject']) ?></span></td>
                                            <td class="py-2 align-middle text-nowrap">
                                                <span class="text-14 main-text d-block"><?= $e($fmtDate($l['date'], 'Y-m-d')) ?></span>
                                                <span class="text-12 sub-text"><?= $time($l['start']) ?> - <?= $time($l['end']) ?></span>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <button type="button" class="table-btn <?= $lectureStatusCls[$l['status']] ?? 'table-btn-primary' ?> w-100"><?= $e($l['status']) ?></button>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="7" class="py-3"><?= $emptyState('No lectures found') ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <script>
            (function() {
                var TODAY = '<?= $todayIso ?>';
                var trend = <?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

                window.sadToggleDate = function() {
                    var pop = document.getElementById('datePopover');
                    var btn = document.getElementById('dateToggleBtn');
                    if (!pop) return;
                    pop.classList.toggle('show');
                    btn.setAttribute('aria-expanded', pop.classList.contains('show'));
                };

                window.sadCalTab = function(tab) {
                    var isEvents = tab === 'events';
                    document.getElementById('tabBtnEvents').classList.toggle('active', isEvents);
                    document.getElementById('tabBtnDeadlines').classList.toggle('active', !isEvents);
                    document.getElementById('eventsTabContent').classList.toggle('d-none', !isEvents);
                    document.getElementById('deadlinesTabContent').classList.toggle('d-none', isEvents);
                };

                window.sadPreset = function(key) {
                    var pad = function(n) {
                        return String(n).padStart(2, '0');
                    };
                    var iso = function(d) {
                        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
                    };
                    var today = new Date(TODAY + 'T12:00:00');
                    var y = today.getFullYear(),
                        m = today.getMonth(),
                        d = today.getDate();
                    var f = TODAY,
                        t = TODAY;
                    if (key === 'week') f = iso(new Date(y, m, d - 6));
                    else if (key === 'month') f = iso(new Date(y, m, 1));
                    else if (key === 'last') {
                        f = iso(new Date(y, m - 1, 1));
                        t = iso(new Date(y, m, 0));
                    } else if (key === '3m') f = iso(new Date(y, m - 2, 1));
                    else if (key === '6m') f = iso(new Date(y, m - 5, 1));
                    document.getElementById('popFrom').value = f;
                    document.getElementById('popTo').value = t;
                    document.getElementById('filterForm').submit();
                };

                document.addEventListener('click', function(ev) {
                    var wrap = document.querySelector('.sad-f-date');
                    var pop = document.getElementById('datePopover');
                    if (pop && pop.classList.contains('show') && wrap && !wrap.contains(ev.target)) {
                        pop.classList.remove('show');
                    }
                });
                document.addEventListener('keydown', function(ev) {
                    if (ev.key === 'Escape') {
                        var pop = document.getElementById('datePopover');
                        if (pop) pop.classList.remove('show');
                    }
                });

                function lkr(v) {
                    return 'LKR ' + Number(v).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                function shortNum(v) {
                    if (Math.abs(v) >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                    if (Math.abs(v) >= 1e3) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
                    return v;
                }

                var css = getComputedStyle(document.querySelector('.sad'));
                var C = function(name, fallback) {
                    var v = css.getPropertyValue(name).trim();
                    return v || fallback;
                };

                function baseOptions(yTick) {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: C('--custom-white', '#ffffff'),
                                titleColor: C('--dark-color', '#1D2026'),
                                bodyColor: C('--main-text-color', '#4E5566'),
                                borderColor: C('--line-color', '#E6E9F0'),
                                borderWidth: 1,
                                cornerRadius: 0,
                                padding: 10,
                                boxPadding: 4,
                                usePointStyle: true
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                border: {
                                    color: C('--line-color', '#E6E9F0')
                                },
                                ticks: {
                                    color: C('--sub-text', '#8C94A3'),
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: C('--sad-grid', '#F0F2F6')
                                },
                                border: {
                                    display: false
                                },
                                ticks: {
                                    color: C('--sub-text', '#8C94A3'),
                                    font: {
                                        size: 12
                                    },
                                    maxTicksLimit: 5,
                                    precision: 0,
                                    callback: yTick
                                }
                            }
                        }
                    };
                }

                window.addEventListener('load', function() {
                    if (typeof Chart === 'undefined') return;

                    var fee = document.getElementById('sadFeeChart');
                    if (fee) {
                        var feeOpts = baseOptions(function(v) {
                            return shortNum(v);
                        });
                        feeOpts.plugins.tooltip.callbacks = {
                            label: function(ctx) {
                                return ' ' + ctx.dataset.label + ': ' + lkr(ctx.parsed.y);
                            },
                            afterBody: function(items) {
                                var exp = trend.expected[items[0].dataIndex];
                                var rec = trend.received[items[0].dataIndex];
                                return exp > 0 ? ' Collected vs due: ' + Math.round(rec / exp * 100) + '%' : '';
                            }
                        };
                        new Chart(fee, {
                            type: 'bar',
                            data: {
                                labels: trend.labels,
                                datasets: [{
                                    label: 'Expected (due)',
                                    data: trend.expected,
                                    backgroundColor: C('--sad-s2', '#9AAFFF'),
                                    hoverBackgroundColor: C('--sad-s2', '#9AAFFF'),
                                    borderRadius: {
                                        topLeft: 4,
                                        topRight: 4
                                    },
                                    borderSkipped: 'bottom',
                                    maxBarThickness: 22,
                                    categoryPercentage: 0.6,
                                    barPercentage: 0.9
                                }, {
                                    label: 'Collected',
                                    data: trend.received,
                                    backgroundColor: C('--sad-s1', '#4f72ff'),
                                    hoverBackgroundColor: C('--main-button-hover', '#3E5EE6'),
                                    borderRadius: {
                                        topLeft: 4,
                                        topRight: 4
                                    },
                                    borderSkipped: 'bottom',
                                    maxBarThickness: 22,
                                    categoryPercentage: 0.6,
                                    barPercentage: 0.9
                                }]
                            },
                            options: feeOpts
                        });
                    }

                    var adm = document.getElementById('sadAdmissionChart');
                    if (adm) {
                        var admOpts = baseOptions();
                        admOpts.plugins.tooltip.callbacks = {
                            label: function(ctx) {
                                return ' ' + ctx.parsed.y + ' new students';
                            }
                        };
                        new Chart(adm, {
                            type: 'bar',
                            data: {
                                labels: trend.labels,
                                datasets: [{
                                    label: 'New admissions',
                                    data: trend.admissions,
                                    backgroundColor: C('--sad-s1', '#4f72ff'),
                                    hoverBackgroundColor: C('--main-button-hover', '#3E5EE6'),
                                    borderRadius: {
                                        topLeft: 4,
                                        topRight: 4
                                    },
                                    borderSkipped: 'bottom',
                                    maxBarThickness: 36
                                }]
                            },
                            options: admOpts
                        });
                    }
                });
            })();
        </script>
<?php
    }
}
