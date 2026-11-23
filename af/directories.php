<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/directories_data.php';
require_once __DIR__ . '/includes/app_layout.php';
require_once __DIR__ . '/includes/flash_messages.php';

audit_fund_require_auth();

extract(audit_fund_app_layout_vars(), EXTR_SKIP);

$flashDir = audit_fund_flash_take('directories.php');

$buildingsData = audit_fund_directories_buildings_data();
$buildingRows = $buildingsData['rows'];
$buildingsError = $buildingsData['error'];
$roomTypesData = audit_fund_directories_room_types_data();
$roomTypeRows = $roomTypesData['rows'];
$roomTypesError = $roomTypesData['error'];

$buildingSaved = (audit_fund_get_string('building_saved') === '1') || !empty($flashDir['building_saved']);
$buildingUpdated = (audit_fund_get_string('building_updated') === '1') || !empty($flashDir['building_updated']);
$buildingDeleted = (audit_fund_get_string('building_deleted') === '1') || !empty($flashDir['building_deleted']);
$gBldErr = audit_fund_get_string('building_err');
$buildingErrCode = $gBldErr !== ''
    ? $gBldErr
    : (isset($flashDir['building_err']) ? (string) $flashDir['building_err'] : '');
$gBldUpdErr = audit_fund_get_string('building_upd_err');
$buildingUpdErrCode = $gBldUpdErr !== ''
    ? $gBldUpdErr
    : (isset($flashDir['building_upd_err']) ? (string) $flashDir['building_upd_err'] : '');
$gBldUpdId = audit_fund_get_string_key('building_upd_id');
$buildingUpdErrId = $gBldUpdId !== null ? (int) $gBldUpdId : (isset($flashDir['building_upd_id']) ? (int) $flashDir['building_upd_id'] : 0);
$gBldDelErr = audit_fund_get_string('building_del_err');
$buildingDelErrCode = $gBldDelErr !== ''
    ? $gBldDelErr
    : (isset($flashDir['building_del_err']) ? (string) $flashDir['building_del_err'] : '');
$buildingErrText = audit_fund_flash_text($buildingErrCode, audit_fund_flash_messages_building_add(), 'Не удалось добавить корпус.');
$buildingUpdErrText = audit_fund_flash_text($buildingUpdErrCode, audit_fund_flash_messages_building_update(), 'Не удалось изменить корпус.');
$buildingDelErrText = audit_fund_flash_text($buildingDelErrCode, audit_fund_flash_messages_building_delete(), 'Не удалось удалить корпус.');

$roomTypeSaved = (audit_fund_get_string('room_type_saved') === '1') || !empty($flashDir['room_type_saved']);
$roomTypeUpdated = (audit_fund_get_string('room_type_updated') === '1') || !empty($flashDir['room_type_updated']);
$roomTypeDeleted = (audit_fund_get_string('room_type_deleted') === '1') || !empty($flashDir['room_type_deleted']);
$gRtErr = audit_fund_get_string('room_type_err');
$roomTypeErrCode = $gRtErr !== ''
    ? $gRtErr
    : (isset($flashDir['room_type_err']) ? (string) $flashDir['room_type_err'] : '');
$gRtUpdErr = audit_fund_get_string('room_type_upd_err');
$roomTypeUpdErrCode = $gRtUpdErr !== ''
    ? $gRtUpdErr
    : (isset($flashDir['room_type_upd_err']) ? (string) $flashDir['room_type_upd_err'] : '');
$gRtUpdId = audit_fund_get_string_key('room_type_upd_id');
$roomTypeUpdErrId = $gRtUpdId !== null ? (int) $gRtUpdId : (isset($flashDir['room_type_upd_id']) ? (int) $flashDir['room_type_upd_id'] : 0);
$gRtDelErr = audit_fund_get_string('room_type_del_err');
$roomTypeDelErrCode = $gRtDelErr !== ''
    ? $gRtDelErr
    : (isset($flashDir['room_type_del_err']) ? (string) $flashDir['room_type_del_err'] : '');

$roomTypeErrText = audit_fund_flash_text($roomTypeErrCode, audit_fund_flash_messages_room_type_add(), 'Не удалось добавить тип аудитории.');
$roomTypeUpdErrText = audit_fund_flash_text($roomTypeUpdErrCode, audit_fund_flash_messages_room_type_update(), 'Не удалось изменить тип аудитории.');
$roomTypeDelErrText = audit_fund_flash_text($roomTypeDelErrCode, audit_fund_flash_messages_room_type_delete(), 'Не удалось удалить тип аудитории.');
$auditFundPageTitle = 'Справочники — Аудиторный фонд';
$auditFundNav = 'directories';
require __DIR__ . '/includes/layout_app_open.php';
?>
    <main class="app-main" id="main-content">
      <h1 class="page-title animate-on-load animate-on-load--d1">Справочники</h1>
      <p class="app-intro animate-on-load animate-on-load--d1">
        Базовые данные системы: корпуса и типы аудиторий. Код в таблице присваивается автоматически при добавлении (формат BLD-… для корпусов, RT-… для типов); при редактировании меняются только название и прочие поля.
      </p>
      <?php if ($buildingSaved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Корпус добавлен.</p>
      <?php endif; ?>
      <?php if ($buildingUpdated): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Изменения корпуса сохранены.</p>
      <?php endif; ?>
      <?php if ($buildingDeleted): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Корпус удалён.</p>
      <?php endif; ?>
      <?php if ($buildingErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($buildingErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($buildingUpdErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($buildingUpdErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($buildingDelErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($buildingDelErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($roomTypeSaved): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Тип аудитории добавлен.</p>
      <?php endif; ?>
      <?php if ($roomTypeUpdated): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Изменения типа аудитории сохранены.</p>
      <?php endif; ?>
      <?php if ($roomTypeDeleted): ?>
        <p class="form__success app-flash animate-on-load animate-on-load--d1" role="status">Тип аудитории удалён.</p>
      <?php endif; ?>
      <?php if ($roomTypeErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roomTypeErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($roomTypeUpdErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roomTypeUpdErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php if ($roomTypeDelErrText !== ''): ?>
        <p class="form__error app-flash animate-on-load animate-on-load--d1" role="alert"><?php echo htmlspecialchars($roomTypeDelErrText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endif; ?>

      <section class="panel animate-on-load animate-on-load--d2" aria-labelledby="dir-buildings-title">
        <div class="panel__head">
          <h2 class="panel__title" id="dir-buildings-title">Корпуса</h2>
          <div class="panel__tools">
            <label class="visually-hidden" for="search-buildings">Поиск корпуса</label>
            <input
              class="form__input panel__search"
              type="search"
              id="search-buildings"
              placeholder="Поиск корпуса…"
              autocomplete="off"
            >
            <?php if ($isStaff): ?>
            <button class="btn btn--primary" type="button" data-modal-open="add-building" aria-haspopup="dialog" aria-controls="modal-add-building">Добавить корпус</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Код</th>
                <th scope="col">Название</th>
                <th scope="col">Адрес</th>
                <th scope="col">Аудиторий</th>
                <?php if ($isStaff): ?>
                <th scope="col">Действия</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if ($buildingsError !== null): ?>
              <tr>
                <td colspan="<?php echo $isStaff ? '5' : '4'; ?>" class="data-table__message"><?php echo htmlspecialchars($buildingsError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif (count($buildingRows) === 0): ?>
              <tr>
                <td colspan="<?php echo $isStaff ? '5' : '4'; ?>" class="data-table__message">Список корпусов пока пуст.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($buildingRows as $row): ?>
                <tr data-buildings-row>
                  <td><?php echo htmlspecialchars((string) $row['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($row['address'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo (int) $row['rooms_count']; ?></td>
                  <?php if ($isStaff): ?>
                  <td>
                    <div class="room-actions-inline">
                      <button
                        class="btn-link"
                        type="button"
                        data-modal-open="edit-building"
                        data-building-id="<?php echo (int) $row['id']; ?>"
                        data-building-code="<?php echo htmlspecialchars((string) $row['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-building-name="<?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-building-address="<?php echo htmlspecialchars((string) ($row['address'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                      >Изменить</button>
                      <form action="building_delete.php" method="post" class="room-actions__delete-form" onsubmit="return confirm('Удалить корпус <?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <input type="hidden" name="building_id" value="<?php echo (int) $row['id']; ?>">
                        <button class="btn-link btn-link--icon" type="submit" aria-label="Удалить корпус" title="Удалить">
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
      </section>

      <section class="panel animate-on-load animate-on-load--d3" aria-labelledby="dir-types-title">
        <div class="panel__head">
          <h2 class="panel__title" id="dir-types-title">Типы аудиторий</h2>
          <div class="panel__tools">
            <label class="visually-hidden" for="search-room-types">Поиск типа аудитории</label>
            <input
              class="form__input panel__search"
              type="search"
              id="search-room-types"
              placeholder="Поиск типа…"
              autocomplete="off"
            >
            <?php if ($isStaff): ?>
            <button class="btn btn--primary" type="button" data-modal-open="add-room-type" aria-haspopup="dialog" aria-controls="modal-add-room-type">Добавить тип</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Код</th>
                <th scope="col">Тип</th>
                <th scope="col">Описание</th>
                <th scope="col">Количество</th>
                <?php if ($isStaff): ?>
                <th scope="col">Действия</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if ($roomTypesError !== null): ?>
              <tr>
                <td colspan="<?php echo $isStaff ? '5' : '4'; ?>" class="data-table__message"><?php echo htmlspecialchars($roomTypesError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              </tr>
              <?php elseif (count($roomTypeRows) === 0): ?>
              <tr>
                <td colspan="<?php echo $isStaff ? '5' : '4'; ?>" class="data-table__message">Список типов аудиторий пока пуст.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($roomTypeRows as $row): ?>
                <tr data-room-types-row>
                  <td><?php echo htmlspecialchars((string) $row['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($row['description'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                  <td><?php echo (int) $row['rooms_count']; ?></td>
                  <?php if ($isStaff): ?>
                  <td>
                    <div class="room-actions-inline">
                      <button
                        class="btn-link"
                        type="button"
                        data-modal-open="edit-room-type"
                        data-room-type-id="<?php echo (int) $row['id']; ?>"
                        data-room-type-code="<?php echo htmlspecialchars((string) $row['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-room-type-name="<?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-room-type-description="<?php echo htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                      >Изменить</button>
                      <form action="room_type_delete.php" method="post" class="room-actions__delete-form" onsubmit="return confirm('Удалить тип аудитории <?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>?');">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <input type="hidden" name="room_type_id" value="<?php echo (int) $row['id']; ?>">
                        <button class="btn-link btn-link--icon" type="submit" aria-label="Удалить тип аудитории" title="Удалить">
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
      </section>

      <?php if ($isStaff): ?>
      <div class="modal is-hidden" id="modal-add-building" role="dialog" aria-modal="true" aria-labelledby="modal-add-building-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-add-building-title">Добавить корпус</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" action="building_add.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <p class="form__hint form__hint--modal-lead">Код корпуса будет создан автоматически после сохранения.</p>
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="building-name">Название</label>
                <input class="form__input" type="text" id="building-name" name="name" maxlength="255" placeholder="например: Учебный корпус №4" required>
              </div>
              <div class="form__field form__field--full">
                <label class="form__label" for="building-address">Адрес</label>
                <input class="form__input" type="text" id="building-address" name="address" maxlength="500" placeholder="например: ул. Центральная, 15">
              </div>
            </div>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit">Сохранить</button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal is-hidden" id="modal-edit-building" role="dialog" aria-modal="true" aria-labelledby="modal-edit-building-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-building-title">Изменить корпус</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" id="form-edit-building" action="building_update.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" id="edit-building-id" name="building_id" value="">
            <p class="form__hint form__hint--modal-lead" id="edit-building-code-row">Внутренний код: <strong id="edit-building-code-display">—</strong></p>
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="edit-building-name">Название</label>
                <input class="form__input" type="text" id="edit-building-name" name="name" maxlength="255" required>
              </div>
              <div class="form__field form__field--full">
                <label class="form__label" for="edit-building-address">Адрес</label>
                <input class="form__input" type="text" id="edit-building-address" name="address" maxlength="500">
              </div>
            </div>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit">Сохранить изменения</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal is-hidden" id="modal-add-room-type" role="dialog" aria-modal="true" aria-labelledby="modal-add-room-type-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-add-room-type-title">Добавить тип аудитории</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" action="room_type_add.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <p class="form__hint form__hint--modal-lead">Код типа будет создан автоматически после сохранения.</p>
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="room-type-name">Название</label>
                <input class="form__input" type="text" id="room-type-name" name="name" maxlength="128" placeholder="например: Лабораторная" required>
              </div>
              <div class="form__field form__field--full">
                <label class="form__label" for="room-type-description">Описание</label>
                <input class="form__input" type="text" id="room-type-description" name="description" maxlength="500" placeholder="например: Аудитория для лабораторных занятий">
              </div>
            </div>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit">Сохранить</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal is-hidden" id="modal-edit-room-type" role="dialog" aria-modal="true" aria-labelledby="modal-edit-room-type-title">
        <div class="modal__overlay" data-modal-close></div>
        <div class="modal__dialog" role="document">
          <div class="modal__head">
            <h2 class="modal__title" id="modal-edit-room-type-title">Изменить тип аудитории</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="Закрыть">×</button>
          </div>
          <form class="form form--compact" id="form-edit-room-type" action="room_type_update.php" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(audit_fund_csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input type="hidden" id="edit-room-type-id" name="room_type_id" value="">
            <p class="form__hint form__hint--modal-lead" id="edit-room-type-code-row">Внутренний код: <strong id="edit-room-type-code-display">—</strong></p>
            <div class="form-grid">
              <div class="form__field">
                <label class="form__label" for="edit-room-type-name">Название</label>
                <input class="form__input" type="text" id="edit-room-type-name" name="name" maxlength="128" required>
              </div>
              <div class="form__field form__field--full">
                <label class="form__label" for="edit-room-type-description">Описание</label>
                <input class="form__input" type="text" id="edit-room-type-description" name="description" maxlength="500">
              </div>
            </div>
            <div class="modal__actions">
              <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
              <button class="btn btn--primary" type="submit">Сохранить изменения</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </main>
<?php require __DIR__ . '/includes/layout_app_post_main.php'; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      function setupTableSearch(inputId, rowsSelector) {
        var input = document.getElementById(inputId);
        if (!input) { return; }
        var rows = document.querySelectorAll(rowsSelector);
        if (!rows.length) { return; }

        input.addEventListener('input', function () {
          var q = (input.value || '').trim().toLowerCase();
          rows.forEach(function (row) {
            var text = (row.textContent || '').toLowerCase();
            row.style.display = q === '' || text.indexOf(q) !== -1 ? '' : 'none';
          });
        });
      }

      setupTableSearch('search-buildings', 'tr[data-buildings-row]');
      setupTableSearch('search-room-types', 'tr[data-room-types-row]');

      var editButtons = document.querySelectorAll('button[data-modal-open="edit-building"][data-building-id]');
      if (!editButtons.length) { return; }

      var idInput = document.getElementById('edit-building-id');
      var codeDisplay = document.getElementById('edit-building-code-display');
      var nameInput = document.getElementById('edit-building-name');
      var addressInput = document.getElementById('edit-building-address');

      editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (idInput) { idInput.value = btn.getAttribute('data-building-id') || ''; }
          if (codeDisplay) { codeDisplay.textContent = btn.getAttribute('data-building-code') || '—'; }
          if (nameInput) { nameInput.value = btn.getAttribute('data-building-name') || ''; }
          if (addressInput) { addressInput.value = btn.getAttribute('data-building-address') || ''; }
        });
      });

      <?php if ($buildingUpdErrText !== '' && $buildingUpdErrId > 0): ?>
      var reopenBtn = document.querySelector('button[data-modal-open="edit-building"][data-building-id="<?php echo $buildingUpdErrId; ?>"]');
      if (reopenBtn) {
        reopenBtn.click();
      }
      <?php endif; ?>
    });
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var editButtons = document.querySelectorAll('button[data-modal-open="edit-room-type"][data-room-type-id]');
      if (!editButtons.length) { return; }

      var idInput = document.getElementById('edit-room-type-id');
      var codeDisplay = document.getElementById('edit-room-type-code-display');
      var nameInput = document.getElementById('edit-room-type-name');
      var descriptionInput = document.getElementById('edit-room-type-description');

      editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (idInput) { idInput.value = btn.getAttribute('data-room-type-id') || ''; }
          if (codeDisplay) { codeDisplay.textContent = btn.getAttribute('data-room-type-code') || '—'; }
          if (nameInput) { nameInput.value = btn.getAttribute('data-room-type-name') || ''; }
          if (descriptionInput) { descriptionInput.value = btn.getAttribute('data-room-type-description') || ''; }
        });
      });

      <?php if ($roomTypeUpdErrText !== '' && $roomTypeUpdErrId > 0): ?>
      var reopenBtn = document.querySelector('button[data-modal-open="edit-room-type"][data-room-type-id="<?php echo $roomTypeUpdErrId; ?>"]');
      if (reopenBtn) {
        reopenBtn.click();
      }
      <?php endif; ?>
    });
  </script>
  <?php if ($buildingErrText !== ''): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var m = document.getElementById('modal-add-building');
      if (m) { m.classList.remove('is-hidden'); }
    });
  </script>
  <?php endif; ?>
  <?php if ($roomTypeErrText !== ''): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var m = document.getElementById('modal-add-room-type');
      if (m) { m.classList.remove('is-hidden'); }
    });
  </script>
  <?php endif; ?>
  <?php if ($buildingSaved || $buildingUpdated || $buildingDeleted || $buildingErrText !== '' || $buildingUpdErrText !== '' || $buildingDelErrText !== '' || $roomTypeSaved || $roomTypeUpdated || $roomTypeDeleted || $roomTypeErrText !== '' || $roomTypeUpdErrText !== '' || $roomTypeDelErrText !== ''): ?>
  <script>
    (function () {
      var url = new URL(window.location.href);
      url.searchParams.delete('building_saved');
      url.searchParams.delete('building_updated');
      url.searchParams.delete('building_deleted');
      url.searchParams.delete('building_err');
      url.searchParams.delete('building_upd_err');
      url.searchParams.delete('building_upd_id');
      url.searchParams.delete('building_del_err');
      url.searchParams.delete('room_type_saved');
      url.searchParams.delete('room_type_updated');
      url.searchParams.delete('room_type_deleted');
      url.searchParams.delete('room_type_err');
      url.searchParams.delete('room_type_upd_err');
      url.searchParams.delete('room_type_upd_id');
      url.searchParams.delete('room_type_del_err');
      window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
    })();
  </script>
  <?php endif; ?>
<?php require __DIR__ . '/includes/layout_app_finish.php'; ?>

