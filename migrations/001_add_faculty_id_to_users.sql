-- Міграція: Додавання підтримки ролі декана факультету
-- Дата: 2025-11-29
-- Опис: Додає поле faculty_id до таблиці users для прив'язки деканів до факультетів

-- Перевірка та додавання колонки faculty_id
ALTER TABLE users
ADD COLUMN IF NOT EXISTS faculty_id VARCHAR(50) NULL
COMMENT 'ID факультету для ролі декана';

-- Додавання індексу для швидкого пошуку
CREATE INDEX IF NOT EXISTS idx_users_faculty_id ON users(faculty_id);

-- Додавання індексу для швидкого пошуку за роллю
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Примітки:
-- 1. Поле faculty_id NULL за замовчуванням для зворотної сумісності
-- 2. Для існуючих користувачів поле залишиться NULL
-- 3. Тільки декани (role='dean') матимуть значення faculty_id
-- 4. faculty_id відповідає ключам з масиву faculty (наприклад: 'fit', 'fmf', etc.)

-- Приклади використання:
-- UPDATE users SET role='dean', faculty_id='fit' WHERE id=5;
-- SELECT * FROM users WHERE role='dean' AND faculty_id='fit';
