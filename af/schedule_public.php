<?php
declare(strict_types=1);

/**
 * Публичный просмотр расписания без авторизации (только неделя/месяц, без добавления и редактирования).
 */
require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/schedule_data.php';
require_once __DIR__ . '/includes/schedule_calendar_helpers.php';
require_once __DIR__ . '/includes/requests_data.php';

audit_fund_session_start();

if (audit_fund_is_logged_in()) {
    header('Location: schedule.php');
    exit;
}

$pageView = audit_fund_get_string('view') === 'month' ? 'month' : 'week';

$weekStartParam = audit_fund_get_string('week_start');
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

$monthYm = '';
$gridStart = '';
$gridEnd = '';
$gridStartTs = 0;
$gridEndTs = 0;
$prevMonthYm = '';
$nextMonthYm = '';

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
    $prevMonthYm = date('Y-m', strtotime('-1 month', $monthFirstTs));
    $nextMonthYm = date('Y-m', strtotime('+1 month', $monthFirstTs));

    $scheduleData = audit_fund_schedule_entries_data($gridStart, $gridEnd);
} else {
    $scheduleData = audit_fund_schedule_entries_data($weekStart, $weekEnd);
}
$scheduleRows = $scheduleData['rows'];
$scheduleError = $scheduleData['error'];

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
$monthTabYm = $pageView === 'month' ? $monthYm : $monthFromCurrentWeekYm;
$weekTabWeekStart = $weekStart;
$monthUrlWeekQs = '&amp;week_start=' . htmlspecialchars($weekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pub = 'schedule_public.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Расписание (просмотр) — Аудиторный фонд</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="schedule-public-page">
  <a class="skip-link" href="#main-content">К содержимому</a>
  <header class="schedule-public-header animate-on-load">
    <div class="schedule-public-header__brand">
      <span class="schedule-public-header__title">Расписание</span>
      <span class="schedule-public-header__badge">Только просмотр</span>
    </div>
    <div class="schedule-public-header__actions">
      <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
      <a class="btn btn--primary btn--sm" href="index.php">Войти</a>
    </div>
  </header>

  <main class="schedule-public-main" id="main-content">
    <section class="panel animate-on-load animate-on-load--d1" aria-labelledby="schedule-public-panel-title">
      <div class="panel__head panel__head--stacked">
        <h1 class="panel__title" id="schedule-public-panel-title">Период <?php echo htmlspecialchars($rangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
        <div class="panel__tools">
          <div class="view-toggle" role="tablist" aria-label="Вид расписания">
            <a class="view-toggle__btn<?php echo $pageView === 'week' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $pageView === 'week' ? 'true' : 'false'; ?>" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?week_start=<?php echo htmlspecialchars($weekTabWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Неделя</a>
            <a class="view-toggle__btn<?php echo $pageView === 'month' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $pageView === 'month' ? 'true' : 'false'; ?>" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?view=month&amp;month=<?php echo htmlspecialchars($monthTabYm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $monthUrlWeekQs; ?>">Месяц</a>
          </div>
          <?php if ($pageView === 'month'): ?>
          <div class="week-nav" aria-label="Переключение месяца">
            <a class="btn btn--ghost week-nav__btn" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?view=month&amp;month=<?php echo htmlspecialchars($prevMonthYm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $monthUrlWeekQs; ?>" aria-label="Предыдущий месяц">←</a>
            <span class="week-nav__label"><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
            <a class="btn btn--ghost week-nav__btn" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?view=month&amp;month=<?php echo htmlspecialchars($nextMonthYm, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?><?php echo $monthUrlWeekQs; ?>" aria-label="Следующий месяц">→</a>
          </div>
          <a class="btn btn--ghost" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?view=month&amp;month=<?php echo htmlspecialchars(date('Y-m'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>&amp;week_start=<?php echo htmlspecialchars($currentWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Текущий месяц</a>
          <?php else: ?>
          <div class="week-nav" aria-label="Переключение недели">
            <a class="btn btn--ghost week-nav__btn" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?week_start=<?php echo htmlspecialchars($prevWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-label="Предыдущая неделя">←</a>
            <span class="week-nav__label"><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
            <a class="btn btn--ghost week-nav__btn" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?week_start=<?php echo htmlspecialchars($nextWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" aria-label="Следующая неделя">→</a>
          </div>
          <a class="btn btn--ghost" href="<?php echo htmlspecialchars($pub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?week_start=<?php echo htmlspecialchars($currentWeekStart, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Текущая неделя</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($scheduleError !== null): ?>
        <p class="form__error" role="alert"><?php echo htmlspecialchars($scheduleError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <p class="form__hint schedule-public__notice">Публичный просмотр: без комментариев и адресов корпусов — только время и номер аудитории. Подробности после входа.</p>

      <?php if (count($legendMap) > 0): ?>
      <div class="schedule-legend" aria-label="Легенда цветов по номерам аудиторий">
        <span class="schedule-legend__item">
          <span class="schedule-legend__dot schedule-legend__dot--maint" aria-hidden="true"></span>
          <span>Обслуживание</span>
        </span>
        <?php foreach ($legendMap as $legendKey => $legend): ?>
        <?php
          $lk = (string) $legendKey;
          $pipePos = strrpos($lk, '|');
          $legendRoom = $pipePos !== false ? substr($lk, $pipePos + 1) : '';
          if ($legendRoom === '' && isset($legend['label'])) {
              $lab = (string) $legend['label'];
              $comma = strpos($lab, ',');
              $legendRoom = $comma !== false ? trim(substr($lab, 0, $comma)) : trim($lab);
          }
          $legendPublicLabel = audit_fund_schedule_public_room_label($legendRoom);
        ?>
        <span class="schedule-legend__item">
          <span class="schedule-legend__dot" style="background-color: <?php echo htmlspecialchars((string) $legend['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;"></span>
          <span><?php echo htmlspecialchars($legendPublicLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
        </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

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
              <div class="calendar__col schedule-public__col">
                <?php if (count($events) === 0): ?>
                  <article class="event" data-calendar-empty>
                    <p class="event__time">—</p>
                    <p class="event__title">Нет занятий</p>
                    <p class="event__meta">Свободный день</p>
                  </article>
                <?php else: ?>
                  <?php foreach ($events as $event): ?>
                  <?php
                    $entryKindEv = isset($event['entry_kind']) ? (string) $event['entry_kind'] : 'schedule';
                    $isMaintenance = $entryKindEv === 'maintenance';
                    $eventColor = $isMaintenance ? '#6b7280' : (string) ($event['color_hex'] ?? '#3B82F6');
                    $eventTextColor = audit_fund_schedule_text_color($eventColor);
                    $eventTimeEndDisp = audit_fund_request_time_end_display_hhmm((string) $event['time_end']);
                    $eventTimeRangeLabel = substr((string) $event['time_start'], 0, 5) . '–' . $eventTimeEndDisp;
                    $publicTitle = audit_fund_schedule_public_slot_title($entryKindEv);
                    $publicRoom = audit_fund_schedule_public_room_label((string) ($event['room_number'] ?? ''));
                  ?>
                  <article class="event schedule-public__event" style="background: <?php echo htmlspecialchars($eventColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($eventTextColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; border-color: <?php echo htmlspecialchars($eventColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;">
                    <p class="event__time"><?php echo htmlspecialchars($eventTimeRangeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <p class="event__title"><?php echo htmlspecialchars($publicTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <p class="event__meta"><?php echo htmlspecialchars($publicRoom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                  </article>
                  <?php endforeach; ?>
                <?php endif; ?>
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
              <div class="calendar-month__cell-inner schedule-public__cell-inner">
                <div class="calendar-month__day-num"><?php echo (int) $dayNumM; ?></div>
                <div class="calendar-month__events">
                  <?php if (count($eventsM) === 0): ?>
                    <p class="calendar-month__empty" data-calendar-empty>Нет занятий</p>
                  <?php else: ?>
                    <?php foreach ($eventsM as $event): ?>
                    <?php
                      $entryKindMm = isset($event['entry_kind']) ? (string) $event['entry_kind'] : 'schedule';
                      $isMaintenanceM = $entryKindMm === 'maintenance';
                      $eventColorM = $isMaintenanceM ? '#6b7280' : (string) ($event['color_hex'] ?? '#3B82F6');
                      $eventTextColorM = audit_fund_schedule_text_color($eventColorM);
                      $eventTimeEndDispM = audit_fund_request_time_end_display_hhmm((string) $event['time_end']);
                      $eventTimeRangeLabelM = substr((string) $event['time_start'], 0, 5) . '–' . $eventTimeEndDispM;
                      $publicRoomM = audit_fund_schedule_public_room_label((string) ($event['room_number'] ?? ''));
                    ?>
                    <article class="event event--month schedule-public__event" style="background: <?php echo htmlspecialchars($eventColorM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($eventTextColorM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>; border-color: <?php echo htmlspecialchars($eventColorM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;" title="<?php echo htmlspecialchars(audit_fund_schedule_public_slot_title($entryKindMm), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                      <span class="event--month__time"><?php echo htmlspecialchars($eventTimeRangeLabelM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                      <span class="event--month__meta"><?php echo htmlspecialchars($publicRoomM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                    </article>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  </main>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
  <script src="script.js?v=8"></script>
</body>
</html>
