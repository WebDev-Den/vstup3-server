-- Міграція: Створення таблиць для факультетів та спеціальностей
-- Дата: 2025-11-29
-- Опис: Створює структуру для зберігання факультетів та їх спеціальностей

-- Таблиця факультетів
CREATE TABLE IF NOT EXISTS faculties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_key VARCHAR(50) NOT NULL UNIQUE COMMENT 'Унікальний ключ факультету (наприклад: fit, fmf)',
    name VARCHAR(255) NOT NULL COMMENT 'Повна назва факультету',
    name_min VARCHAR(100) NOT NULL COMMENT 'Скорочена назва факультету',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_faculty_key (faculty_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблиця спеціальностей
CREATE TABLE IF NOT EXISTS specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL COMMENT 'ID факультету',
    specialty_key VARCHAR(50) NOT NULL COMMENT 'Унікальний ключ спеціальності в межах факультету (наприклад: f2, f3)',
    name VARCHAR(255) NOT NULL COMMENT 'Повна назва спеціальності',
    name_small VARCHAR(100) NULL COMMENT 'Скорочена назва',
    specialty_code VARCHAR(50) NOT NULL COMMENT 'Код спеціальності',
    educational_program VARCHAR(255) NOT NULL COMMENT 'Освітня програма',
    specialty_name VARCHAR(255) NULL COMMENT 'Альтернативна назва спеціальності',
    type_data JSON NULL COMMENT 'Дані про типи (bachelor, master)',
    pricing_data JSON NULL COMMENT 'Дані про ціноутворення',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_specialty_per_faculty (faculty_id, specialty_key),
    INDEX idx_specialty_key (specialty_key),
    INDEX idx_faculty_id (faculty_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Приклад даних для тестування
INSERT INTO faculties (faculty_key, name, name_min) VALUES
('fit', 'Факультет інформаційних технологій', 'ФІТ')
ON DUPLICATE KEY UPDATE name=VALUES(name), name_min=VALUES(name_min);

INSERT INTO specialties (faculty_id, specialty_key, name, name_small, specialty_code, educational_program, specialty_name, type_data, pricing_data) VALUES
(
    (SELECT id FROM faculties WHERE faculty_key='fit'),
    'f2',
    'F2 – Інженерія програмного забезпечення',
    'ІПЗ',
    'F2',
    'Інженерія програмного забезпечення',
    'Інженерія програмного забезпечення',
    JSON_OBJECT(
        'bachelor', JSON_OBJECT(
            'name', 'Бакалавр',
            'credits', 240,
            'hash_shortened', false,
            'hash_correspondence', false,
            'accreditation', JSON_OBJECT(
                'status', true,
                'date', '2024-06-15'
            )
        ),
        'master', JSON_OBJECT(
            'name', 'Магістр',
            'credits', 90,
            'hash_shortened', false,
            'hash_correspondence', false,
            'accreditation', JSON_OBJECT(
                'status', true,
                'date', '2024-06-15'
            )
        )
    ),
    JSON_OBJECT(
        'bachelor', JSON_OBJECT(
            'inPerson', JSON_OBJECT(
                'y1', 28000,
                'y2', 29000,
                'y3', 30000,
                'y4', 31000
            )
        ),
        'master', JSON_OBJECT(
            'inPerson', JSON_OBJECT(
                'y1', 32000,
                'y2', 33000
            )
        )
    )
)
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    name_small=VALUES(name_small),
    type_data=VALUES(type_data),
    pricing_data=VALUES(pricing_data);

-- Примітки:
-- 1. Використовуємо JSON для зберігання складних структур type та pricing
-- 2. CASCADE видалення - при видаленні факультету видаляються всі його спеціальності
-- 3. Унікальність specialty_key в межах факультету забезпечується composite unique key
