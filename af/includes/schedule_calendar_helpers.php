<?php
declare(strict_types=1);

/**
 * Подписи дат и контраст текста для календаря расписания (неделя/месяц).
 */

function audit_fund_schedule_day_label(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $weekdays = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    $months = [1 => 'янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    $w = $weekdays[(int) date('w', $ts)] ?? '';
    $d = (int) date('j', $ts);
    $m = $months[(int) date('n', $ts)] ?? '';

    return $w . ', ' . $d . ' ' . $m;
}

function audit_fund_schedule_range_label(string $startDate, string $endDate): string
{
    $tsStart = strtotime($startDate);
    $tsEnd = strtotime($endDate);
    if ($tsStart === false || $tsEnd === false) {
        return 'Нет данных';
    }

    $monthsFull = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $d1 = (int) date('j', $tsStart);
    $d2 = (int) date('j', $tsEnd);
    $m2 = $monthsFull[(int) date('n', $tsEnd)] ?? '';
    $y2 = date('Y', $tsEnd);
    if (date('Y-m-d', $tsStart) === date('Y-m-d', $tsEnd)) {
        return $d1 . ' ' . $m2 . ' ' . $y2;
    }

    return $d1 . '–' . $d2 . ' ' . $m2 . ' ' . $y2;
}

function audit_fund_schedule_month_title(string $ym): string
{
    $ts = strtotime($ym . '-01');
    if ($ts === false) {
        return $ym;
    }
    $monthsFull = [1 => 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

    return ($monthsFull[(int) date('n', $ts)] ?? $ym) . ' ' . date('Y', $ts);
}

function audit_fund_schedule_text_color(string $hex): string
{
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        return '#111827';
    }
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    $luma = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

    return $luma > 160 ? '#111827' : '#FFFFFF';
}

/**
 * Публичная витрина: тип слота без текста комментария из БД.
 */
function audit_fund_schedule_public_slot_title(string $entryKind): string
{
    switch ($entryKind) {
        case 'maintenance':
            return 'Техобслуживание';
        case 'approved_request':
            return 'Занятие';
        default:
            return 'Занятие';
    }
}

/**
 * Публичная витрина: только номер помещения (без корпуса и произвольных подписей).
 */
function audit_fund_schedule_public_room_label(string $roomNumber): string
{
    $r = trim($roomNumber);

    return $r === '' ? 'Помещение' : 'Ауд. ' . $r;
}
