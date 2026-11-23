<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/management_data.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/flash_messages.php';
require_once __DIR__ . '/includes/pagination.php';

audit_fund_require_auth();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$flashMgmt = audit_fund_flash_take('management.php');
$logoutErrCode = isset($flashMgmt['logout_err']) ? (string) $flashMgmt['logout_err'] : '';
$logoutErrText = audit_fund_flash_text($logoutErrCode, audit_fund_flash_messages_logout(), '');

$mgmtPerPage = audit_fund_pagination_per_page(audit_fund_get_string_key('per_page'), 50, 200);
$mgmtPageRequested = audit_fund_pagination_page(audit_fund_get_string_key('page'));
$fundData = audit_fund_management_rooms_data($mgmtPageRequested, $mgmtPerPage);
$stats = $fundData['stats'];
$roomsListTotal = $stats !== null ? (int) $stats['total'] : 0;
$mgmtTotalPages = audit_fund_pagination_total_pages($roomsListTotal, $mgmtPerPage);
$mgmtPage = audit_fund_pagination_clamp_page($mgmtPageRequested, $mgmtTotalPages);
if ($mgmtPage !== $mgmtPageRequested) {
    $fundData = audit_fund_management_rooms_data($mgmtPage, $mgmtPerPage);
    $stats = $fundData['stats'];
}
$roomRows = $fundData['rows'];
$dbError = $fundData['error'];

$mgmtPagerQuery = [];
if ($mgmtPerPage !== 50) {
    $mgmtPagerQuery['per_page'] = (string) $mgmtPerPage;
}

$formRefs = audit_fund_room_form_references();
$buildingsList = $formRefs['buildings'];
$roomTypesList = $formRefs['room_types'];
$formRefsError = $formRefs['error'];

$roomSaved = (audit_fund_get_string('room_saved') === '1') || !empty($flashMgmt['room_saved']);
$roomUpdated = (audit_fund_get_string('room_updated') === '1') || !empty($flashMgmt['room_updated']);
$roomDeleted = (audit_fund_get_string('room_deleted') === '1') || !empty($flashMgmt['room_deleted']);
$gRoomErr = audit_fund_get_string('room_err');
$roomErrCode = $gRoomErr !== ''
    ? $gRoomErr
    : (isset($flashMgmt['room_err']) ? (string) $flashMgmt['room_err'] : '');
$gRoomUpdErr = audit_fund_get_string('room_upd_err');
$roomUpdErrCode = $gRoomUpdErr !== ''
    ? $gRoomUpdErr
    : (isset($flashMgmt['room_upd_err']) ? (string) $flashMgmt['room_upd_err'] : '');
$gRoomUpdId = audit_fund_get_string_key('room_upd_id');
$roomUpdErrId = $gRoomUpdId !== null ? (int) $gRoomUpdId : (isset($flashMgmt['room_upd_id']) ? (int) $flashMgmt['room_upd_id'] : 0);
$gRoomDelErr = audit_fund_get_string('room_del_err');
$roomDelErrCode = $gRoomDelErr !== ''
    ? $gRoomDelErr
    : (isset($flashMgmt['room_del_err']) ? (string) $flashMgmt['room_del_err'] : '');
$roomErrText = audit_fund_flash_text($roomErrCode, audit_fund_flash_messages_room_add(), 'Не удалось добавить аудиторию.');
$roomUpdErrText = audit_fund_flash_text($roomUpdErrCode, audit_fund_flash_messages_room_update(), 'Не удалось изменить аудиторию.');
$roomDelErrText = audit_fund_flash_text($roomDelErrCode, audit_fund_flash_messages_room_delete(), 'Не удалось удалить аудиторию.');
$auditFundPageTitle = 'Управление фондом — Аудиторный фонд';
$auditFundNav = 'management';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Управление фондом</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Контроль состава аудиторий, вместимости и эксплуатационного статуса: <strong>Активна</strong> (в работе) или <strong>На обслуживании</strong> (недоступна для подбора в заявках).
      </p>

      <?php if ($logoutErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($logoutErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($roomSaved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Аудитория добавлена.</p>
      <?php endif; ?>
      <?php if ($roomUpdated): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Изменения аудитории сохранены.</p>
      <?php endif; ?>
      <?php if ($roomDeleted): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Аудитория удалена.</p>
      <?php endif; ?>
      <?php if ($roomErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roomErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($roomUpdErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roomUpdErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($roomDelErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roomDelErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <section class="stats-grid" aria-label="Показатели фонда">
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Всего аудиторий</p>
          <p class="stat-card__value"><?php echo $stats !== null ? (int) $stats['total'] : '—'; ?></p>
          <p class="stat-card__hint">все помещения в каталоге</p>
        </article>
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Активные аудитории</p>
          <p class="stat-card__value stat-card__value--success"><?php echo $stats !== null ? (int) $stats['active'] : '—'; ?></p>
          <p class="stat-card__hint">в работе, не на обслуживании (как в списке ниже)</p>
        </article>
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">На обслуживании</p>
          <p class="stat-card__value stat-card__value--muted"><?php echo $stats !== null ? (int) $stats['maint'] : '—'; ?></p>
          <p class="stat-card__hint">статус «На обслуживании» (ремонт, проверка)</p>
        </article>
        <article class="stat-card animate-on-load animate-on-load--d2">
          <p class="stat-card__label">Корпусов в справочнике</p>
          <p class="stat-card__value stat-card__value--warning"><?php echo $stats !== null ? (int) $stats['buildings'] : '—'; ?></p>
          <p class="stat-card__hint">записей в справочнике «Корпуса» (раздел «Справочники»)</p>
        </article>
      </section>

      <section class="panel animate-on-load animate-on-load--d3" aria-labelledby="panel-aud-title">
        <div class="panel__head">
          <h2 class="panel__title" id="panel-aud-title">Список аудиторий</h2>
          <div class="panel__tools">
            <label class="visually-hidden" for="search-aud">Поиск по номеру или корпусу</label>
            <input
              class="form__input panel__search"
              type="search"
              id="search-aud"
              placeholder="Поиск…"
              autocomplete="off"
            >
            <form method="get" action="management.php" class="requests-page-size-form">
              <label class="visually-hidden" for="mgmt-per-page">Аудиторий на странице</label>
              <select class="form__input panel__search panel__select" id="mgmt-per-page" name="per_page" aria-label="Аудиторий на странице" onchange="this.form.submit()">
                <?php foreach ([25, 50, 100, 200] as $pp): ?>
                <option value="<?php echo (int) $pp; ?>"<?php echo $mgmtPerPage === $pp ? ' selected' : ''; ?>><?php echo (int) $pp; ?> на стр.</option>
                <?php endforeach; ?>
              </select>
            </form>
            <?php if ($isStaff): ?>
            <button
              class="btn btn--primary"
              type="button"
              data-modal-open="add-room"
              aria-haspopup="dialog"
              aria-controls="modal-add-room"
            >
              Добавить аудиторию
            </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Номер</th>
                <th scope="col">Корпус</th>
                <th scope="col">Этаж</th>
                <th scope="col">Вместимость</th>
                <th scope="col">Тип</th>
                <th scope="col">Статус</th>
                <?php if ($isStaff): ?>
                <th scope="col">Действия</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if ($dbError !== null): ?>
              <tr>
                <td colspan="<?php echo $isStaff ? '7' : '6'; ?>" class="data-table__message"><?php echo htmlspecialchars($dbError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif ($stats !== null && (int) $stats['total'] === 0): ?>
              <tr>
                <td colspan="<?php echo $isStaff ? '7' : '6'; ?>" class="data-table__message">В базе пока нет аудиторий.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($roomRows as $row): ?>
                  <?php
                    $badge = audit_fund_room_status_badge((string) $row['operational_status']);
                  ?>
              <tr data-rooms-row>
                <td><?php echo htmlspecialchars((string) $row['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $row['building_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><?php echo (int) $row['floor']; ?></td>
                <td><?php echo (int) $row['capacity']; ?></td>
                <td><?php echo htmlspecialchars((string) $row['room_type_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td><span class="<?php echo htmlspecialchars($badge['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo htmlspecialchars($badge['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></td>
                <?php if ($isStaff): ?>
                <td>
                  <div class="room-actions-inline">
                    <button
                      class="btn-link"
                      type="button"
                      data-modal-open="edit-room"
                      data-room-id="<?php echo (int) $row['id']; ?>"
                      data-room-number="<?php echo htmlspecialchars((string) $row['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                      data-room-color="<?php echo htmlspecialchars((string) ($row['color_hex'] ?? '#3B82F6'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                      data-building-id="<?php echo (int) $row['building_id']; ?>"
                      data-room-type-id="<?php echo (int) $row['room_type_id']; ?>"
                      data-floor="<?php echo (int) $row['floor']; ?>"
                      data-capacity="<?php echo (int) $row['capacity']; ?>"
                      data-status="<?php echo htmlspecialchars((string) $row['operational_status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    >Изменить</button>
                    <form action="room_delete.php" method="post" class="room-actions__delete-form" onsubmit="return confirm('Удалить аудиторию <?php echo htmlspecialchars((string) $row['room_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?');">
                      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                      <input type="hidden" name="room_id" value="<?php echo (int) $row['id']; ?>">
                      <button class="btn-link btn-link--icon" type="submit" aria-label="Удалить аудиторию" title="Удалить">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                          <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z" fill="currentColor"></path>
                        </svg>
                      </button>
                    </form>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
        if ($dbError === null && $stats !== null && (int) $stats['total'] > 0) {
            audit_fund_pagination_render('management.php', $mgmtPagerQuery, 'page', $mgmtPage, $mgmtTotalPages, $roomsListTotal, $mgmtPerPage, 'аудиторий');
        }
        ?>
      </section>

      <?php if ($isStaff): ?>
      <div class="modal is-hidden" id="modal-add-room" role="dialog" aria-modal="true" aria-labelledby="modal-add-room-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-add-room-title">Добавить аудиторию</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>

          <?php if ($formRefsError !== null): ?>
            <p class="form__error modal__body-error"><?php echo htmlspecialchars($formRefsError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          <?php endif; ?>

          <form class="form form--compact" id="form-add-room" action="room_add.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="room-number">Номер</label>
                <input class="form__input" type="text" id="room-number" name="room_number" maxlength="32" placeholder="например: 203" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="room-building">Корпус</label>
                <select class="form__input" id="room-building" name="building_id" required <?php echo count($buildingsList) === 0 ? ' disabled' : ''; ?>>
                  <option value="">Выберите корпус…</option>
                  <?php foreach ($buildingsList as $b): ?>
                  <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars((string) $b['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form__field">
                <label class="form__label" for="room-floor">Этаж</label>
                <input class="form__input" type="number" id="room-floor" name="floor" min="0" max="32767" placeholder="например: 2" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="room-color">Цвет в календаре</label>
                <input class="form__input" type="color" id="room-color" name="color_hex" value="#3b82f6" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="room-capacity">Вместимость</label>
                <input class="form__input" type="number" id="room-capacity" name="capacity" min="0" max="65535" placeholder="например: 30" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="room-type">Тип</label>
                <select class="form__input" id="room-type" name="room_type_id" required <?php echo count($roomTypesList) === 0 ? ' disabled' : ''; ?>>
                  <option value="">Выберите тип…</option>
                  <?php foreach ($roomTypesList as $t): ?>
                  <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form__field">
                <label class="form__label" for="room-status">Статус</label>
                <select class="form__input" id="room-status" name="operational_status" required>
                  <option value="active">Активна</option>
                  <option value="maint">На обслуживании</option>
                </select>
              </div>
            </div>

            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit" <?php echo (count($buildingsList) === 0 || count($roomTypesList) === 0) ? ' disabled' : ''; ?>>Сохранить</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal is-hidden" id="modal-edit-room" role="dialog" aria-modal="true" aria-labelledby="modal-edit-room-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-room-title">Изменить аудиторию</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>

          <?php if ($formRefsError !== null): ?>
            <p class="form__error modal__body-error"><?php echo htmlspecialchars($formRefsError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
          <?php endif; ?>

          <form class="form form--compact" id="form-edit-room" action="room_update.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" id="edit-room-id" name="room_id" value="">
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="edit-room-number">Номер</label>
                <input class="form__input" type="text" id="edit-room-number" name="room_number" maxlength="32" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-room-building">Корпус</label>
                <select class="form__input" id="edit-room-building" name="building_id" required <?php echo count($buildingsList) === 0 ? ' disabled' : ''; ?>>
                  <option value="">Выберите корпус…</option>
                  <?php foreach ($buildingsList as $b): ?>
                  <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars((string) $b['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-room-floor">Этаж</label>
                <input class="form__input" type="number" id="edit-room-floor" name="floor" min="0" max="32767" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-room-color">Цвет в календаре</label>
                <input class="form__input" type="color" id="edit-room-color" name="color_hex" value="#3b82f6" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-room-capacity">Вместимость</label>
                <input class="form__input" type="number" id="edit-room-capacity" name="capacity" min="0" max="65535" required>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-room-type">Тип</label>
                <select class="form__input" id="edit-room-type" name="room_type_id" required <?php echo count($roomTypesList) === 0 ? ' disabled' : ''; ?>>
                  <option value="">Выберите тип…</option>
                  <?php foreach ($roomTypesList as $t): ?>
                  <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form__field">
                <label class="form__label" for="edit-room-status">Статус</label>
                <select class="form__input" id="edit-room-status" name="operational_status" required>
                  <option value="active">Активна</option>
                  <option value="maint">На обслуживании</option>
                </select>
              </div>
            </div>

            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit" <?php echo (count($buildingsList) === 0 || count($roomTypesList) === 0) ? ' disabled' : ''; ?>>Сохранить изменения</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
  <?php if ($roomErrText !== ''): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var m = document.getElementById('modal-add-room');
      if (m) { m.classList.remove('is-hidden'); }
    });
  </script>
  <?php endif; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var searchInput = document.getElementById('search-aud');
      if (searchInput) {
        var rows = document.querySelectorAll('tr[data-rooms-row]');
        searchInput.addEventListener('input', function () {
          var q = (searchInput.value || '').trim().toLowerCase();
          rows.forEach(function (row) {
            var text = (row.textContent || '').toLowerCase();
            row.style.display = q === '' || text.indexOf(q) !== -1 ? '' : 'none';
          });
        });
      }

      var editButtons = document.querySelectorAll('button[data-modal-open="edit-room"][data-room-id]');
      var form = document.getElementById('form-edit-room');
      if (!editButtons.length || !form) { return; }

      var idInput = document.getElementById('edit-room-id');
      var numberInput = document.getElementById('edit-room-number');
      var buildingSelect = document.getElementById('edit-room-building');
      var floorInput = document.getElementById('edit-room-floor');
      var colorInput = document.getElementById('edit-room-color');
      var capacityInput = document.getElementById('edit-room-capacity');
      var typeSelect = document.getElementById('edit-room-type');
      var statusSelect = document.getElementById('edit-room-status');

      editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (idInput) { idInput.value = btn.getAttribute('data-room-id') || ''; }
          if (numberInput) { numberInput.value = btn.getAttribute('data-room-number') || ''; }
          if (buildingSelect) { buildingSelect.value = btn.getAttribute('data-building-id') || ''; }
          if (floorInput) { floorInput.value = btn.getAttribute('data-floor') || ''; }
          if (colorInput) { colorInput.value = btn.getAttribute('data-room-color') || '#3b82f6'; }
          if (capacityInput) { capacityInput.value = btn.getAttribute('data-capacity') || ''; }
          if (typeSelect) { typeSelect.value = btn.getAttribute('data-room-type-id') || ''; }
          if (statusSelect) {
            var statusValue = btn.getAttribute('data-status') || 'active';
            if (statusValue === 'free' || statusValue === 'busy') { statusValue = 'active'; }
            statusSelect.value = statusValue;
          }
        });
      });

      <?php if ($roomUpdErrText !== '' && $roomUpdErrId > 0): ?>
      var reopenBtn = document.querySelector('button[data-modal-open="edit-room"][data-room-id="<?php echo $roomUpdErrId; ?>"]');
      if (reopenBtn) {
        reopenBtn.click();
      }
      <?php endif; ?>
    });
  </script>
  <?php if ($roomSaved || $roomUpdated || $roomErrText !== '' || $roomUpdErrText !== '' || $roomDeleted || $roomDelErrText !== ''): ?>
  <script>
    (function () {
      var url = new URL(window.location.href);
      url.searchParams.delete('room_saved');
      url.searchParams.delete('room_updated');
      url.searchParams.delete('room_err');
      url.searchParams.delete('room_upd_err');
      url.searchParams.delete('room_upd_id');
      url.searchParams.delete('room_deleted');
      url.searchParams.delete('room_del_err');
      window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
    })();
  </script>
  <?php endif; ?>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>

