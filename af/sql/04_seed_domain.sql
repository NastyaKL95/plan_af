-- Демо-данные под интерфейс страниц (повторный запуск безопасен: INSERT IGNORE по id).
USE `audit_fund`;

INSERT INTO `buildings` (`id`, `code`, `name`, `address`, `sort_order`) VALUES
(1, 'BLD-01', 'Главный корпус', 'ул. Центральная, 1', 1),
(2, 'BLD-02', 'Учебный корпус №2', 'ул. Академическая, 10', 2),
(3, 'BLD-03', 'Лабораторный корпус', 'ул. Научная, 5', 3),
(4, 'BLD-KZ', 'Красная звезда 51А', 'Красная звезда, 51А', 4);

INSERT IGNORE INTO `room_types` (`id`, `code`, `name`, `description`, `sort_order`) VALUES
(1, 'TYPE-01', 'Лекционная', 'Аудитория для потоковых занятий', 1),
(2, 'TYPE-02', 'Семинарская', 'Кабинет для групповой работы', 2),
(3, 'TYPE-03', 'Компьютерный класс', 'Оснащён персональными компьютерами', 3);

-- Аудитории как на странице «Обзор фонда» (management.php)
INSERT IGNORE INTO `rooms` (`id`, `building_id`, `room_type_id`, `room_number`, `color_hex`, `floor`, `capacity`, `operational_status`) VALUES
(1, 4, 1, '105', '#2563eb', 1, 30, 'active'),
(2, 4, 1, '301-1', '#059669', 3, 24, 'active'),
(3, 4, 1, '403', '#dc2626', 4, 50, 'active'),
(4, 4, 3, '17', '#7c3aed', 5, 18, 'maint'),
(5, 4, 1, '18', '#ea580c', 5, 20, 'active'),
(6, 4, 1, '22', '#0d9488', 5, 20, 'active');

INSERT IGNORE INTO `equipment` (`id`, `code`, `name`, `description`) VALUES
(1, 'EQ-PRJ', 'Проектор', 'Мультимедийный проектор'),
(2, 'EQ-PC', 'Рабочее место ПК', 'ПК в компьютерном классе');

INSERT IGNORE INTO `schedule_entries` (`id`, `room_id`, `entry_date`, `time_start`, `time_end`, `comment`) VALUES
(1, 1, '2026-04-07', '09:00:00', '10:30:00', 'Лекция, полный набор, ~28 чел.'),
(2, 3, '2026-04-07', '10:45:00', '12:15:00', 'Практикум, нужен проектор и розетки у столов'),
(3, 2, '2026-04-08', '09:00:00', '10:30:00', 'Демонстрационный эксперимент, первый ряд свободен'),
(4, 6, '2026-04-08', '14:00:00', '15:30:00', 'Лабораторная, сборка у входа'),
(5, 5, '2026-04-09', '11:00:00', '12:30:00', 'Устная практика, деление на подгруппы'),
(6, 1, '2026-04-10', '09:00:00', '10:30:00', 'Лекция, полный набор, ~28 чел.');

INSERT IGNORE INTO `room_maintenance_windows` (`id`, `room_id`, `maint_date`, `time_start`, `time_end`, `reason`, `created_by_user_id`) VALUES
(1, 6, '2026-04-09', '16:00:00', '18:00:00', 'Профилактика проектора', NULL);

INSERT IGNORE INTO `booking_requests` (`id`, `request_number`, `room_id`, `requested_by_user_id`, `applicant_display`, `booking_date`, `time_start`, `time_end`, `comment`, `status`, `submitted_at`) VALUES
(1, '2026-014', 3, NULL, 'Каф. информатики', '2026-04-08', '14:00:00', '15:30:00', 'Семинар для магистрантов', 'pending', '2026-04-05 10:00:00'),
(2, '2026-013', 2, NULL, 'Иванова А. П.', '2026-04-10', '09:00:00', '12:00:00', 'Зачётная неделя', 'approved', '2026-04-04 11:30:00'),
(3, '2026-012', 1, NULL, 'Студ. совет', '2026-04-07', '11:00:00', '13:00:00', 'Собрание актива', 'rejected', '2026-04-03 09:00:00');

ALTER TABLE `buildings` AUTO_INCREMENT = 20;
ALTER TABLE `room_types` AUTO_INCREMENT = 10;
ALTER TABLE `rooms` AUTO_INCREMENT = 100;
ALTER TABLE `equipment` AUTO_INCREMENT = 10;
ALTER TABLE `schedule_entries` AUTO_INCREMENT = 100;
ALTER TABLE `room_maintenance_windows` AUTO_INCREMENT = 10;
ALTER TABLE `booking_requests` AUTO_INCREMENT = 10;
