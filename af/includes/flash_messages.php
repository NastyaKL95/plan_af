<?php
declare(strict_types=1);

/** Единый текст для ошибки CSRF во flash-параметрах URL. */
const AUDIT_FUND_MSG_CSRF = 'Сессия устарела. Обновите страницу и повторите действие.';

/**
 * Текст уведомления по коду из query string или пустая строка.
 *
 * @param array<string, string> $messages
 */
function audit_fund_flash_text(string $code, array $messages, string $fallback): string
{
    if ($code === '') {
        return '';
    }

    return $messages[$code] ?? $fallback;
}

/** @return array<string, string> */
function audit_fund_flash_messages_room_add(): array
{
    return [
        'duplicate' => 'В этом корпусе уже есть аудитория с таким номером.',
        'invalid' => 'Проверьте номер, корпус, тип, этаж и вместимость.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить. Повторите попытку позже.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_room_update(): array
{
    return [
        'duplicate' => 'В этом корпусе уже есть аудитория с таким номером.',
        'invalid' => 'Проверьте номер, корпус, тип, этаж и вместимость.',
        'not_found' => 'Аудитория не найдена.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить изменения. Повторите попытку позже.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_room_delete(): array
{
    return [
        'invalid' => 'Некорректный идентификатор аудитории.',
        'not_found' => 'Аудитория не найдена или уже удалена.',
        'linked' => 'Нельзя удалить аудиторию: с ней связаны расписание или заявки.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось удалить аудиторию. Повторите попытку позже.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_request_create(): array
{
    return [
        'invalid' => 'Проверьте данные заявки и повторите отправку.',
        'need_room' => 'Выберите аудиторию.',
        'need_date' => 'Укажите дату бронирования.',
        'need_time' => 'Укажите время начала и окончания.',
        'need_applicant' => 'Укажите заявителя (подразделение, кафедру или ФИО).',
        'bad_date' => 'Некорректная дата. Выберите дату в формате ГГГГ-ММ-ДД.',
        'bad_time' => 'Некорректный формат времени.',
        'bad_interval' => 'Время окончания должно быть позже начала. Для конца дня укажите 00:00 в поле «до».',
        'applicant_too_long' => 'Поле «Заявитель» не длиннее 255 символов.',
        'comment_too_long' => 'Комментарий слишком длинный.',
        'room_not_found' => 'Выбранная аудитория не найдена. Обновите страницу и выберите снова.',
        'busy' => 'В это время аудитория уже занята: в расписании или есть пересекающаяся заявка (на рассмотрении или одобренная). Выберите другое время.',
        'maint' => 'На выбранное время в этой аудитории запланировано обслуживание. Выберите другое время.',
        'past' => 'Нельзя подать заявку на интервал, который уже закончился. Укажите будущую дату и время.',
        'room_maint' => 'Эта аудитория на обслуживании и недоступна для заявок.',
        'duplicate' => 'Не удалось создать заявку из-за конфликта номера. Повторите попытку.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить заявку. Повторите позже.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_request_status(): array
{
    return [
        'invalid' => 'Некорректные параметры изменения статуса.',
        'not_found' => 'Заявка не найдена.',
        'already_processed' => 'Заявка уже обработана.',
        'busy' => 'Нельзя одобрить: аудитория занята в этот период.',
        'maint' => 'Нельзя одобрить: в это время запланировано обслуживание аудитории.',
        'rejection_reason_required' => 'Укажите причину отклонения заявки.',
        'rejection_reason_too_long' => 'Причина отклонения слишком длинная.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось обновить статус заявки.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_request_update(): array
{
    return [
        'invalid' => 'Некорректные данные заявки. Обновите страницу и попробуйте снова.',
        'need_room' => 'Выберите аудиторию.',
        'need_date' => 'Укажите дату бронирования.',
        'need_time' => 'Укажите время начала и окончания.',
        'need_applicant' => 'Укажите заявителя.',
        'bad_date' => 'Некорректная дата.',
        'bad_time' => 'Некорректный формат времени.',
        'bad_interval' => 'Время окончания должно быть позже начала. Для конца дня — 00:00 в поле «до».',
        'applicant_too_long' => 'Поле «Заявитель» не длиннее 255 символов.',
        'comment_too_long' => 'Комментарий слишком длинный.',
        'room_not_found' => 'Аудитория не найдена. Обновите страницу.',
        'busy' => 'На это время аудитория уже занята.',
        'maint' => 'На выбранное время запланировано обслуживание аудитории.',
        'past' => 'Нельзя сохранить заявку на интервал, который уже закончился. Укажите будущую дату и время.',
        'room_maint' => 'Эта аудитория на обслуживании и недоступна для заявок.',
        'forbidden' => 'Можно редактировать только свои заявки в статусе "На рассмотрении".',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось обновить заявку.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_maintenance(): array
{
    return [
        'invalid' => 'Проверьте аудиторию, дату и временной интервал обслуживания.',
        'past' => 'Нельзя добавить окно обслуживания в прошедшее время.',
        'busy' => 'Нельзя добавить окно обслуживания: на это время уже есть занятия или заявки.',
        'overlap' => 'Такое окно обслуживания уже пересекается с существующим.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить окно обслуживания.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_maintenance_delete(): array
{
    return [
        'invalid' => 'Некорректный идентификатор окна обслуживания.',
        'not_found' => 'Окно обслуживания не найдено.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось удалить окно обслуживания.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_schedule_move(): array
{
    return [
        'invalid' => 'Некорректные параметры переноса записи расписания.',
        'not_found' => 'Запись расписания не найдена.',
        'past' => 'Нельзя переносить запись расписания в прошедшее время.',
        'busy' => 'Нельзя перенести: в новом слоте есть пересечение.',
        'maint' => 'Нельзя перенести: на новое время запланировано обслуживание.',
        'bad_time' => 'Укажите корректное время начала и окончания (формат ЧЧ:ММ).',
        'bad_interval' => 'Время окончания должно быть позже времени начала (для интервала до полуночи укажите окончание 00:00).',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось перенести запись расписания.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_schedule_delete(): array
{
    return [
        'invalid' => 'Некорректные параметры удаления записи.',
        'not_found' => 'Запись не найдена или уже удалена.',
        'not_approved' => 'Можно снять только одобренную заявку из расписания.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось удалить запись расписания.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_building_add(): array
{
    return [
        'duplicate' => 'Не удалось сохранить корпус из-за конфликта данных. Повторите попытку.',
        'invalid' => 'Проверьте название и адрес корпуса.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить корпус. Повторите попытку позже.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_building_update(): array
{
    return [
        'invalid' => 'Проверьте название и адрес корпуса.',
        'not_found' => 'Корпус не найден.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить изменения корпуса.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_building_delete(): array
{
    return [
        'invalid' => 'Некорректный идентификатор корпуса.',
        'not_found' => 'Корпус не найден или уже удалён.',
        'linked' => 'Нельзя удалить корпус: к нему привязаны аудитории.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось удалить корпус.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_room_type_add(): array
{
    return [
        'duplicate' => 'Не удалось сохранить тип из-за конфликта данных. Повторите попытку.',
        'invalid' => 'Проверьте название и описание типа.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить тип аудитории.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_room_type_update(): array
{
    return [
        'invalid' => 'Проверьте название и описание типа.',
        'not_found' => 'Тип аудитории не найден.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить изменения типа аудитории.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_room_type_delete(): array
{
    return [
        'invalid' => 'Некорректный идентификатор типа аудитории.',
        'not_found' => 'Тип аудитории не найден или уже удалён.',
        'linked' => 'Нельзя удалить тип: к нему привязаны аудитории.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось удалить тип аудитории.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_logout(): array
{
    return [
        'get' => 'Выход выполняется только через кнопку «Выйти» в шапке. Обновите страницу.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_profile(): array
{
    return [
        'display_name_empty' => 'Укажите имя.',
        'display_name_len' => 'Имя: от 1 до 191 символов.',
        'display_name_chars' => 'Имя: допустимы буквы, цифры, пробел, апостроф, точка и дефис.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось сохранить имя. Повторите попытку позже.',
        'not_found' => 'Учётная запись не найдена или отключена.',
        'invalid' => 'Некорректный запрос.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_register(): array
{
    return [
        'display_name_empty' => 'Укажите имя.',
        'display_name_len' => 'Имя: от 1 до 191 символов.',
        'display_name_chars' => 'Имя: допустимы буквы, цифры, пробел, апостроф, точка и дефис.',
        'username_len' => 'Логин: от 3 до 64 символов.',
        'username_chars' => 'Логин: только латиница, цифры, точка, дефис и подчёркивание.',
        'email_invalid' => 'Укажите корректный e-mail или оставьте поле пустым.',
        'password_short' => 'Пароль не короче 8 символов.',
        'password_mismatch' => 'Пароли не совпадают.',
        'user_exists' => 'Пользователь с таким логином уже есть.',
        'email_exists' => 'Этот e-mail уже зарегистрирован.',
        'duplicate' => 'Такой логин или e-mail уже занят.',
        'db' => 'Не удалось сохранить данные. Проверьте подключение к базе.',
        'csrf' => 'Сессия устарела. Обновите страницу и повторите отправку формы.',
        'registration_closed' => 'Открытая регистрация отключена. Обратитесь к администратору за учётной записью.',
        'rate_limited' => 'Слишком много попыток регистрации с вашего адреса. Подождите около 15 минут и попробуйте снова.',
    ];
}

/** @return array<string, string> */
function audit_fund_flash_messages_audit_log_purge(): array
{
    return [
        'invalid' => 'Проверьте режим очистки и даты.',
        'confirm' => 'Для полной очистки журнала отметьте подтверждение.',
        'csrf' => AUDIT_FUND_MSG_CSRF,
        'db' => 'Не удалось очистить журнал. Повторите попытку позже.',
    ];
}
