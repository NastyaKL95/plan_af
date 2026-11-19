<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/schedule_data.php';
require_once __DIR__ . '/includes/schedule_calendar_helpers.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/flash_messages.php';

audit_fund_require_auth();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$flashSchedule = audit_fund_flash_take('schedule.php');
$logoutErrCode = isset($flashSchedule['logout_err']) ? (string) $flashSchedule['logout_err'] : '';
$logoutErrText = audit_fund_flash_text($logoutErrCode, audit_fund_flash_messages_logout(), '');
$pageView = audit_fund_get_string('view') === 'month' ? 'month' : 'week';

$weekStartGet = audit_fund_get_string('week_start');
$weekStartParam = $weekStartGet !== ''
    ? $weekStartGet
    : (isset($flashSchedule['week_start']) ? (string) $flashSchedule['week_start'] : '');
$weekStartTs = $weekStartParam !== '' ? strtotime($weekStartParam) : false;
if ($weekStartTs === false) {
    $todayTs = strtotime('today');
    $dow = (int) date('N', $todayTs);
    $weekStartTs = strtotime('-' . ($dow - 1) . ' days', $todayTs);
}
$weekEndTs = strtotime('+6 days', $weekStartTs);
$weekStart = date('Y-m-d', $weekStartTs);
$weekEnd = date('Y-m-d', $weekEndTs);
$prevWeekStart = date('Y-m-d', strtotime('-7 days', $weekStartTs));
$nextWeekStart = date('Y-m-d', strtotime('+7 days', $weekStartTs));
$todayTs = strtotime('today');
$todayDow = (int) date('N', $todayTs);
$currentWeekStart = date('Y-m-d', strtotime('-' . ($todayDow - 1) . ' days', $todayTs));
$scheduleMoveDateMin = htmlspecialchars(date('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$monthYm = '';
$gridStart = '';
$gridEnd = '';
$gridStartTs = 0;
$gridEndTs = 0;
$prevMonthYm = '';
$nextMonthYm = '';
$scheduleWeekStartForForms = $weekStart;

if ($pageView === 'month') {
    $monthYmRaw = trim(audit_fund_get_string('month'));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthYmRaw)) {
        $monthYmRaw = date('Y-m');
    }
    $monthYm = $monthYmRaw;
    $monthFirstTs = strtotime($monthYm . '-01');
    if ($monthFirstTs === false) {
        $monthYm = date('Y-m');
        $monthFirstTs = strtotime($monthYm . '-01');
    }
    $monthLastDayNum = (int) date('t', $monthFirstTs);
    $monthEndStr = $monthYm . '-' . str_pad((string) $monthLastDayNum, 2, '0', STR_PAD_LEFT);
    $dowMonthStart = (int) date('N', $monthFirstTs);
    $gridStartTs = strtotime('-' . ($dowMonthStart - 1) . ' days', $monthFirstTs);
    $monthLastTs = strtotime($monthEndStr);
    $dowMonthEnd = (int) date('N', $monthLastTs);
    $gridEndTs = strtotime('+' . (7 - $dowMonthEnd) . ' days', $monthLastTs);
    $gridStart = date('Y-m-d', $gridStartTs);
    $gridEnd = date('Y-m-d', $gridEndTs);
    // Сохраняем неделю из URL/flash; иначе — понедельник первой строки сетки (иначе «Неделя» уезжает назад)
    $scheduleWeekStartForForms = $weekStartParam !== '' ? $weekStart : $gridStart;
    $prevMonthYm = date('Y-m', strtotime('-1 month', $monthFirstTs));
    $nextMonthYm = date('Y-m', strtotime('+1 month', $monthFirstTs));

    $scheduleData = audit_fund_schedule_entries_data($gridStart, $gridEnd);
    $maintenanceData = audit_fund_schedule_maintenance_data($gridStart, $gridEnd);
} else {
    $scheduleData = audit_fund_schedule_entries_data($weekStart, $weekEnd);
    $maintenanceData = audit_fund_schedule_maintenance_data($weekStart, $weekEnd);
}
$scheduleRows = $scheduleData['rows'];
$scheduleError = $scheduleData['error'];
$maintenanceRows = $maintenanceData['rows'];
$maintenanceRooms = $maintenanceData['rooms'];
$maintenanceError = $maintenanceData['error'];
$scheduleWeekStartValue = htmlspecialchars($scheduleWeekStartForForms, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$relocateRoomsData = audit_fund_requests_rooms();
$relocateRooms = $relocateRoomsData['rooms'];
$relocateRoomsError = $relocateRoomsData['error'];
$scheduleRequestTodayYmd = date('Y-m-d');
$maintSaved = (audit_fund_get_string('maint_saved') === '1') || !empty($flashSchedule['maint_saved']);
$maintUpdated = (audit_fund_get_string('maint_updated') === '1') || !empty($flashSchedule['maint_updated']);
$maintDeleted = (audit_fund_get_string('maint_deleted') === '1') || !empty($flashSchedule['maint_deleted']);
$gMaintErr = audit_fund_get_string('maint_err');
$maintErrCode = $gMaintErr !== ''
    ? $gMaintErr
    : (isset($flashSchedule['maint_err']) ? (string) $flashSchedule['maint_err'] : '');
$gMaintUpdErr = audit_fund_get_string('maint_upd_err');
$maintUpdErrCode = $gMaintUpdErr !== ''
    ? $gMaintUpdErr
    : (isset($flashSchedule['maint_upd_err']) ? (string) $flashSchedule['maint_upd_err'] : '');
$gMaintUpdId = audit_fund_get_string_key('maint_upd_id');
$maintUpdErrId = $gMaintUpdId !== null ? (int) $gMaintUpdId : (isset($flashSchedule['maint_upd_id']) ? (int) $flashSchedule['maint_upd_id'] : 0);
$gMaintDelErr = audit_fund_get_string('maint_del_err');
$maintDelErrCode = $gMaintDelErr !== ''
    ? $gMaintDelErr
    : (isset($flashSchedule['maint_del_err']) ? (string) $flashSchedule['maint_del_err'] : '');
$maintFlash = audit_fund_flash_messages_maintenance();
$maintErrText = audit_fund_flash_text($maintErrCode, $maintFlash, 'Не удалось добавить окно обслуживания.');
$maintUpdErrText = audit_fund_flash_text($maintUpdErrCode, $maintFlash, 'Не удалось изменить окно обслуживания.');
$maintDelErrText = audit_fund_flash_text($maintDelErrCode, audit_fund_flash_messages_maintenance_delete(), 'Не удалось удалить окно обслуживания.');
$schedMoved = (audit_fund_get_string('sched_moved') === '1') || !empty($flashSchedule['sched_moved']);
$gSchedMoveErr = audit_fund_get_string('sched_move_err');
$schedMoveErrCode = $gSchedMoveErr !== ''
    ? $gSchedMoveErr
    : (isset($flashSchedule['sched_move_err']) ? (string) $flashSchedule['sched_move_err'] : '');
$schedMoveErrText = audit_fund_flash_text($schedMoveErrCode, audit_fund_flash_messages_schedule_move(), 'Не удалось перенести запись расписания.');
$schedDeleted = (audit_fund_get_string('sched_deleted') === '1') || !empty($flashSchedule['sched_deleted']);
$gSchedDelErr = audit_fund_get_string('sched_del_err');
$schedDelErrCode = $gSchedDelErr !== ''
    ? $gSchedDelErr
    : (isset($flashSchedule['sched_del_err']) ? (string) $flashSchedule['sched_del_err'] : '');
$schedDelErrText = audit_fund_flash_text($schedDelErrCode, audit_fund_flash_messages_schedule_delete(), 'Не удалось удалить запись расписания.');
$requestSaved = (audit_fund_get_string('request_saved') === '1') || !empty($flashSchedule['request_saved']);
$gSchedReqErr = audit_fund_get_string('request_err');
$requestErrCode = $gSchedReqErr !== ''
    ? $gSchedReqErr
    : (isset($flashSchedule['request_err']) ? (string) $flashSchedule['request_err'] : '');
$requestErrText = audit_fund_flash_text($requestErrCode, audit_fund_flash_messages_request_create(), 'Не удалось создать заявку.');
$requestErrHints = isset($flashSchedule['request_err_hints']) ? (string) $flashSchedule['request_err_hints'] : '';
$requestModalRestore = null;
if (!empty($flashSchedule['request_modal_open'])) {
    $requestModalRestore = [
        'roomId' => (int) ($flashSchedule['request_modal_room_id'] ?? 0),
        'bookingDate' => (string) ($flashSchedule['request_modal_booking_date'] ?? ''),
        'timeStart' => (string) ($flashSchedule['request_modal_time_start'] ?? ''),
        'timeEnd' => (string) ($flashSchedule['request_modal_time_end'] ?? ''),
        'applicant' => (string) ($flashSchedule['request_modal_applicant'] ?? ''),
        'comment' => (string) ($flashSchedule['request_modal_comment'] ?? ''),
        'error' => $requestErrText,
        'hints' => $requestErrHints,
    ];
}

$calendarByDate = [];
$monthGridCells = [];
$legendMap = [];
if ($pageView === 'month') {
    $calendarByDateMonth = [];
    for ($t = $gridStartTs; $t <= $gridEndTs; $t = strtotime('+1 day', $t)) {
        $calendarByDateMonth[date('Y-m-d', $t)] = [];
    }
    foreach ($scheduleRows as $row) {
        $dateKey = (string) $row['entry_date'];
        if (isset($calendarByDateMonth[$dateKey])) {
            $calendarByDateMonth[$dateKey][] = $row;
        }
        $legendKey = (string) $row['building_name'] . '|' . (string) $row['room_number'];
        if (!isset($legendMap[$legendKey])) {
            $legendMap[$legendKey] = [
                'label' => (string) $row['room_number'] . ', ' . (string) $row['building_name'],
                'color' => (string) ($row['color_hex'] ?? '#3B82F6'),
            ];
        }
    }
    for ($t = $gridStartTs; $t <= $gridEndTs; $t = strtotime('+1 day', $t)) {
        $d = date('Y-m-d', $t);
        $monthGridCells[] = [
            'date' => $d,
            'in_month' => substr($d, 0, 7) === $monthYm,
            'events' => $calendarByDateMonth[$d] ?? [],
        ];
    }
    $rangeLabel = audit_fund_schedule_month_title($monthYm);
} else {
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime('+' . $i . ' days', $weekStartTs));
        $calendarByDate[$d] = [];
    }
    foreach ($scheduleRows as $row) {
        $dateKey = (string) $row['entry_date'];
        if (isset($calendarByDate[$dateKey])) {
            $calendarByDate[$dateKey][] = $row;
        }
        $legendKey = (string) $row['building_name'] . '|' . (string) $row['room_number'];
        if (!isset($legendMap[$legendKey])) {
            $legendMap[$legendKey] = [
                'label' => (string) $row['room_number'] . ', ' . (string) $row['building_name'],
                'color' => (string) ($row['color_hex'] ?? '#3B82F6'),
            ];
        }
    }
    $rangeLabel = audit_fund_schedule_range_label($weekStart, $weekEnd);
}
$monthFromCurrentWeekYm = date('Y-m', strtotime($weekStart));
$weekTabWeekStart = $scheduleWeekStartForForms;
$monthTabYm = $pageView === 'month' ? $monthYm : $monthFromCurrentWeekYm;
$monthUrlWeekQs = '&amp;week_start=' . htmlspecialchars($weekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$auditFundPageTitle = 'Главная — расписание — Аудиторный фонд';
$auditFundNav = 'schedule';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Расписание</h1>
      <?php if ($logoutErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($logoutErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($maintSaved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Окно обслуживания добавлено.</p>
      <?php endif; ?>
      <?php if ($schedMoved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Запись расписания перенесена.</p>
      <?php endif; ?>
      <?php if ($maintUpdated): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Окно обслуживания обновлено.</p>
      <?php endif; ?>
      <?php if ($maintDeleted): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Окно обслуживания удалено.</p>
      <?php endif; ?>
      <?php if ($maintErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($maintErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($maintUpdErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($maintUpdErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($maintDelErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($maintDelErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($schedMoveErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($schedMoveErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($schedDeleted): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Запись снята с расписания.</p>
      <?php endif; ?>
      <?php if ($schedDelErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($schedDelErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($requestSaved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Заявка создана и отправлена на рассмотрение.</p>
      <?php endif; ?>
      <?php if ($requestErrText !== '' && $requestModalRestore === null): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($requestErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php if ($requestErrHints !== ''): ?>
        <p class="form__hint app-flash animate-on-load animate-on-load--d1" role="status"><?php echo htmlspecialchars($requestErrHints, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <section class="panel animate-on-load animate-on-load--d2" aria-labelledby="schedule-panel-title">
        <div class="panel__head panel__head--stacked">
          <h2 class="panel__title" id="schedule-panel-title">Период <?php echo htmlspecialchars($rangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
          <div class="panel__tools">
            <label class="visually-hidden" for="schedule-filter-date">Фильтр по дате</label>
            <input class="form__input panel__search" type="date" id="schedule-filter-date">
            <label class="visually-hidden" for="schedule-filter-room">Фильтр по аудитории</label>
            <input class="form__input panel__search" type="search" id="schedule-filter-room" placeholder="Аудитория или корпус…" autocomplete="off">
            <div class="view-toggle" role="tablist" aria-label="Вид расписания">
              <a class="view-toggle__btn<?php echo $pageView === 'week' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $pageView === 'week' ? 'true' : 'false'; ?>" href="schedule.php?week_start=<?php echo htmlspecialchars($weekTabWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Неделя</a>
              <a class="view-toggle__btn<?php echo $pageView === 'month' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $pageView === 'month' ? 'true' : 'false'; ?>" href="schedule.php?view=month&amp;month=<?php echo htmlspecialchars($monthTabYm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $monthUrlWeekQs; ?>">Месяц</a>
              <button class="view-toggle__btn" type="button" role="tab" aria-selected="false" data-view="table">Таблица</button>
            </div>
            <?php if ($pageView === 'month'): ?>
            <div class="week-nav" aria-label="Переключение месяца">
              <a class="btn btn--ghost week-nav__btn" href="schedule.php?view=month&amp;month=<?php echo htmlspecialchars($prevMonthYm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $monthUrlWeekQs; ?>" aria-label="Предыдущий месяц">←</a>
              <span class="week-nav__label"><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
              <a class="btn btn--ghost week-nav__btn" href="schedule.php?view=month&amp;month=<?php echo htmlspecialchars($nextMonthYm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $monthUrlWeekQs; ?>" aria-label="Следующий месяц">→</a>
            </div>
            <a class="btn btn--ghost" href="schedule.php?view=month&amp;month=<?php echo htmlspecialchars(date('Y-m'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>&amp;week_start=<?php echo htmlspecialchars($currentWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Текущий месяц</a>
            <?php else: ?>
            <div class="week-nav" aria-label="Переключение недели">
              <a class="btn btn--ghost week-nav__btn" href="schedule.php?week_start=<?php echo htmlspecialchars($prevWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-label="Предыдущая неделя">←</a>
              <span class="week-nav__label"><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
              <a class="btn btn--ghost week-nav__btn" href="schedule.php?week_start=<?php echo htmlspecialchars($nextWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-label="Следующая неделя">→</a>
            </div>
            <a class="btn btn--ghost" href="schedule.php?week_start=<?php echo htmlspecialchars($currentWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Текущая неделя</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (count($legendMap) > 0): ?>
        <div class="schedule-legend" aria-label="Легенда цветов аудиторий">
          <span class="schedule-legend__item">
            <span class="schedule-legend__dot schedule-legend__dot--maint" aria-hidden="true"></span>
            <span>Окно обслуживания</span>
          </span>
          <?php foreach ($legendMap as $legend): ?>
          <span class="schedule-legend__item">
            <span class="schedule-legend__dot" style="background-color: <?php echo htmlspecialchars((string) $legend['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;"></span>
            <span><?php echo htmlspecialchars((string) $legend['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="schedule-view is-hidden" data-schedule-view="table">
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
              <?php if ($scheduleError !== null): ?>
              <tr>
                <td colspan="4" class="data-table__message"><?php echo htmlspecialchars($scheduleError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif (count($scheduleRows) === 0): ?>
              <tr>
                <td colspan="4" class="data-table__message">В расписании пока нет записей.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($scheduleRows as $row): ?>
                <tr data-schedule-row data-date="<?php echo htmlspecialchars((string) $row['entry_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-room-text="<?php echo htmlspecialchars(mb_strtolower((string) $row['building_name'] . ' ' . (string) $row['room_number'], 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                  <td><?php echo htmlspecialchars(audit_fund_schedule_day_label((string) $row['entry_date']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(substr((string) $row['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $row['time_end']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['building_name'] . ', ' . (string) $row['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($row['comment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        </div>

        <div class="schedule-view<?php echo $pageView === 'month' ? ' is-hidden' : ''; ?>" data-schedule-view="calendar" aria-label="Календарь недели">
          <div class="calendar">
            <div class="calendar__grid">
                <?php foreach ($calendarByDate as $colDate => $events): ?>
                <?php
                  $dayLabel = audit_fund_schedule_day_label((string) $colDate);
                  $parts = explode(', ', $dayLabel, 2);
                  $dayShort = $parts[0] ?? $dayLabel;
                  $dayDate = $parts[1] ?? '';
                ?>
                <section class="calendar__day-stack" aria-label="<?php echo htmlspecialchars($dayLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                  <header class="calendar__day">
                    <div class="calendar__day-title"><?php echo htmlspecialchars($dayShort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                    <div class="calendar__day-date"><?php echo htmlspecialchars($dayDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                  </header>
                <div class="calendar__col" data-calendar-col data-col-date="<?php echo htmlspecialchars((string) $colDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                  <?php if (count($events) === 0): ?>
                    <article class="event" data-calendar-empty>
                      <p class="event__time">—</p>
                      <p class="event__title">Нет занятий</p>
                      <p class="event__meta">Свободный день</p>
                    </article>
                  <?php else: ?>
                    <?php foreach ($events as $event): ?>
                    <?php
                      $isMaintenance = isset($event['entry_kind']) && (string) $event['entry_kind'] === 'maintenance';
                      $eventColor = $isMaintenance ? '#6b7280' : (string) ($event['color_hex'] ?? '#3B82F6');
                      $eventTextColor = audit_fund_schedule_text_color($eventColor);
                      $entryKind = isset($event['entry_kind']) ? (string) $event['entry_kind'] : 'schedule';
                      $entryKindLabel = $entryKind === 'maintenance'
                        ? 'Обслуживание'
                        : ($entryKind === 'approved_request' ? 'Одобренная заявка' : 'Расписание');
                    ?>
                    <?php
                      $eventRequestId = isset($event['request_id']) ? (int) $event['request_id'] : 0;
                      $canDragSlot = !empty($isStaff) && ($entryKind === 'maintenance' || $entryKind === 'schedule' || $entryKind === 'approved_request');
                      $eventTimeEndDisp = audit_fund_request_time_end_display_hhmm((string) $event['time_end']);
                      $eventTimeRangeLabel = substr((string) $event['time_start'], 0, 5) . '–' . $eventTimeEndDisp;
                    ?>
                    <article class="event event--interactive" <?php echo $canDragSlot ? 'draggable="true"' : ''; ?> style="background: <?php echo htmlspecialchars($eventColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($eventTextColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; border-color: <?php echo htmlspecialchars($eventColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;" data-calendar-event data-date="<?php echo htmlspecialchars((string) $event['entry_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-room-text="<?php echo htmlspecialchars(mb_strtolower((string) $event['building_name'] . ' ' . (string) $event['room_number'], 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-id="<?php echo isset($event['id']) ? (int) $event['id'] : 0; ?>" data-request-id="<?php echo $eventRequestId; ?>" data-entry-room-id="<?php echo isset($event['room_id']) ? (int) $event['room_id'] : 0; ?>" data-entry-kind="<?php echo htmlspecialchars($entryKind, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-kind-label="<?php echo htmlspecialchars($entryKindLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-building="<?php echo htmlspecialchars((string) $event['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-room="<?php echo htmlspecialchars((string) $event['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-time="<?php echo htmlspecialchars($eventTimeRangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-time-start="<?php echo htmlspecialchars(substr((string) $event['time_start'], 0, 5), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-time-end="<?php echo htmlspecialchars($eventTimeEndDisp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-comment="<?php echo htmlspecialchars((string) ($event['comment'] ?? 'Без комментария'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                      <p class="event__time"><?php echo htmlspecialchars($eventTimeRangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                      <p class="event__title" title="<?php echo htmlspecialchars((string) ($event['comment'] ?? 'Без комментария'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($event['comment'] ?? 'Без комментария'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                      <p class="event__meta" title="<?php echo htmlspecialchars((string) $event['building_name'] . ', ' . (string) $event['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $event['building_name'] . ', ' . (string) $event['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    </article>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <?php
                    $colDateAllowsAdd = strtotime((string) $colDate) !== false && (string) $colDate >= $scheduleRequestTodayYmd;
                  ?>
                  <button
                    type="button"
                    class="calendar__col-add"
                    <?php if ($colDateAllowsAdd): ?>
                    data-modal-open="request-add"
                    data-col-date="<?php echo htmlspecialchars((string) $colDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    aria-label="Добавить заявку на этот день"
                    <?php else: ?>
                    disabled
                    aria-label="Добавление заявки на прошедшую дату недоступно"
                    <?php endif; ?>
                  >
                    <span class="calendar__col-add__icon" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24" focusable="false">
                        <path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </span>
                    <span class="calendar__col-add__text">Добавить запись</span>
                  </button>
                </div>
                </section>
                <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="schedule-view<?php echo $pageView === 'week' ? ' is-hidden' : ''; ?>" data-schedule-view="month" aria-label="Календарь месяца">
          <div class="calendar-month">
            <div class="calendar-month__weekdays" aria-hidden="true">
              <span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Вс</span>
            </div>
            <div class="calendar-month__grid">
              <?php foreach ($monthGridCells as $cell): ?>
              <?php
                $colDateM = (string) $cell['date'];
                $eventsM = $cell['events'];
                $inMonthM = !empty($cell['in_month']);
                $dayNumM = $colDateM !== '' && strtotime($colDateM) !== false ? (int) date('j', strtotime($colDateM)) : 0;
              ?>
              <div class="calendar-month__cell<?php echo $inMonthM ? '' : ' calendar-month__cell--outside'; ?>">
                <div class="calendar-month__cell-inner" data-calendar-col data-col-date="<?php echo htmlspecialchars($colDateM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                  <div class="calendar-month__day-num"><?php echo (int) $dayNumM; ?></div>
                  <div class="calendar-month__events">
                    <?php if (count($eventsM) === 0): ?>
                      <p class="calendar-month__empty" data-calendar-empty>Нет занятий</p>
                    <?php else: ?>
                      <?php foreach ($eventsM as $event): ?>
                      <?php
                        $isMaintenanceM = isset($event['entry_kind']) && (string) $event['entry_kind'] === 'maintenance';
                        $eventColorM = $isMaintenanceM ? '#6b7280' : (string) ($event['color_hex'] ?? '#3B82F6');
                        $eventTextColorM = audit_fund_schedule_text_color($eventColorM);
                        $entryKindM = isset($event['entry_kind']) ? (string) $event['entry_kind'] : 'schedule';
                        $entryKindLabelM = $entryKindM === 'maintenance'
                          ? 'Обслуживание'
                          : ($entryKindM === 'approved_request' ? 'Одобренная заявка' : 'Расписание');
                        $eventRequestIdM = isset($event['request_id']) ? (int) $event['request_id'] : 0;
                        $eventTimeEndDispM = audit_fund_request_time_end_display_hhmm((string) $event['time_end']);
                        $eventTimeRangeLabelM = substr((string) $event['time_start'], 0, 5) . '–' . $eventTimeEndDispM;
                      ?>
                      <article class="event event--month event--interactive" style="background: <?php echo htmlspecialchars($eventColorM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($eventTextColorM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; border-color: <?php echo htmlspecialchars($eventColorM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;" data-calendar-event data-date="<?php echo htmlspecialchars((string) $event['entry_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-room-text="<?php echo htmlspecialchars(mb_strtolower((string) $event['building_name'] . ' ' . (string) $event['room_number'], 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-id="<?php echo isset($event['id']) ? (int) $event['id'] : 0; ?>" data-request-id="<?php echo $eventRequestIdM; ?>" data-entry-room-id="<?php echo isset($event['room_id']) ? (int) $event['room_id'] : 0; ?>" data-entry-kind="<?php echo htmlspecialchars($entryKindM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-kind-label="<?php echo htmlspecialchars($entryKindLabelM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-building="<?php echo htmlspecialchars((string) $event['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-room="<?php echo htmlspecialchars((string) $event['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-time="<?php echo htmlspecialchars($eventTimeRangeLabelM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-time-start="<?php echo htmlspecialchars(substr((string) $event['time_start'], 0, 5), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-time-end="<?php echo htmlspecialchars($eventTimeEndDispM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-entry-comment="<?php echo htmlspecialchars((string) ($event['comment'] ?? 'Без комментария'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <span class="event--month__time"><?php echo htmlspecialchars($eventTimeRangeLabelM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        <span class="event--month__meta"><?php echo htmlspecialchars((string) $event['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                      </article>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                  <?php
                    $colDateMAllowsAdd = strtotime($colDateM) !== false && $colDateM >= $scheduleRequestTodayYmd;
                  ?>
                  <button
                    type="button"
                    class="calendar__col-add calendar__col-add--month"
                    <?php if ($colDateMAllowsAdd): ?>
                    data-modal-open="request-add"
                    data-col-date="<?php echo htmlspecialchars($colDateM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    aria-label="Добавить заявку на этот день"
                    <?php else: ?>
                    disabled
                    aria-label="Добавление заявки на прошедшую дату недоступно"
                    <?php endif; ?>
                  >
                    <span class="calendar__col-add__icon" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24" focusable="false">
                        <path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </span>
                    <span class="calendar__col-add__text">Добавить запись</span>
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>
      <div class="modal is-hidden" id="modal-schedule-entry" role="dialog" aria-modal="true" aria-labelledby="modal-schedule-entry-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-schedule-entry-title">Детали записи</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <div class="form form--compact">
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label">Тип</label>
                <div class="form__input" id="entry-detail-kind">—</div>
              </div>
              <div class="form__field">
                <label class="form__label">Дата</label>
                <div class="form__input" id="entry-detail-date">—</div>
              </div>
              <div class="form__field">
                <label class="form__label">Время</label>
                <div class="form__input" id="entry-detail-time">—</div>
              </div>
              <div class="form__field">
                <label class="form__label">Аудитория</label>
                <div class="form__input" id="entry-detail-room">—</div>
              </div>
            </div>
            <div class="form__field">
              <label class="form__label">Комментарий</label>
              <div class="form__input" id="entry-detail-comment">—</div>
            </div>
            <?php if ($isStaff): ?>
            <div class="entry-detail-move-section is-hidden" id="entry-detail-move-section">
              <form id="entry-detail-move-form" class="entry-detail-move-form" method="post" action="schedule_entry_move.php" novalidate>
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="week_start" id="entry-detail-move-week-start" value="">
                <input type="hidden" name="entry_id" id="entry-detail-move-entry-id" value="">
                <input type="hidden" name="request_id" id="entry-detail-move-request-id" value="">
                <div class="form__field">
                  <label class="form__label" for="entry-detail-move-new-room">Аудитория</label>
                  <select class="form__input" id="entry-detail-move-new-room" name="new_room_id" required autocomplete="off" <?php echo count($relocateRooms) === 0 ? ' disabled' : ''; ?>>
                    <option value="">Выберите…</option>
                    <?php foreach ($relocateRooms as $rr): ?>
                    <option value="<?php echo (int) $rr['id']; ?>"><?php echo htmlspecialchars((string) $rr['room_number'] . ' — ' . (string) $rr['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form__field">
                  <label class="form__label" for="entry-detail-move-new-date">Дата</label>
                  <input class="form__input" type="date" id="entry-detail-move-new-date" name="new_date" required min="<?php echo $scheduleMoveDateMin; ?>">
                </div>
                <div class="form-grid">
                  <div class="form__field">
                    <label class="form__label" for="entry-detail-move-time-start">Начало</label>
                    <input class="form__input" type="time" id="entry-detail-move-time-start" name="relocate_time_start" required step="60">
                  </div>
                  <div class="form__field">
                    <label class="form__label" for="entry-detail-move-time-end">Окончание</label>
                    <input class="form__input" type="time" id="entry-detail-move-time-end" name="relocate_time_end" required step="60" value="10:00">
                  </div>
                </div>
                <p class="form__hint">Можно изменить дату, аудиторию и интервал времени (в том числе только время на той же дате). После переноса откроется неделя выбранного дня. Для одобренной заявки в списке заявок появится пометка о корректировке.</p>
                <button class="btn btn--primary" type="submit">Перенести</button>
              </form>
            </div>
            <?php endif; ?>
            <div class="modal__actions">
              <?php if ($isStaff): ?>
              <button class="btn btn--ghost" type="button" id="entry-detail-edit-maint" data-modal-open="edit-maintenance">Изменить обслуживание</button>
              <form action="maintenance_delete.php" method="post" id="entry-detail-delete-form" onsubmit="return confirm('Удалить окно обслуживания?');">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
                <input type="hidden" name="window_id" id="entry-detail-delete-id" value="">
                <button class="btn btn--danger-ghost" type="submit">Удалить обслуживание</button>
              </form>
              <form action="schedule_slot_delete.php" method="post" id="entry-detail-slot-delete-form" class="is-hidden" onsubmit="return confirm('Удалить запись из расписания? Для одобренной заявки статус будет изменён на «Отклонено».');">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
                <input type="hidden" name="entry_id" id="entry-detail-slot-entry-id" value="">
                <input type="hidden" name="request_id" id="entry-detail-slot-request-id" value="">
                <button class="btn btn--danger-ghost" type="submit">Удалить из расписания</button>
              </form>
              <?php endif; ?>
              <button class="btn btn--ghost" type="button" data-modal-close>Закрыть</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal is-hidden" id="modal-request-add" role="dialog" aria-modal="true" aria-labelledby="modal-request-add-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-request-add-title">Новая заявка</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" action="request_add.php" method="post">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="return_to" value="schedule">
            <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="modal-req-room">Аудитория <span class="form__required-mark" aria-hidden="true">*</span></label>
                <select class="form__input" id="modal-req-room" name="room_id" required autocomplete="off" title="Выберите аудиторию из списка" <?php echo $relocateRoomsError !== null ? ' disabled' : ''; ?>>
                  <option value="">Выберите…</option>
                  <?php foreach ($relocateRooms as $room): ?>
                  <option value="<?php echo (int) $room['id']; ?>"><?php echo htmlspecialchars((string) $room['room_number'] . ' — ' . (string) $room['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($relocateRoomsError !== null): ?>
                  <p class="form__error"><?php echo htmlspecialchars($relocateRoomsError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php endif; ?>
              </div>
              <div class="form__field">
                <label class="form__label" for="modal-req-booking-date">Дата занятия <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="date" id="modal-req-booking-date" name="booking_date" required min="<?php echo htmlspecialchars($scheduleRequestTodayYmd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" title="Укажите дату">
              </div>
              <div class="form__field">
                <label class="form__label" for="modal-req-time-start">Время начала <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="time" id="modal-req-time-start" name="time_start" required value="09:00" title="Укажите время начала">
              </div>
              <div class="form__field">
                <label class="form__label" for="modal-req-time-end">Время окончания <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="time" id="modal-req-time-end" name="time_end" required value="10:00" title="Укажите время окончания">
              </div>
              <div class="form__field">
                <label class="form__label" for="modal-req-applicant">Заявитель <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="text" id="modal-req-applicant" name="applicant_display" maxlength="255" placeholder="Например: Кафедра информатики" required autocomplete="organization" title="Укажите заявителя">
              </div>
            </div>
            <div class="form__field">
              <label class="form__label" for="modal-req-comment">Комментарий</label>
              <input class="form__input" type="text" id="modal-req-comment" name="comment" maxlength="1000" placeholder="Цель, количество человек…">
            </div>
            <p class="form__error is-hidden" id="modal-req-inline-error" role="alert"></p>
            <p class="form__hint is-hidden" id="modal-req-inline-hints" role="status"></p>
            <p class="form__hint is-hidden" id="modal-req-precheck-hint" role="status"></p>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit" <?php echo $relocateRoomsError !== null ? ' disabled' : ''; ?>>Отправить заявку</button>
            </div>
          </form>
        </div>
      </div>
      <?php if ($isStaff): ?>
      <form id="form-maintenance-dnd" action="maintenance_update.php" method="post" class="is-hidden">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
        <input type="hidden" name="window_id" id="dnd-window-id" value="">
        <input type="hidden" name="room_id" id="dnd-room-id" value="">
        <input type="hidden" name="maint_date" id="dnd-maint-date" value="">
        <input type="hidden" name="time_start" id="dnd-time-start" value="">
        <input type="hidden" name="time_end" id="dnd-time-end" value="">
        <input type="hidden" name="reason" id="dnd-reason" value="">
      </form>
      <form id="form-schedule-dnd" action="schedule_entry_move.php" method="post" class="is-hidden">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <input type="hidden" name="week_start" id="dnd-schedule-week-start" value="<?php echo $scheduleWeekStartValue; ?>">
        <input type="hidden" name="entry_id" id="dnd-schedule-entry-id" value="">
        <input type="hidden" name="request_id" id="dnd-schedule-request-id" value="">
        <input type="hidden" name="new_room_id" id="dnd-schedule-new-room-id" value="">
        <input type="hidden" name="new_date" id="dnd-schedule-new-date" value="">
        <input type="hidden" name="relocate_time_start" id="dnd-schedule-relocate-time-start" value="">
        <input type="hidden" name="relocate_time_end" id="dnd-schedule-relocate-time-end" value="">
      </form>
      <div class="modal is-hidden" id="modal-edit-maintenance" role="dialog" aria-modal="true" aria-labelledby="modal-edit-maintenance-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-maintenance-title">Изменить окно обслуживания</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" id="form-edit-maintenance" action="maintenance_update.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
            <input type="hidden" id="edit-maint-window-id" name="window_id" value="">
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="edit-maint-room">Аудитория</label>
                <select class="form__input" id="edit-maint-room" name="room_id" required>
                  <option value="">Выберите…</option>
                  <?php foreach ($maintenanceRooms as $room): ?>
                  <option value="<?php echo (int) $room['id']; ?>"><?php echo htmlspecialchars((string) $room['room_number'] . ' — ' . (string) $room['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-maint-date">Дата</label>
                <input class="form__input" type="date" id="edit-maint-date" name="maint_date" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-maint-time-start">Время с</label>
                <input class="form__input" type="time" id="edit-maint-time-start" name="time_start" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-maint-time-end">Время до</label>
                <input class="form__input" type="time" id="edit-maint-time-end" name="time_end" required value="10:00">
              </div>
            </div>
            <div class="form__field">
              <label class="form__label" for="edit-maint-reason">Причина</label>
              <input class="form__input" type="text" id="edit-maint-reason" name="reason" maxlength="1000">
            </div>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit">Сохранить</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($isStaff): ?>
      <section class="panel panel--offset-top animate-on-load animate-on-load--d3" id="schedule-maintenance-section" aria-labelledby="maintenance-panel-title">
        <div class="panel__head">
          <h2 class="panel__title" id="maintenance-panel-title">Окна обслуживания</h2>
        </div>
        <?php if ($maintenanceError !== null): ?>
          <p class="form__error"><?php echo htmlspecialchars($maintenanceError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form class="form form--compact" action="maintenance_add.php" method="post" novalidate>
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
          <div class="form-grid">
            <div class="form__field">
              <label class="form__label" for="maint-room">Аудитория</label>
              <select class="form__input" id="maint-room" name="room_id" required>
                <option value="">Выберите…</option>
                <?php foreach ($maintenanceRooms as $room): ?>
                <option value="<?php echo (int) $room['id']; ?>"><?php echo htmlspecialchars((string) $room['room_number'] . ' — ' . (string) $room['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form__field">
              <label class="form__label" for="maint-date">Дата</label>
              <input class="form__input" type="date" id="maint-date" name="maint_date" required>
            </div>
            <div class="form__field">
              <label class="form__label" for="maint-time-start">Время с</label>
              <input class="form__input" type="time" id="maint-time-start" name="time_start" required>
            </div>
            <div class="form__field">
              <label class="form__label" for="maint-time-end">Время до</label>
              <input class="form__input" type="time" id="maint-time-end" name="time_end" required value="10:00">
            </div>
          </div>
          <p class="form__error is-hidden" id="maint-inline-error" role="alert"></p>
          <div class="form__field">
            <label class="form__label" for="maint-reason">Причина</label>
            <input class="form__input" type="text" id="maint-reason" name="reason" maxlength="1000" placeholder="Например: профилактика оборудования">
          </div>
          <button class="btn btn--primary" type="submit">Добавить окно обслуживания</button>
        </form>
        <div class="table-wrap table-wrap--offset-top">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">День</th>
                <th scope="col">Время</th>
                <th scope="col">Аудитория</th>
                <th scope="col">Причина</th>
                <th scope="col">Действия</th>
              </tr>
            </thead>
            <tbody>
            <?php if (count($maintenanceRows) === 0): ?>
              <tr>
                <td colspan="5" class="data-table__message">На выбранную неделю окон обслуживания нет.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($maintenanceRows as $mw): ?>
              <tr>
                <td><?php echo htmlspecialchars(audit_fund_schedule_day_label((string) $mw['maint_date']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(substr((string) $mw['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $mw['time_end']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $mw['building_name'] . ', ' . (string) $mw['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($mw['reason'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td>
                  <form action="maintenance_delete.php" method="post" onsubmit="return confirm('Удалить окно обслуживания?');">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="week_start" value="<?php echo $scheduleWeekStartValue; ?>">
                    <input type="hidden" name="window_id" value="<?php echo (int) $mw['id']; ?>">
                    <button class="btn-link btn-link--icon" type="submit" aria-label="Удалить окно обслуживания" title="Удалить">
                      <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z" fill="currentColor"></path>
                      </svg>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
  <script>
    <?php if ($requestModalRestore !== null): ?>
    window.__AUDIT_FUND_REQUEST_MODAL_RESTORE__ = <?php echo json_encode($requestModalRestore, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    <?php endif; ?>
    document.addEventListener('DOMContentLoaded', function () {
      var dateInput = document.getElementById('schedule-filter-date');
      var roomInput = document.getElementById('schedule-filter-room');
      var tableRows = document.querySelectorAll('tr[data-schedule-row]');
      var events = document.querySelectorAll('[data-calendar-event]');
      var cols = document.querySelectorAll('[data-calendar-col]');
      var detailsModal = document.getElementById('modal-schedule-entry');
      var detailKind = document.getElementById('entry-detail-kind');
      var detailDate = document.getElementById('entry-detail-date');
      var detailTime = document.getElementById('entry-detail-time');
      var detailRoom = document.getElementById('entry-detail-room');
      var detailComment = document.getElementById('entry-detail-comment');
      var detailDeleteForm = document.getElementById('entry-detail-delete-form');
      var detailDeleteId = document.getElementById('entry-detail-delete-id');
      var detailSlotDeleteForm = document.getElementById('entry-detail-slot-delete-form');
      var detailSlotEntryId = document.getElementById('entry-detail-slot-entry-id');
      var detailSlotRequestId = document.getElementById('entry-detail-slot-request-id');
      var entryDetailMoveSection = document.getElementById('entry-detail-move-section');
      var entryDetailMoveForm = document.getElementById('entry-detail-move-form');
      var entryDetailMoveEntryId = document.getElementById('entry-detail-move-entry-id');
      var entryDetailMoveRequestId = document.getElementById('entry-detail-move-request-id');
      var entryDetailMoveNewRoom = document.getElementById('entry-detail-move-new-room');
      var entryDetailMoveNewDate = document.getElementById('entry-detail-move-new-date');
      var entryDetailMoveTimeStart = document.getElementById('entry-detail-move-time-start');
      var entryDetailMoveTimeEnd = document.getElementById('entry-detail-move-time-end');
      var entryDetailMoveWeekStart = document.getElementById('entry-detail-move-week-start');
      var detailEditMaintBtn = document.getElementById('entry-detail-edit-maint');
      var dndForm = document.getElementById('form-maintenance-dnd');
      var dndWindowId = document.getElementById('dnd-window-id');
      var dndRoomId = document.getElementById('dnd-room-id');
      var dndMaintDate = document.getElementById('dnd-maint-date');
      var dndTimeStart = document.getElementById('dnd-time-start');
      var dndTimeEnd = document.getElementById('dnd-time-end');
      var dndReason = document.getElementById('dnd-reason');
      var dndScheduleForm = document.getElementById('form-schedule-dnd');
      var dndScheduleEntryId = document.getElementById('dnd-schedule-entry-id');
      var dndScheduleRequestId = document.getElementById('dnd-schedule-request-id');
      var dndScheduleNewRoomId = document.getElementById('dnd-schedule-new-room-id');
      var dndScheduleWeekStart = document.getElementById('dnd-schedule-week-start');
      var dndScheduleNewDate = document.getElementById('dnd-schedule-new-date');
      var dndScheduleRelocateTimeStart = document.getElementById('dnd-schedule-relocate-time-start');
      var dndScheduleRelocateTimeEnd = document.getElementById('dnd-schedule-relocate-time-end');
      var editMaintWindowId = document.getElementById('edit-maint-window-id');
      var editMaintRoom = document.getElementById('edit-maint-room');
      var editMaintDate = document.getElementById('edit-maint-date');
      var editMaintTimeStart = document.getElementById('edit-maint-time-start');
      var editMaintTimeEnd = document.getElementById('edit-maint-time-end');
      var editMaintReason = document.getElementById('edit-maint-reason');
      var scheduleTodayYmd = <?php echo json_encode($scheduleRequestTodayYmd, JSON_THROW_ON_ERROR); ?>;
      var modalRequestAdd = document.getElementById('modal-request-add');
      var modalReqForm = modalRequestAdd ? modalRequestAdd.querySelector('form') : null;
      var modalReqBookingDate = document.getElementById('modal-req-booking-date');
      var modalReqInlineErr = document.getElementById('modal-req-inline-error');
      var modalReqInlineHints = document.getElementById('modal-req-inline-hints');
      var modalReqPrecheck = document.getElementById('modal-req-precheck-hint');
      var modalReqRoom = document.getElementById('modal-req-room');
      var modalReqTimeStart = document.getElementById('modal-req-time-start');
      var modalReqTimeEnd = document.getElementById('modal-req-time-end');
      var modalReqApplicant = document.getElementById('modal-req-applicant');
      var modalReqComment = document.getElementById('modal-req-comment');
      var modalReqPrecheckTimer = null;

      function clearModalReqServerError() {
        if (modalReqInlineErr) {
          modalReqInlineErr.textContent = '';
          modalReqInlineErr.classList.add('is-hidden');
        }
        if (modalReqInlineHints) {
          modalReqInlineHints.textContent = '';
          modalReqInlineHints.classList.add('is-hidden');
        }
      }

      function clearModalReqPrecheck() {
        if (modalReqPrecheck) {
          modalReqPrecheck.textContent = '';
          modalReqPrecheck.classList.add('is-hidden');
        }
      }

      function showModalReqServerError(msg, hints) {
        if (modalReqInlineErr) {
          modalReqInlineErr.textContent = msg || '';
          modalReqInlineErr.classList.toggle('is-hidden', !msg);
        }
        if (modalReqInlineHints) {
          modalReqInlineHints.textContent = hints || '';
          modalReqInlineHints.classList.toggle('is-hidden', !hints);
        }
      }

      document.addEventListener('click', function (e) {
        var opener = e.target.closest('button.calendar__col-add[data-modal-open="request-add"]');
        if (!opener || !modalReqBookingDate) {
          return;
        }
        clearModalReqServerError();
        clearModalReqPrecheck();
        var ymd = opener.getAttribute('data-col-date') || '';
        if (ymd !== '' && ymd >= scheduleTodayYmd) {
          modalReqBookingDate.value = ymd;
        } else {
          modalReqBookingDate.value = scheduleTodayYmd;
        }
      }, true);

      if (window.__AUDIT_FUND_REQUEST_MODAL_RESTORE__ && modalReqForm) {
        var rr = window.__AUDIT_FUND_REQUEST_MODAL_RESTORE__;
        if (modalReqRoom && rr.roomId) {
          modalReqRoom.value = String(rr.roomId);
        }
        if (modalReqBookingDate && rr.bookingDate) {
          modalReqBookingDate.value = rr.bookingDate;
        }
        if (modalReqTimeStart && rr.timeStart) {
          modalReqTimeStart.value = rr.timeStart.length >= 5 ? rr.timeStart.slice(0, 5) : rr.timeStart;
        }
        if (modalReqTimeEnd && rr.timeEnd) {
          modalReqTimeEnd.value = rr.timeEnd.length >= 5 ? rr.timeEnd.slice(0, 5) : rr.timeEnd;
        }
        if (modalReqApplicant && rr.applicant !== undefined) {
          modalReqApplicant.value = rr.applicant;
        }
        if (modalReqComment && rr.comment !== undefined) {
          modalReqComment.value = rr.comment;
        }
        showModalReqServerError(rr.error || '', rr.hints || '');
        if (window.auditFundOpenModal) {
          window.auditFundOpenModal('modal-request-add', null);
        }
      }

      if (modalReqForm) {
        modalReqForm.addEventListener('submit', function (ev) {
          if (modalReqForm.getAttribute('data-audit-skip-slot-check') === '1') {
            modalReqForm.removeAttribute('data-audit-skip-slot-check');
            return;
          }
          ev.preventDefault();
          clearModalReqPrecheck();
          var fd = new FormData(modalReqForm);
          fetch('request_slot_check.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
          })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              if (data && data.ok) {
                modalReqForm.setAttribute('data-audit-skip-slot-check', '1');
                modalReqForm.submit();
              } else {
                showModalReqServerError(
                  data && data.message ? data.message : 'Не удалось проверить слот.',
                  data && data.hints ? data.hints : ''
                );
              }
            })
            .catch(function () {
              showModalReqServerError('Не удалось проверить занятость. Проверьте сеть и повторите отправку.', '');
            });
        });

        function bindClearServerOnEdit(el) {
          if (!el) {
            return;
          }
          el.addEventListener('change', clearModalReqServerError);
          el.addEventListener('input', clearModalReqServerError);
        }
        bindClearServerOnEdit(modalReqRoom);
        bindClearServerOnEdit(modalReqBookingDate);
        bindClearServerOnEdit(modalReqTimeStart);
        bindClearServerOnEdit(modalReqTimeEnd);
        bindClearServerOnEdit(modalReqApplicant);
        bindClearServerOnEdit(modalReqComment);

        function runModalPrecheckDebounced() {
          clearModalReqPrecheck();
          clearTimeout(modalReqPrecheckTimer);
          modalReqPrecheckTimer = setTimeout(runModalPrecheckNow, 450);
        }

        function runModalPrecheckNow() {
          if (!modalReqRoom || !modalReqBookingDate || !modalReqTimeStart || !modalReqTimeEnd || !modalReqPrecheck) {
            return;
          }
          var roomId = modalReqRoom.value;
          var bd = modalReqBookingDate.value;
          if (!roomId || !bd || !modalReqTimeStart.value || !modalReqTimeEnd.value) {
            return;
          }
          var csrfInput = modalReqForm.querySelector('input[name="_csrf"]');
          if (!csrfInput) {
            return;
          }
          var fd = new FormData();
          fd.set('_csrf', csrfInput.value);
          fd.set('room_id', roomId);
          fd.set('booking_date', bd);
          fd.set('time_start', modalReqTimeStart.value);
          fd.set('time_end', modalReqTimeEnd.value);
          fetch('request_slot_check.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
          })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              if (!data || data.ok) {
                return;
              }
              var msg = (data.message || 'Интервал недоступен.') + (data.hints ? ' ' + data.hints : '');
              modalReqPrecheck.textContent = msg;
              modalReqPrecheck.classList.remove('is-hidden');
            })
            .catch(function () {});
        }

        [modalReqRoom, modalReqBookingDate, modalReqTimeStart, modalReqTimeEnd].forEach(function (el) {
          if (!el) {
            return;
          }
          el.addEventListener('change', runModalPrecheckDebounced);
          el.addEventListener('input', runModalPrecheckDebounced);
        });
      }

      function normalize(s) {
        return (s || '').toString().toLowerCase().trim();
      }

      function auditFundWeekStartMonday(ymd) {
        var p = ymd.split('-');
        if (p.length !== 3) {
          return ymd;
        }
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        var dow = d.getDay();
        var delta = dow === 0 ? -6 : 1 - dow;
        d.setDate(d.getDate() + delta);
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
      }

      function applyFilter() {
        var selectedDate = dateInput ? dateInput.value : '';
        var roomQuery = normalize(roomInput ? roomInput.value : '');

        tableRows.forEach(function (row) {
          var rowDate = row.getAttribute('data-date') || '';
          var rowRoom = normalize(row.getAttribute('data-room-text') || '');
          var dateOk = selectedDate === '' || rowDate === selectedDate;
          var roomOk = roomQuery === '' || rowRoom.indexOf(roomQuery) !== -1;
          row.style.display = (dateOk && roomOk) ? '' : 'none';
        });

        events.forEach(function (eventEl) {
          var evDate = eventEl.getAttribute('data-date') || '';
          var evRoom = normalize(eventEl.getAttribute('data-room-text') || '');
          var dateOk = selectedDate === '' || evDate === selectedDate;
          var roomOk = roomQuery === '' || evRoom.indexOf(roomQuery) !== -1;
          eventEl.style.display = (dateOk && roomOk) ? '' : 'none';
        });

        cols.forEach(function (col) {
          var visibleEvents = col.querySelectorAll('[data-calendar-event]');
          var emptyEvent = col.querySelector('[data-calendar-empty]');
          var addRecordBtn = col.querySelector('.calendar__col-add');
          var hasVisible = false;
          visibleEvents.forEach(function (ev) {
            if (ev.style.display !== 'none') {
              hasVisible = true;
            }
          });
          if (emptyEvent) {
            emptyEvent.style.display = (selectedDate === '' && roomQuery === '') ? '' : 'none';
          }
          var showAddOnly = addRecordBtn && selectedDate === '' && roomQuery === '';
          col.style.display = (hasVisible || (emptyEvent && emptyEvent.style.display !== 'none') || showAddOnly) ? '' : 'none';
        });
      }

      if (dateInput) {
        dateInput.addEventListener('change', applyFilter);
      }
      if (roomInput) {
        roomInput.addEventListener('input', applyFilter);
      }
      if (detailsModal && events.length) {
        function openDetailsModal(eventEl) {
          if (detailKind) { detailKind.textContent = eventEl.getAttribute('data-entry-kind-label') || '—'; }
          if (detailDate) { detailDate.textContent = eventEl.getAttribute('data-date') || '—'; }
          if (detailTime) { detailTime.textContent = eventEl.getAttribute('data-entry-time') || '—'; }
          if (detailRoom) {
            var building = eventEl.getAttribute('data-entry-building') || '';
            var room = eventEl.getAttribute('data-entry-room') || '';
            detailRoom.textContent = (building !== '' || room !== '') ? (building + ', ' + room).replace(/^,\s*/, '') : '—';
          }
          if (detailComment) { detailComment.textContent = eventEl.getAttribute('data-entry-comment') || '—'; }
          var kind = eventEl.getAttribute('data-entry-kind') || '';
          var isMaintenance = kind === 'maintenance';
          if (detailDeleteForm) {
            detailDeleteForm.style.display = isMaintenance ? '' : 'none';
          }
          if (detailDeleteId) {
            detailDeleteId.value = isMaintenance ? (eventEl.getAttribute('data-entry-id') || '') : '';
          }
          var showSlotDelete = kind === 'schedule' || kind === 'approved_request';
          if (detailSlotDeleteForm) {
            detailSlotDeleteForm.classList.toggle('is-hidden', !showSlotDelete);
          }
          if (detailSlotEntryId) {
            detailSlotEntryId.value = kind === 'schedule' ? (eventEl.getAttribute('data-entry-id') || '') : '';
          }
          if (detailSlotRequestId) {
            detailSlotRequestId.value = kind === 'approved_request' ? (eventEl.getAttribute('data-request-id') || '') : '';
          }
          var showMoveByDate = kind === 'schedule' || kind === 'approved_request';
          if (entryDetailMoveSection) {
            entryDetailMoveSection.classList.toggle('is-hidden', !showMoveByDate);
          }
          if (entryDetailMoveEntryId) {
            entryDetailMoveEntryId.value = kind === 'schedule' ? (eventEl.getAttribute('data-entry-id') || '') : '';
          }
          if (entryDetailMoveRequestId) {
            entryDetailMoveRequestId.value = kind === 'approved_request' ? (eventEl.getAttribute('data-request-id') || '') : '';
          }
          if (entryDetailMoveNewDate) {
            entryDetailMoveNewDate.value = eventEl.getAttribute('data-date') || '';
          }
          if (entryDetailMoveNewRoom) {
            var rid = eventEl.getAttribute('data-entry-room-id') || '';
            entryDetailMoveNewRoom.value = rid;
          }
          if (entryDetailMoveTimeStart) {
            entryDetailMoveTimeStart.value = eventEl.getAttribute('data-entry-time-start') || '';
          }
          if (entryDetailMoveTimeEnd) {
            entryDetailMoveTimeEnd.value = eventEl.getAttribute('data-entry-time-end') || '';
          }
          if (detailEditMaintBtn) {
            detailEditMaintBtn.style.display = isMaintenance ? '' : 'none';
            detailEditMaintBtn.dataset.sourceEventId = eventEl.getAttribute('data-entry-id') || '';
            detailEditMaintBtn.dataset.sourceDate = eventEl.getAttribute('data-date') || '';
            detailEditMaintBtn.dataset.sourceTimeStart = eventEl.getAttribute('data-entry-time-start') || '';
            detailEditMaintBtn.dataset.sourceTimeEnd = eventEl.getAttribute('data-entry-time-end') || '';
            detailEditMaintBtn.dataset.sourceComment = eventEl.getAttribute('data-entry-comment') || '';
            var roomLabel = (eventEl.getAttribute('data-entry-building') || '') + ', ' + (eventEl.getAttribute('data-entry-room') || '');
            detailEditMaintBtn.dataset.sourceRoomLabel = roomLabel;
            detailEditMaintBtn.dataset.sourceRoomId = eventEl.getAttribute('data-entry-room-id') || '';
          }
          detailsModal.classList.remove('is-hidden');
        }
        events.forEach(function (eventEl) {
          eventEl.addEventListener('click', function () {
            openDetailsModal(eventEl);
          });
        });
      }
      if (entryDetailMoveForm && entryDetailMoveNewDate && entryDetailMoveWeekStart) {
        entryDetailMoveForm.addEventListener('submit', function (e) {
          var nd = entryDetailMoveNewDate.value || '';
          if (nd === '') {
            return;
          }
          entryDetailMoveWeekStart.value = auditFundWeekStartMonday(nd);
          var ts = entryDetailMoveTimeStart ? (entryDetailMoveTimeStart.value || '') : '';
          var te = entryDetailMoveTimeEnd ? (entryDetailMoveTimeEnd.value || '') : '';
          var timePart = (ts !== '' && te !== '') ? (' ' + ts + '–' + te) : '';
          if (!window.confirm('Перенести запись на ' + nd + timePart + '?')) {
            e.preventDefault();
          }
        });
      }

      if (detailEditMaintBtn) {
        detailEditMaintBtn.addEventListener('click', function () {
          if (editMaintWindowId) { editMaintWindowId.value = detailEditMaintBtn.dataset.sourceEventId || ''; }
          if (editMaintDate) { editMaintDate.value = detailEditMaintBtn.dataset.sourceDate || ''; }
          if (editMaintTimeStart) { editMaintTimeStart.value = detailEditMaintBtn.dataset.sourceTimeStart || ''; }
          if (editMaintTimeEnd) { editMaintTimeEnd.value = detailEditMaintBtn.dataset.sourceTimeEnd || ''; }
          if (editMaintReason) { editMaintReason.value = detailEditMaintBtn.dataset.sourceComment || ''; }
          if (editMaintRoom) { editMaintRoom.value = detailEditMaintBtn.dataset.sourceRoomId || ''; }
        });
      }
      var draggedMaintenance = null;
      var draggableMaintenanceEvents = document.querySelectorAll('[data-calendar-event][data-entry-kind="maintenance"][draggable="true"]');
      if (draggableMaintenanceEvents.length && cols.length && dndForm) {
        var todayDate = new Date().toISOString().slice(0, 10);
        draggableMaintenanceEvents.forEach(function (eventEl) {
          eventEl.addEventListener('dragstart', function () {
            draggedMaintenance = eventEl;
            eventEl.classList.add('event--dragging');
          });
          eventEl.addEventListener('dragend', function () {
            eventEl.classList.remove('event--dragging');
            draggedMaintenance = null;
            cols.forEach(function (c) { c.classList.remove('calendar__col--drop'); });
          });
        });
        cols.forEach(function (col) {
          col.addEventListener('dragover', function (e) {
            if (!draggedMaintenance) { return; }
            e.preventDefault();
            col.classList.add('calendar__col--drop');
          });
          col.addEventListener('dragleave', function () {
            col.classList.remove('calendar__col--drop');
          });
          col.addEventListener('drop', function (e) {
            if (!draggedMaintenance) { return; }
            e.preventDefault();
            col.classList.remove('calendar__col--drop');
            var newDate = col.getAttribute('data-col-date') || '';
            var oldDate = draggedMaintenance.getAttribute('data-date') || '';
            if (newDate === '' || oldDate === '' || newDate === oldDate) {
              return;
            }
            if (newDate < todayDate) {
              window.alert('Нельзя переносить окно обслуживания в прошедшую дату.');
              return;
            }
            var roomLabel = ((draggedMaintenance.getAttribute('data-entry-building') || '') + ', ' + (draggedMaintenance.getAttribute('data-entry-room') || '')).replace(/^,\s*/, '');
            var timeRange = draggedMaintenance.getAttribute('data-entry-time') || '';
            if (!window.confirm('Перенести окно обслуживания ' + roomLabel + ' (' + timeRange + ') на ' + newDate + '?')) {
              return;
            }
            if (dndWindowId) { dndWindowId.value = draggedMaintenance.getAttribute('data-entry-id') || ''; }
            if (dndRoomId) { dndRoomId.value = draggedMaintenance.getAttribute('data-entry-room-id') || ''; }
            if (dndMaintDate) { dndMaintDate.value = newDate; }
            if (dndTimeStart) { dndTimeStart.value = draggedMaintenance.getAttribute('data-entry-time-start') || ''; }
            if (dndTimeEnd) { dndTimeEnd.value = draggedMaintenance.getAttribute('data-entry-time-end') || ''; }
            if (dndReason) { dndReason.value = draggedMaintenance.getAttribute('data-entry-comment') || ''; }
            dndForm.submit();
          });
        });
      }
      var draggedSchedule = null;
      var draggableSlotEvents = document.querySelectorAll('[data-calendar-event][draggable="true"]:not([data-entry-kind="maintenance"])');
      if (draggableSlotEvents.length && cols.length && dndScheduleForm) {
        var todayDateForSchedule = new Date().toISOString().slice(0, 10);
        draggableSlotEvents.forEach(function (eventEl) {
          eventEl.addEventListener('dragstart', function () {
            draggedSchedule = eventEl;
            eventEl.classList.add('event--dragging');
          });
          eventEl.addEventListener('dragend', function () {
            eventEl.classList.remove('event--dragging');
            draggedSchedule = null;
            cols.forEach(function (c) { c.classList.remove('calendar__col--drop'); });
          });
        });
        cols.forEach(function (col) {
          col.addEventListener('dragover', function (e) {
            if (!draggedSchedule) { return; }
            e.preventDefault();
            col.classList.add('calendar__col--drop');
          });
          col.addEventListener('dragleave', function () {
            if (!draggedSchedule) { return; }
            col.classList.remove('calendar__col--drop');
          });
          col.addEventListener('drop', function (e) {
            if (!draggedSchedule) { return; }
            e.preventDefault();
            col.classList.remove('calendar__col--drop');
            var newDate = col.getAttribute('data-col-date') || '';
            var oldDate = draggedSchedule.getAttribute('data-date') || '';
            if (newDate === '' || oldDate === '' || newDate === oldDate) {
              return;
            }
            if (newDate < todayDateForSchedule) {
              window.alert('Нельзя переносить запись в прошедшую дату.');
              return;
            }
            var slotKind = draggedSchedule.getAttribute('data-entry-kind') || '';
            var entryIdVal = draggedSchedule.getAttribute('data-entry-id') || '';
            var requestIdVal = draggedSchedule.getAttribute('data-request-id') || '';
            if (slotKind === 'schedule' && (!entryIdVal || entryIdVal === '0')) {
              window.alert('Некорректная запись расписания.');
              return;
            }
            if (slotKind === 'approved_request' && (!requestIdVal || requestIdVal === '0')) {
              window.alert('Некорректная заявка.');
              return;
            }
            var roomLabel = ((draggedSchedule.getAttribute('data-entry-building') || '') + ', ' + (draggedSchedule.getAttribute('data-entry-room') || '')).replace(/^,\s*/, '');
            var timeRange = draggedSchedule.getAttribute('data-entry-time') || '';
            var confirmMsg = slotKind === 'approved_request'
              ? ('Перенести одобренную заявку ' + roomLabel + ' (' + timeRange + ') на ' + newDate + '?')
              : ('Перенести запись расписания ' + roomLabel + ' (' + timeRange + ') на ' + newDate + '?');
            if (!window.confirm(confirmMsg)) {
              return;
            }
            if (dndScheduleEntryId) { dndScheduleEntryId.value = slotKind === 'schedule' ? entryIdVal : ''; }
            if (dndScheduleRequestId) { dndScheduleRequestId.value = slotKind === 'approved_request' ? requestIdVal : ''; }
            if (dndScheduleNewRoomId) { dndScheduleNewRoomId.value = draggedSchedule.getAttribute('data-entry-room-id') || ''; }
            if (dndScheduleWeekStart) { dndScheduleWeekStart.value = auditFundWeekStartMonday(newDate); }
            if (dndScheduleNewDate) { dndScheduleNewDate.value = newDate; }
            if (dndScheduleRelocateTimeStart) { dndScheduleRelocateTimeStart.value = draggedSchedule.getAttribute('data-entry-time-start') || ''; }
            if (dndScheduleRelocateTimeEnd) { dndScheduleRelocateTimeEnd.value = draggedSchedule.getAttribute('data-entry-time-end') || ''; }
            dndScheduleForm.submit();
          });
        });
      }
      var maintForm = document.querySelector('form[action="maintenance_add.php"]');
      var maintDateInput = document.getElementById('maint-date');
      var maintStartInput = document.getElementById('maint-time-start');
      var maintEndInput = document.getElementById('maint-time-end');
      var maintInlineError = document.getElementById('maint-inline-error');
      if (maintForm && maintDateInput && maintStartInput && maintEndInput) {
        var now = new Date();
        var today = now.toISOString().slice(0, 10);
        maintDateInput.min = today;
        function clearMaintError() {
          if (!maintInlineError) { return; }
          maintInlineError.textContent = '';
          maintInlineError.classList.add('is-hidden');
        }
        function showMaintError(message) {
          if (!maintInlineError) { return; }
          maintInlineError.textContent = message;
          maintInlineError.classList.remove('is-hidden');
        }
        function validateMaintenanceForm() {
          clearMaintError();
          var dateVal = maintDateInput.value || '';
          var startVal = maintStartInput.value || '';
          var endVal = maintEndInput.value || '';
          if (dateVal === '' || startVal === '' || endVal === '') {
            return true;
          }
          if (startVal >= endVal) {
            showMaintError('Время начала должно быть раньше времени окончания.');
            return false;
          }
          var startDateTime = new Date(dateVal + 'T' + startVal + ':00');
          if (Number.isNaN(startDateTime.getTime())) {
            showMaintError('Проверьте формат даты и времени.');
            return false;
          }
          if (startDateTime.getTime() <= Date.now()) {
            showMaintError('Нельзя добавить окно обслуживания в прошедшее время.');
            return false;
          }
          return true;
        }
        maintForm.addEventListener('submit', function (e) {
          if (!validateMaintenanceForm()) {
            e.preventDefault();
          }
        });
        [maintDateInput, maintStartInput, maintEndInput].forEach(function (input) {
          input.addEventListener('input', clearMaintError);
          input.addEventListener('change', clearMaintError);
        });
      }
    });
  </script>
  <?php if ($schedMoved || $schedMoveErrText !== '' || $schedDeleted || $schedDelErrText !== '' || $maintSaved || $maintUpdated || $maintDeleted || $maintErrText !== '' || $maintUpdErrText !== '' || $maintDelErrText !== '' || $requestSaved || $requestErrText !== ''): ?>
  <script>
    (function () {
      var url = new URL(window.location.href);
      url.searchParams.delete('sched_moved');
      url.searchParams.delete('sched_move_err');
      url.searchParams.delete('sched_deleted');
      url.searchParams.delete('sched_del_err');
      url.searchParams.delete('maint_saved');
      url.searchParams.delete('maint_updated');
      url.searchParams.delete('maint_deleted');
      url.searchParams.delete('maint_err');
      url.searchParams.delete('maint_upd_err');
      url.searchParams.delete('maint_upd_id');
      url.searchParams.delete('maint_del_err');
      url.searchParams.delete('request_saved');
      url.searchParams.delete('request_err');
      window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
    })();
  </script>
  <?php endif; ?>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>

