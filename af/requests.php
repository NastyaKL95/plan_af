<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/requests_data.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/flash_messages.php';
require_once __DIR__ . '/includes/pagination.php';

audit_fund_require_auth();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);
$currentUserId = isset($_SESSION['audit_fund_user_id']) ? (int) $_SESSION['audit_fund_user_id'] : 0;

$flashReq = audit_fund_flash_take('requests.php');

$reqListPerPage = audit_fund_pagination_per_page(audit_fund_get_string_key('per_page'), 30, 100);
$reqListPageRequested = audit_fund_pagination_page(audit_fund_get_string_key('page'));

$requestsListRestrictUserId = audit_fund_is_staff() ? null : $currentUserId;

$roomsData = audit_fund_requests_rooms();
$rooms = $roomsData['rooms'];
$roomsError = $roomsData['error'];
$requestsData = audit_fund_requests_list($reqListPageRequested, $reqListPerPage, $requestsListRestrictUserId);
$requestsTotal = $requestsData['total'];
$reqListTotalPages = audit_fund_pagination_total_pages($requestsTotal, $reqListPerPage);
$reqListPage = audit_fund_pagination_clamp_page($reqListPageRequested, $reqListTotalPages);
if ($reqListPage !== $reqListPageRequested) {
    $requestsData = audit_fund_requests_list($reqListPage, $reqListPerPage, $requestsListRestrictUserId);
}
$requestRows = $requestsData['rows'];
$requestsError = $requestsData['error'];

$requestsPagerQuery = [];
if ($reqListPerPage !== 30) {
    $requestsPagerQuery['per_page'] = (string) $reqListPerPage;
}

$requestSaved = (audit_fund_get_string('request_saved') === '1') || !empty($flashReq['request_saved']);
$requestUpdated = (audit_fund_get_string('request_updated') === '1') || !empty($flashReq['request_updated']);
$gReqErr = audit_fund_get_string('request_err');
$requestErrCode = $gReqErr !== ''
    ? $gReqErr
    : (isset($flashReq['request_err']) ? (string) $flashReq['request_err'] : '');
$gReqUpdErr = audit_fund_get_string('request_update_err');
$requestUpdateErrCode = $gReqUpdErr !== ''
    ? $gReqUpdErr
    : (isset($flashReq['request_update_err']) ? (string) $flashReq['request_update_err'] : '');
$gUpdId = audit_fund_get_string_key('request_upd_id');
$requestUpdErrId = $gUpdId !== null ? (int) $gUpdId : (isset($flashReq['request_upd_id']) ? (int) $flashReq['request_upd_id'] : 0);
$gStatusOk = audit_fund_get_string('request_status_ok');
$requestStatusOk = $gStatusOk !== ''
    ? $gStatusOk
    : (isset($flashReq['request_status_ok']) ? (string) $flashReq['request_status_ok'] : '');
$gStatusErr = audit_fund_get_string('request_status_err');
$requestStatusErrCode = $gStatusErr !== ''
    ? $gStatusErr
    : (isset($flashReq['request_status_err']) ? (string) $flashReq['request_status_err'] : '');
$gStatusId = audit_fund_get_string_key('request_status_id');
$requestStatusErrId = $gStatusId !== null ? (int) $gStatusId : (isset($flashReq['request_status_id']) ? (int) $flashReq['request_status_id'] : 0);
$requestErrText = audit_fund_flash_text($requestErrCode, audit_fund_flash_messages_request_create(), 'Не удалось создать заявку.');
$requestErrHints = isset($flashReq['request_err_hints']) ? (string) $flashReq['request_err_hints'] : '';
$requestStatusOkText = $requestStatusOk === 'approved'
    ? 'Заявка одобрена.'
    : ($requestStatusOk === 'rejected' ? 'Заявка отклонена.' : '');
$requestStatusErrText = audit_fund_flash_text($requestStatusErrCode, audit_fund_flash_messages_request_status(), 'Не удалось изменить статус заявки.');
$requestUpdateErrText = audit_fund_flash_text($requestUpdateErrCode, audit_fund_flash_messages_request_update(), 'Не удалось изменить заявку.');
$auditFundPageTitle = 'Заявки — Аудиторный фонд';
$auditFundNav = 'requests';
$requestsTableColspan = $isStaff ? 8 : 7;
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Заявки</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Заявки на бронирование и перенос занятий. Новую заявку можно подать с страницы «Расписание» (кнопка «Добавить запись» в колонке дня). При оформлении учитывается занятость аудитории по расписанию и активным заявкам. Для одобренных заявок, если менеджер сменил дату или аудиторию в расписании, под статусом показываются пометки.
      </p>
      <?php if ($requestSaved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Заявка создана и отправлена на рассмотрение.</p>
      <?php endif; ?>
      <?php if ($requestUpdated): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Заявка обновлена.</p>
      <?php endif; ?>
      <?php if ($requestErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($requestErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php if ($requestErrHints !== ''): ?>
        <p class="form__hint app-flash animate-on-load animate-on-load--d1" role="status"><?php echo htmlspecialchars($requestErrHints, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($requestUpdateErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($requestUpdateErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($requestStatusOkText !== ''): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status"><?php echo htmlspecialchars($requestStatusOkText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($requestStatusErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($requestStatusErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <section class="panel animate-on-load animate-on-load--d2" aria-labelledby="requests-list-title">
        <div class="panel__head">
          <h2 class="panel__title" id="requests-list-title">Список заявок</h2>
          <div class="panel__tools">
            <label class="visually-hidden" for="filter-status">Фильтр по статусу</label>
            <select class="form__input panel__search panel__select" id="filter-status" name="status">
              <option value="all" selected>Все статусы</option>
              <option value="pending">На рассмотрении</option>
              <option value="approved">Одобрено</option>
              <option value="rejected">Отклонено</option>
            </select>
            <?php if ($isStaff): ?>
            <label class="visually-hidden" for="search-user">Поиск по пользователю</label>
            <input
              class="form__input panel__search"
              type="search"
              id="search-user"
              placeholder="Поиск по пользователю…"
              autocomplete="off"
            >
            <button class="btn btn--ghost" type="button" id="toggle-my-requests" aria-pressed="false">Мои заявки (0)</button>
            <?php endif; ?>
            <form method="get" action="requests.php" class="requests-page-size-form">
              <label class="visually-hidden" for="req-per-page">Записей на странице</label>
              <select class="form__input panel__search panel__select" id="req-per-page" name="per_page" aria-label="Записей на странице" onchange="this.form.submit()">
                <?php foreach ([20, 30, 50, 100] as $pp): ?>
                <option value="<?php echo (int) $pp; ?>"<?php echo $reqListPerPage === $pp ? ' selected' : ''; ?>><?php echo (int) $pp; ?> на стр.</option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
        <?php if ($requestsError === null && $requestsTotal > 0): ?>
        <p class="app-intro app-intro--panel-tight"><?php echo $isStaff
            ? 'Активные и будущие заявки (интервал бронирования ещё не закончился). Всего: '
            : 'Ваши активные и будущие заявки (интервал бронирования ещё не закончился). Всего: '; ?><strong><?php echo (int) $requestsTotal; ?></strong>.</p>
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
                <?php if ($isStaff): ?>
                <th scope="col">Кто добавил</th>
                <?php endif; ?>
                <th scope="col">Статус</th>
                <th scope="col">Действия</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($requestsError !== null): ?>
              <tr>
                <td colspan="<?php echo (int) $requestsTableColspan; ?>" class="data-table__message"><?php echo htmlspecialchars($requestsError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif (count($requestRows) === 0): ?>
              <tr>
                <td colspan="<?php echo (int) $requestsTableColspan; ?>" class="data-table__message">Заявок пока нет.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($requestRows as $row): ?>
                <?php $badge = audit_fund_request_status_badge((string) $row['status']); ?>
                <?php $slotChanged = audit_fund_request_approved_slot_changed_flags($row); ?>
                <?php
                  $requestedByUserId = isset($row['requested_by_user_id']) ? (int) $row['requested_by_user_id'] : 0;
                  $requestedByUsername = isset($row['requested_by_username']) && trim((string) $row['requested_by_username']) !== ''
                    ? (string) $row['requested_by_username']
                    : 'Не указан';
                ?>
                <tr
                  data-request-row
                  data-status="<?php echo htmlspecialchars((string) $row['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                  data-requested-by="<?php echo (int) $requestedByUserId; ?>"
                  data-requested-by-name="<?php echo htmlspecialchars(mb_strtolower($requestedByUsername), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                  data-is-my="<?php echo ($requestedByUserId > 0 && $requestedByUserId === $currentUserId) ? '1' : '0'; ?>"
                  data-request-id="<?php echo (int) $row['id']; ?>"
                  data-room-id="<?php echo (int) $row['room_id']; ?>"
                  data-booking-date="<?php echo htmlspecialchars((string) $row['booking_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                  data-time-start="<?php echo htmlspecialchars(substr((string) $row['time_start'], 0, 5), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                  data-time-end="<?php echo htmlspecialchars(audit_fund_request_time_end_display_hhmm((string) $row['time_end']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                  data-applicant="<?php echo htmlspecialchars((string) $row['applicant_display'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                  data-comment="<?php echo htmlspecialchars((string) ($row['comment'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                >
                  <td><?php echo htmlspecialchars((string) $row['request_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $row['submitted_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['room_number'] . ' — ' . (string) $row['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(date('d.m.Y', strtotime((string) $row['booking_date'])) . ', ' . substr((string) $row['time_start'], 0, 5) . '–' . audit_fund_request_time_end_display_hhmm((string) $row['time_end']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['applicant_display'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <?php if ($isStaff): ?>
                  <td><?php echo htmlspecialchars($requestedByUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <?php endif; ?>
                  <td>
                    <span class="<?php echo htmlspecialchars($badge['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars($badge['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                    <?php if ($slotChanged['date'] || $slotChanged['room'] || $slotChanged['time']): ?>
                      <div class="request-slot-notes">
                        <?php if ($slotChanged['date']): ?>
                          <span class="badge badge--maint">Дата изменена после одобрения</span>
                        <?php endif; ?>
                        <?php if ($slotChanged['room']): ?>
                          <span class="badge badge--maint">Аудитория изменена после одобрения</span>
                        <?php endif; ?>
                        <?php if ($slotChanged['time']): ?>
                          <span class="badge badge--maint">Время изменено после одобрения</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                    <?php if ((string) $row['status'] === 'rejected' && isset($row['rejection_reason']) && trim((string) $row['rejection_reason']) !== ''): ?>
                      <div class="form__hint">Причина: <?php echo htmlspecialchars((string) $row['rejection_reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ((string) $row['status'] === 'pending'): ?>
                      <div class="request-actions">
                        <?php if ($isStaff): ?>
                        <form action="request_status.php" method="post">
                          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                          <input type="hidden" name="request_id" value="<?php echo (int) $row['id']; ?>">
                          <input type="hidden" name="status" value="approved">
                          <button class="btn btn--primary btn--sm" type="submit">Одобрить</button>
                        </form>
                        <button
                          class="btn btn--ghost btn--sm btn--danger-ghost"
                          type="button"
                          data-modal-open="reject-request"
                          data-reject-request-id="<?php echo (int) $row['id']; ?>"
                          data-reject-request-number="<?php echo htmlspecialchars((string) $row['request_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        >Отклонить</button>
                        <?php endif; ?>
                        <?php if ($requestedByUserId === $currentUserId): ?>
                        <button
                          class="btn btn--ghost btn--sm"
                          type="button"
                          data-modal-open="edit-request"
                          data-edit-request-id="<?php echo (int) $row['id']; ?>"
                        >Изменить</button>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
        if ($requestsError === null) {
            audit_fund_pagination_render('requests.php', $requestsPagerQuery, 'page', $reqListPage, $reqListTotalPages, $requestsTotal, $reqListPerPage, 'заявок');
        }
        ?>
      </section>

      <div class="modal is-hidden" id="modal-edit-request" role="dialog" aria-modal="true" aria-labelledby="modal-edit-request-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-request-title">Изменить заявку</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" id="form-edit-request" action="request_update.php" method="post">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" id="edit-request-id" name="request_id" value="">
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="edit-req-room">Аудитория <span class="form__required-mark" aria-hidden="true">*</span></label>
                <select class="form__input" id="edit-req-room" name="room_id" required autocomplete="off" title="Выберите аудиторию" <?php echo $roomsError !== null ? ' disabled' : ''; ?>>
                  <option value="">Выберите…</option>
                  <?php foreach ($rooms as $room): ?>
                  <option value="<?php echo (int) $room['id']; ?>"><?php echo htmlspecialchars((string) $room['room_number'] . ' — ' . (string) $room['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-req-date">Дата занятия <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="date" id="edit-req-date" name="booking_date" required title="Укажите дату">
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-req-time-from">Время начала <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="time" id="edit-req-time-from" name="time_start" required title="Время начала">
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-req-time-to">Время окончания <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="time" id="edit-req-time-to" name="time_end" required value="10:00" title="Время окончания">
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-req-applicant">Заявитель <span class="form__required-mark" aria-hidden="true">*</span></label>
                <input class="form__input" type="text" id="edit-req-applicant" name="applicant_display" maxlength="255" required placeholder="Кафедра или подразделение" autocomplete="organization" title="Укажите заявителя">
              </div>
            </div>
            <div class="form__field">
              <label class="form__label" for="edit-req-comment">Комментарий</label>
              <input class="form__input" type="text" id="edit-req-comment" name="comment" maxlength="1000">
            </div>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit">Сохранить</button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal is-hidden" id="modal-reject-request" role="dialog" aria-modal="true" aria-labelledby="modal-reject-request-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-reject-request-title">Отклонить заявку</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" id="form-reject-request" action="request_status.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" id="reject-request-id" name="request_id" value="">
            <input type="hidden" name="status" value="rejected">
            <div class="form__field">
              <label class="form__label" for="reject-request-reason">Причина отклонения</label>
              <textarea class="form__input" id="reject-request-reason" name="rejection_reason" rows="4" maxlength="1000" required placeholder="Укажите причину отклонения заявки"></textarea>
            </div>
            <p class="form__hint" id="reject-request-hint"></p>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--danger-ghost" type="submit">Отклонить</button>
            </div>
          </form>
        </div>
      </div>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var filter = document.getElementById('filter-status');
      var userSearch = document.getElementById('search-user');
      var myRequestsToggle = document.getElementById('toggle-my-requests');
      var rows = document.querySelectorAll('tr[data-request-row]');
      var editButtons = document.querySelectorAll('button[data-modal-open="edit-request"][data-edit-request-id]');
      var rejectButtons = document.querySelectorAll('button[data-modal-open="reject-request"][data-reject-request-id]');
      var showOnlyMine = false;
      function updateMyRequestsCounter() {
        if (!myRequestsToggle) { return; }
        var totalMine = 0;
        var visibleMine = 0;
        rows.forEach(function (row) {
          var rowIsMy = row.getAttribute('data-is-my') === '1';
          if (!rowIsMy) { return; }
          totalMine += 1;
          if (row.style.display !== 'none') {
            visibleMine += 1;
          }
        });
        var value = showOnlyMine ? visibleMine : totalMine;
        myRequestsToggle.textContent = 'Мои заявки (' + String(value) + ')';
      }
      function applyRequestFilters() {
        var statusValue = filter ? (filter.value || 'all') : 'all';
        var userQuery = userSearch ? (userSearch.value || '').trim().toLowerCase() : '';
        rows.forEach(function (row) {
          var rowStatus = row.getAttribute('data-status') || '';
          var rowUserName = (row.getAttribute('data-requested-by-name') || '').toLowerCase();
          var rowIsMy = row.getAttribute('data-is-my') === '1';
          var statusOk = (statusValue === 'all' || rowStatus === statusValue);
          var userOk = (userQuery === '' || rowUserName.indexOf(userQuery) !== -1);
          var mineOk = (!showOnlyMine || rowIsMy);
          row.style.display = (statusOk && userOk && mineOk) ? '' : 'none';
        });
        updateMyRequestsCounter();
      }
      if (rows.length) {
        if (filter) {
          filter.addEventListener('change', applyRequestFilters);
        }
        if (userSearch) {
          userSearch.addEventListener('input', applyRequestFilters);
        }
        if (myRequestsToggle) {
          myRequestsToggle.addEventListener('click', function () {
            showOnlyMine = !showOnlyMine;
            myRequestsToggle.setAttribute('aria-pressed', showOnlyMine ? 'true' : 'false');
            myRequestsToggle.classList.toggle('btn--primary', showOnlyMine);
            myRequestsToggle.classList.toggle('btn--ghost', !showOnlyMine);
            applyRequestFilters();
          });
        }
        updateMyRequestsCounter();
      }
      if (editButtons.length) {
        var editRequestId = document.getElementById('edit-request-id');
        var editReqRoom = document.getElementById('edit-req-room');
        var editReqDate = document.getElementById('edit-req-date');
        var editReqTimeFrom = document.getElementById('edit-req-time-from');
        var editReqTimeTo = document.getElementById('edit-req-time-to');
        var editReqApplicant = document.getElementById('edit-req-applicant');
        var editReqComment = document.getElementById('edit-req-comment');
        editButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            var row = btn.closest('tr[data-request-row]');
            if (!row) { return; }
            if (editRequestId) { editRequestId.value = row.getAttribute('data-request-id') || ''; }
            if (editReqRoom) { editReqRoom.value = row.getAttribute('data-room-id') || ''; }
            if (editReqDate) { editReqDate.value = row.getAttribute('data-booking-date') || ''; }
            if (editReqTimeFrom) { editReqTimeFrom.value = row.getAttribute('data-time-start') || ''; }
            if (editReqTimeTo) { editReqTimeTo.value = row.getAttribute('data-time-end') || ''; }
            if (editReqApplicant) { editReqApplicant.value = row.getAttribute('data-applicant') || ''; }
            if (editReqComment) { editReqComment.value = row.getAttribute('data-comment') || ''; }
          });
        });
      }
      if (rejectButtons.length) {
        var rejectRequestId = document.getElementById('reject-request-id');
        var rejectReason = document.getElementById('reject-request-reason');
        var rejectHint = document.getElementById('reject-request-hint');
        rejectButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (rejectRequestId) { rejectRequestId.value = btn.getAttribute('data-reject-request-id') || ''; }
            if (rejectReason) { rejectReason.value = ''; }
            if (rejectHint) {
              var reqNumber = btn.getAttribute('data-reject-request-number') || '';
              rejectHint.textContent = reqNumber !== '' ? ('Заявка № ' + reqNumber) : '';
            }
          });
        });
      }
      <?php if ($requestUpdateErrText !== '' && $requestUpdErrId > 0): ?>
      var reopenBtn = document.querySelector('button[data-modal-open="edit-request"][data-edit-request-id="<?php echo $requestUpdErrId; ?>"]');
      if (reopenBtn) {
        reopenBtn.click();
      }
      <?php endif; ?>
      <?php if ($requestStatusErrText !== '' && $requestStatusErrId > 0): ?>
      var reopenRejectBtn = document.querySelector('button[data-modal-open="reject-request"][data-reject-request-id="<?php echo $requestStatusErrId; ?>"]');
      if (reopenRejectBtn) {
        reopenRejectBtn.click();
      }
      <?php endif; ?>
    });
  </script>
  <?php if ($requestSaved || $requestUpdated || $requestErrText !== '' || $requestUpdateErrText !== '' || $requestStatusOkText !== '' || $requestStatusErrText !== ''): ?>
  <script>
    (function () {
      var url = new URL(window.location.href);
      url.searchParams.delete('request_saved');
      url.searchParams.delete('request_updated');
      url.searchParams.delete('request_err');
      url.searchParams.delete('request_update_err');
      url.searchParams.delete('request_upd_id');
      url.searchParams.delete('request_status_ok');
      url.searchParams.delete('request_status_err');
      url.searchParams.delete('request_status_id');
      window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
    })();
  </script>
  <?php endif; ?>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>

