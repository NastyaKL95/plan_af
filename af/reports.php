<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/reports_data.php';
require_once __DIR__ . '/includes/requests_data.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/pagination.php';

audit_fund_require_staff();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$requestsReport = audit_fund_reports_requests_summary();
$requestSummaryRows = $requestsReport['rows'];
$requestsReportError = $requestsReport['error'];

$reportsReqPerPage = audit_fund_pagination_per_page(audit_fund_get_string_key('req_per_page'), 20, 100);
$reportsReqPageRequested = audit_fund_pagination_page(audit_fund_get_string_key('req_page'));
$requestsDetailedReport = audit_fund_reports_requests_detailed_paged($reportsReqPageRequested, $reportsReqPerPage);
$requestsReqTotal = $requestsDetailedReport['total'];
$reportsReqTotalPages = audit_fund_pagination_total_pages($requestsReqTotal, $reportsReqPerPage);
$reportsReqPage = audit_fund_pagination_clamp_page($reportsReqPageRequested, $reportsReqTotalPages);
if ($reportsReqPage !== $reportsReqPageRequested) {
    $requestsDetailedReport = audit_fund_reports_requests_detailed_paged($reportsReqPage, $reportsReqPerPage);
}
$requestRows = $requestsDetailedReport['rows'];
$requestsDetailedError = $requestsDetailedReport['error'];

$reportsSchPerPage = audit_fund_pagination_per_page(audit_fund_get_string_key('sch_per_page'), 20, 100);
$reportsSchPageRequested = audit_fund_pagination_page(audit_fund_get_string_key('sch_page'));
$scheduleDetailedReport = audit_fund_reports_schedule_detailed_paged($reportsSchPageRequested, $reportsSchPerPage);
$reportsSchTotal = $scheduleDetailedReport['total'];
$reportsSchTotalPages = audit_fund_pagination_total_pages($reportsSchTotal, $reportsSchPerPage);
$reportsSchPage = audit_fund_pagination_clamp_page($reportsSchPageRequested, $reportsSchTotalPages);
if ($reportsSchPage !== $reportsSchPageRequested) {
    $scheduleDetailedReport = audit_fund_reports_schedule_detailed_paged($reportsSchPage, $reportsSchPerPage);
}
$scheduleRows = $scheduleDetailedReport['rows'];
$scheduleDetailedError = $scheduleDetailedReport['error'];

$scheduleReport = audit_fund_reports_schedule_summary();
$scheduleSummaryRows = $scheduleReport['rows'];
$scheduleReportError = $scheduleReport['error'];

/** @var array<string, string> $reportsReqPagerQuery */
$reportsReqPagerQuery = [];
if ($reportsSchPage > 1) {
    $reportsReqPagerQuery['sch_page'] = (string) $reportsSchPage;
}
if ($reportsSchPerPage !== 20) {
    $reportsReqPagerQuery['sch_per_page'] = (string) $reportsSchPerPage;
}
if ($reportsReqPerPage !== 20) {
    $reportsReqPagerQuery['req_per_page'] = (string) $reportsReqPerPage;
}

/** @var array<string, string> $reportsSchPagerQuery */
$reportsSchPagerQuery = [];
if ($reportsReqPage > 1) {
    $reportsSchPagerQuery['req_page'] = (string) $reportsReqPage;
}
if ($reportsReqPerPage !== 20) {
    $reportsSchPagerQuery['req_per_page'] = (string) $reportsReqPerPage;
}
if ($reportsSchPerPage !== 20) {
    $reportsSchPagerQuery['sch_per_page'] = (string) $reportsSchPerPage;
}
$auditFundPageTitle = 'Отчёты — Аудиторный фонд';
$auditFundNav = 'reports';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Отчёты</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Здесь собраны итоги: сколько заявок на бронирование аудиторий и в каком они состоянии (на рассмотрении, одобрено, отклонено); по расписанию — сколько занятых промежутков времени и их суммарная длительность в часах. Под одним промежутком понимается одна аудитория в конкретный день с указанным временем начала и окончания занятости.
      </p>

      <section class="stats-grid" aria-label="Ключевые показатели отчёта">
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Всего заявок</p>
          <p class="stat-card__value"><?php echo array_sum(array_map(static function (array $r): int { return (int) $r['requests_count']; }, $requestSummaryRows)); ?></p>
          <p class="stat-card__hint">все статусы: на рассмотрении, одобрено, отклонено</p>
        </article>
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Слотов расписания</p>
          <p class="stat-card__value stat-card__value--warning"><?php echo array_sum(array_map(static function (array $r): int { return (int) $r['entries_count']; }, $scheduleSummaryRows)); ?></p>
          <p class="stat-card__hint">количество строк в расписании (интервалов)</p>
        </article>
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Часов по расписанию</p>
          <p class="stat-card__value"><?php echo number_format(array_sum(array_map(static function (array $r): float { return (float) $r['hours_total']; }, $scheduleSummaryRows)), 1, '.', ''); ?></p>
          <p class="stat-card__hint">сумма длительностей всех слотов</p>
        </article>
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Активных аудиторий</p>
          <p class="stat-card__value stat-card__value--success"><?php echo count($scheduleSummaryRows); ?></p>
          <p class="stat-card__hint">аудитории с хотя бы одним слотом в расписании</p>
        </article>
      </section>

      <section class="panel animate-on-load animate-on-load--d3" aria-labelledby="reports-table-title">
        <div class="panel__head">
          <h2 class="panel__title" id="reports-table-title">Отчёт по заявкам</h2>
          <div class="panel__tools">
            <form method="get" action="reports.php" class="reports-page-size-form">
              <?php if ($reportsSchPage > 1): ?>
              <input type="hidden" name="sch_page" value="<?php echo (int) $reportsSchPage; ?>">
              <?php endif; ?>
              <?php if ($reportsSchPerPage !== 20): ?>
              <input type="hidden" name="sch_per_page" value="<?php echo (int) $reportsSchPerPage; ?>">
              <?php endif; ?>
              <label class="visually-hidden" for="reports-req-per-page">Заявок на странице</label>
              <select class="form__input panel__search panel__select" id="reports-req-per-page" name="req_per_page" aria-label="Заявок на странице отчёта" onchange="this.form.submit()">
                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                <option value="<?php echo (int) $pp; ?>"<?php echo $reportsReqPerPage === $pp ? ' selected' : ''; ?>><?php echo (int) $pp; ?> на стр.</option>
                <?php endforeach; ?>
              </select>
            </form>
            <a class="btn btn--primary" href="reports_requests_export.php">Экспорт в Excel</a>
          </div>
        </div>
        <?php if ($requestsDetailedError === null && $requestsReqTotal > 0): ?>
        <p class="app-intro app-intro--panel-tight">В таблице — постраничный просмотр всех заявок. Полный список — в Excel.</p>
        <?php endif; ?>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">№</th>
                <th scope="col">Подана</th>
                <th scope="col">Аудитория</th>
                <th scope="col">Период</th>
                <th scope="col">Заявитель</th>
                <th scope="col">Имя пользователя</th>
                <th scope="col">Статус</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($requestsDetailedError !== null): ?>
              <tr>
                <td colspan="7" class="data-table__message"><?php echo htmlspecialchars($requestsDetailedError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif (count($requestRows) === 0): ?>
              <tr>
                <td colspan="7" class="data-table__message">Данных по заявкам нет.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($requestRows as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) $row['request_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $row['submitted_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['room_number'] . ' — ' . (string) $row['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $row['booking_date'])) . ', ' . substr((string) $row['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $row['time_end']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['applicant_display'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php
                    $byUser = isset($row['requested_by_username']) && trim((string) $row['requested_by_username']) !== ''
                      ? (string) $row['requested_by_username']
                      : '—';
                    echo htmlspecialchars($byUser, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                  ?></td>
                  <td><?php echo htmlspecialchars(audit_fund_report_request_status_label((string) $row['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
        if ($requestsDetailedError === null) {
            audit_fund_pagination_render('reports.php', $reportsReqPagerQuery, 'req_page', $reportsReqPage, $reportsReqTotalPages, $requestsReqTotal, $reportsReqPerPage, 'заявок');
        }
        ?>
        <p class="app-intro app-intro--after-pagination">Экспорт в Excel содержит все заявки без ограничения по страницам.</p>
      </section>

      <section class="panel animate-on-load animate-on-load--d3" aria-labelledby="reports-schedule-title">
        <div class="panel__head">
          <h2 class="panel__title" id="reports-schedule-title">Отчёт по расписанию</h2>
          <div class="panel__tools">
            <form method="get" action="reports.php" class="reports-page-size-form">
              <?php if ($reportsReqPage > 1): ?>
              <input type="hidden" name="req_page" value="<?php echo (int) $reportsReqPage; ?>">
              <?php endif; ?>
              <?php if ($reportsReqPerPage !== 20): ?>
              <input type="hidden" name="req_per_page" value="<?php echo (int) $reportsReqPerPage; ?>">
              <?php endif; ?>
              <label class="visually-hidden" for="reports-sch-per-page">Слотов на странице</label>
              <select class="form__input panel__search panel__select" id="reports-sch-per-page" name="sch_per_page" aria-label="Слотов расписания на странице" onchange="this.form.submit()">
                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                <option value="<?php echo (int) $pp; ?>"<?php echo $reportsSchPerPage === $pp ? ' selected' : ''; ?>><?php echo (int) $pp; ?> на стр.</option>
                <?php endforeach; ?>
              </select>
            </form>
            <a class="btn btn--primary" href="reports_schedule_export.php">Экспорт в Excel</a>
          </div>
        </div>
        <?php if ($scheduleDetailedError === null && $reportsSchTotal > 0): ?>
        <p class="app-intro app-intro--panel-tight">В таблице — постраничный просмотр слотов расписания. Полный набор — в Excel.</p>
        <?php endif; ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">День</th>
                <th scope="col">Время</th>
                <th scope="col">Аудитория</th>
                <th scope="col">Комментарий</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($scheduleDetailedError !== null): ?>
              <tr>
                <td colspan="4" class="data-table__message"><?php echo htmlspecialchars($scheduleDetailedError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif (count($scheduleRows) === 0): ?>
              <tr>
                <td colspan="4" class="data-table__message">Данных расписания нет.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($scheduleRows as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $row['entry_date'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(substr((string) $row['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $row['time_end']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['room_number'] . ' — ' . (string) $row['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($row['comment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
        if ($scheduleDetailedError === null) {
            audit_fund_pagination_render('reports.php', $reportsSchPagerQuery, 'sch_page', $reportsSchPage, $reportsSchTotalPages, $reportsSchTotal, $reportsSchPerPage, 'слотов');
        }
        ?>
        <p class="app-intro app-intro--after-pagination">Экспорт в Excel содержит все слоты расписания.</p>
      </section>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>
