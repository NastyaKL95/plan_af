<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/audit_log.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/pagination.php';
require_once __DIR__ . '/includes/flash_messages.php';
require_once __DIR__ . '/includes/redirect_flash.php';

audit_fund_require_admin();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$flashAuditLog = audit_fund_flash_take('audit_log.php');
$auditPurgeOk = !empty($flashAuditLog['audit_purge_ok']);
$auditPurgedCount = isset($flashAuditLog['audit_purged_count']) ? (int) $flashAuditLog['audit_purged_count'] : 0;
$auditPurgeErrCode = isset($flashAuditLog['audit_purge_err']) && (string) $flashAuditLog['audit_purge_err'] !== ''
    ? (string) $flashAuditLog['audit_purge_err']
    : '';
$auditPurgeErrText = audit_fund_flash_text(
    $auditPurgeErrCode,
    audit_fund_flash_messages_audit_log_purge(),
    'Не удалось выполнить очистку журнала.'
);

$dateFrom = trim(audit_fund_get_string('date_from'));
$dateTo = trim(audit_fund_get_string('date_to'));
$userQuery = trim(audit_fund_get_string('user'));
$logPerPage = audit_fund_pagination_per_page(audit_fund_get_string_key('per_page'), 25, 100);
$logPageRequested = audit_fund_pagination_page(audit_fund_get_string_key('page'));

$logData = audit_fund_log_list(
    $dateFrom !== '' ? $dateFrom : null,
    $dateTo !== '' ? $dateTo : null,
    $userQuery !== '' ? $userQuery : null,
    $logPerPage,
    ($logPageRequested - 1) * $logPerPage
);
$logTotal = $logData['total'];
$logTotalPages = audit_fund_pagination_total_pages($logTotal, $logPerPage);
$logPage = audit_fund_pagination_clamp_page($logPageRequested, $logTotalPages);
if ($logPage !== $logPageRequested) {
    $logData = audit_fund_log_list(
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null,
        $userQuery !== '' ? $userQuery : null,
        $logPerPage,
        ($logPage - 1) * $logPerPage
    );
}
$logRows = $logData['rows'];
$logError = $logData['error'];

$auditLogQuery = [];
if ($dateFrom !== '') {
    $auditLogQuery['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $auditLogQuery['date_to'] = $dateTo;
}
if ($userQuery !== '') {
    $auditLogQuery['user'] = $userQuery;
}
if ($logPerPage !== 25) {
    $auditLogQuery['per_page'] = (string) $logPerPage;
}

function audit_fund_audit_action_badge_class(string $action): string
{
    switch ($action) {
        case 'create':
            return 'badge badge--free';
        case 'update':
            return 'badge badge--pending';
        case 'delete':
            return 'badge badge--rejected';
        case 'approve':
            return 'badge badge--free';
        case 'reject':
            return 'badge badge--rejected';
        default:
            return 'badge badge--maint';
    }
}
$auditFundPageTitle = 'Аудит-лог — Аудиторный фонд';
$auditFundNav = 'audit_log';
$auditPurgeAccordionOpen = $auditPurgeErrText !== '';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Аудит-лог</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Журнал действий пользователей по ключевым операциям системы.
      </p>

      <?php if ($auditPurgeOk): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Журнал очищен. Удалено записей: <?php echo (int) $auditPurgedCount; ?>.</p>
      <?php endif; ?>
      <?php if ($auditPurgeErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($auditPurgeErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <?php if (!empty($isAdmin)): ?>
      <details class="accordion accordion--purge panel panel--danger-zone animate-on-load animate-on-load--d2" id="audit-purge-accordion"<?php echo $auditPurgeAccordionOpen ? ' open' : ''; ?>>
        <summary class="accordion__summary">
          <span class="accordion__summary-main">
            <span class="accordion__title" id="audit-purge-title">Очистка журнала</span>
            <span class="accordion__hint">Необратимое удаление — разверните, чтобы выбрать действие</span>
          </span>
          <span class="accordion__chevron" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" focusable="false">
              <path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
            </svg>
          </span>
        </summary>
        <div class="accordion__body">
        <p class="app-intro app-intro--panel-tight">Доступно только администратору. Удалённые записи нельзя восстановить. После успешной очистки в журнал добавляется одна запись о выполненной операции.</p>
        <div class="form-grid form-grid--audit-purge">
          <form class="form form--compact" method="post" action="audit_log_purge.php" onsubmit="return confirm('Удалить все записи журнала, у которых дата события строго раньше выбранной?');">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="purge_mode" value="before">
            <h3 class="form__section-title">Старше даты</h3>
            <div class="form__field">
              <label class="form__label" for="audit-purge-before">Удалить записи с датой раньше</label>
              <input class="form__input" type="date" id="audit-purge-before" name="before_date" required>
            </div>
            <button class="btn btn--danger-ghost" type="submit">Удалить старые</button>
          </form>
          <form class="form form--compact" method="post" action="audit_log_purge.php" onsubmit="return confirm('Удалить все записи журнала за выбранный период (даты включительно)?');">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="purge_mode" value="range">
            <h3 class="form__section-title">За период</h3>
            <div class="form__field">
              <label class="form__label" for="audit-purge-from">С даты</label>
              <input class="form__input" type="date" id="audit-purge-from" name="purge_date_from" required>
            </div>
            <div class="form__field">
              <label class="form__label" for="audit-purge-to">По дату</label>
              <input class="form__input" type="date" id="audit-purge-to" name="purge_date_to" required>
            </div>
            <button class="btn btn--danger-ghost" type="submit">Удалить за период</button>
          </form>
          <form class="form form--compact" method="post" action="audit_log_purge.php" onsubmit="return confirm('Полностью очистить журнал аудита? Это действие необратимо.');">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" name="purge_mode" value="all">
            <h3 class="form__section-title">Полностью</h3>
            <div class="form__field">
              <label class="form__label">
                <input type="checkbox" name="confirm_all" value="1" required>
                Подтверждаю удаление всех записей журнала
              </label>
            </div>
            <button class="btn btn--danger-ghost" type="submit">Очистить весь журнал</button>
          </form>
        </div>
        </div>
      </details>
      <?php endif; ?>

      <section class="panel animate-on-load animate-on-load--d2" aria-labelledby="audit-filter-title">
        <h2 class="panel__title panel__title--block" id="audit-filter-title">Фильтры</h2>
        <form class="form form--compact" method="get" action="audit_log.php">
          <div class="form-grid">
            <div class="form__field">
              <label class="form__label" for="audit-per-page">Записей на странице</label>
              <select class="form__input" id="audit-per-page" name="per_page">
                <?php foreach ([25, 50, 100] as $pp): ?>
                <option value="<?php echo (int) $pp; ?>"<?php echo $logPerPage === $pp ? ' selected' : ''; ?>><?php echo (int) $pp; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form__field">
              <label class="form__label" for="audit-date-from">Дата с</label>
              <input class="form__input" type="date" id="audit-date-from" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>
            <div class="form__field">
              <label class="form__label" for="audit-date-to">Дата по</label>
              <input class="form__input" type="date" id="audit-date-to" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>
            <div class="form__field">
              <label class="form__label" for="audit-user">Кто выполнил</label>
              <input class="form__input" type="search" id="audit-user" name="user" value="<?php echo htmlspecialchars($userQuery, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="Имя или логин">
            </div>
          </div>
          <div class="modal__actions">
            <a class="btn btn--ghost" href="audit_log.php">Сбросить</a>
            <button class="btn btn--primary" type="submit">Применить</button>
          </div>
        </form>
      </section>

      <section class="panel animate-on-load animate-on-load--d3" aria-labelledby="audit-table-title">
        <div class="panel__head">
          <h2 class="panel__title" id="audit-table-title">Журнал</h2>
          <div class="panel__tools">
            <form class="form" method="post" action="audit_log_export.php">
              <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
              <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
              <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
              <input type="hidden" name="user" value="<?php echo htmlspecialchars($userQuery, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
              <button class="btn btn--primary" type="submit">Экспорт в Excel</button>
            </form>
          </div>
        </div>
        <p class="app-intro app-intro--panel-tight">По выбранным фильтрам список разбит на страницы (до <?php echo (int) $logPerPage; ?> записей на страницу). В Excel выгружается до 10&nbsp;000 записей с теми же фильтрами.</p>
        <div class="panel__tools panel__tools--spaced-below">
          <label class="visually-hidden" for="audit-quick-search">Быстрый поиск по журналу</label>
          <input
            class="form__input panel__search"
            type="search"
            id="audit-quick-search"
            placeholder="Быстрый поиск: пользователь, действие, сущность, детали…"
            autocomplete="off"
          >
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Дата/время</th>
                <th scope="col">Имя в системе</th>
                <th scope="col">Действие</th>
                <th scope="col">Сущность</th>
                <th scope="col">ID</th>
                <th scope="col">Детали</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($logError !== null): ?>
              <tr>
                <td colspan="6" class="data-table__message"><?php echo htmlspecialchars($logError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
            <?php elseif (count($logRows) === 0): ?>
              <tr>
                <td colspan="6" class="data-table__message">По выбранным фильтрам действий не найдено.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($logRows as $row): ?>
              <?php
                $actionLabel = audit_fund_audit_action_label((string) $row['action']);
                $entityLabel = audit_fund_audit_entity_label((string) $row['entity_type']);
                $detailsLines = audit_fund_audit_details_pretty(isset($row['details_json']) ? (string) $row['details_json'] : null);
                $searchText = mb_strtolower(
                    trim(
                        (string) ($row['user_display'] ?? '') . ' ' .
                        $actionLabel . ' ' .
                        $entityLabel . ' ' .
                        (string) ($row['entity_id'] ?? '') . ' ' .
                        implode(' ', $detailsLines)
                    ),
                    'UTF-8'
                );
              ?>
              <tr data-audit-row data-search-text="<?php echo htmlspecialchars($searchText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <td><?php echo htmlspecialchars((string) $row['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['user_display'] ?? 'Система'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><span class="<?php echo htmlspecialchars(audit_fund_audit_action_badge_class((string) $row['action']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars($actionLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></td>
                <td><?php echo htmlspecialchars($entityLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['entity_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td>
                  <?php if (count($detailsLines) === 0): ?>
                    —
                  <?php else: ?>
                    <?php foreach ($detailsLines as $line): ?>
                      <div class="audit-details__line"><?php echo htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
        if ($logError === null) {
            audit_fund_pagination_render('audit_log.php', $auditLogQuery, 'page', $logPage, $logTotalPages, $logTotal, $logPerPage, 'записей');
        }
        ?>
      </section>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var searchInput = document.getElementById('audit-quick-search');
      var rows = document.querySelectorAll('tr[data-audit-row]');
      if (!searchInput || !rows.length) { return; }

      searchInput.addEventListener('input', function () {
        var query = (searchInput.value || '').toLowerCase().trim();
        rows.forEach(function (row) {
          var text = (row.getAttribute('data-search-text') || '').toLowerCase();
          row.style.display = (query === '' || text.indexOf(query) !== -1) ? '' : 'none';
        });
      });
    });
  </script>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>
