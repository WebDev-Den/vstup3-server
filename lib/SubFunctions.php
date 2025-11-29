<?php

namespace App\Core;
class Validate
{

    static function roles($roleList = null)
    {
        $token = self::getAuthorizationBearer();
        if (!$token) {
            \App\Core\Response::badRequest('Token not found');
        }
        global $JWT;
        $user = $JWT->validateToken($token);
        if (!empty($roleList)) {
            $roleList = is_array($roleList) ? $roleList : [$roleList];
            if (count($roleList) > 0) {
                if (empty($user->role_name)) {
                    \App\Core\Response::badRequest('Role not found');
                }
                if (!in_array($user->role_name, $roleList)) {
                    \App\Core\Response::forbidden('Does not have access for the specified role');
                }
            }
        }
        return (array)$user;
    }

    static function getAuthorizationBearer()
    {

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
            return base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . ($_SERVER['PHP_AUTH_PW'] ?? ''));
        } else {
            return null;
        }
        if (!empty($auth) && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    static function getClientIP()
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Якщо є кілька IP (через кому)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }
}

class PasswordManager
{
    // Константи для алгоритмів
    const ALGORITHM_DEFAULT = PASSWORD_DEFAULT;
    const ALGORITHM_BCRYPT = PASSWORD_BCRYPT;
    const ALGORITHM_ARGON2I = PASSWORD_ARGON2I;
    const ALGORITHM_ARGON2ID = PASSWORD_ARGON2ID;

    // Налаштування за замовчуванням
    private static $defaultOptions = [
        'bcrypt' => [
            'cost' => 12
        ],
        'argon2i' => [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,
            'threads' => 3
        ],
        'argon2id' => [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,
            'threads' => 3
        ]
    ];

    /**
     * Створення хешу пароля (рекомендований метод)
     */
    public static function hash($password, $algorithm = null, $options = [])
    {
        if (empty($password)) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }

        $algorithm = $algorithm ?? self::ALGORITHM_DEFAULT;

        // Використання налаштувань за замовчуванням якщо не передані
        if (empty($options)) {
            switch ($algorithm) {
                case PASSWORD_BCRYPT:
                    $options = self::$defaultOptions['bcrypt'];
                    break;
                case PASSWORD_ARGON2I:
                    $options = self::$defaultOptions['argon2i'];
                    break;
                case PASSWORD_ARGON2ID:
                    $options = self::$defaultOptions['argon2id'];
                    break;
            }
        }

        $hash = password_hash($password, $algorithm, $options);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password');
        }

        return $hash;
    }

    /**
     * Перевірка пароля
     */
    public static function verify($password, $hash)
    {
        if (empty($password) || empty($hash)) {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Перевірка чи потрібно оновити хеш
     */
    public static function needsRehash($hash, $algorithm = null, $options = [])
    {
        $algorithm = $algorithm ?? self::ALGORITHM_DEFAULT;
        return password_needs_rehash($hash, $algorithm, $options);
    }

    /**
     * Комплексна автентифікація з автооновленням хешу
     */
    public static function authenticate($password, $storedHash, $algorithm = null)
    {
        $result = [
            'success' => false,
            'needs_rehash' => false,
            'new_hash' => null
        ];

        // Перевірка пароля
        if (!self::verify($password, $storedHash)) {
            return $result;
        }

        $result['success'] = true;

        // Перевірка чи потрібно оновити хеш
        $algorithm = $algorithm ?? self::ALGORITHM_DEFAULT;
        if (self::needsRehash($storedHash, $algorithm)) {
            $result['needs_rehash'] = true;
            $result['new_hash'] = self::hash($password, $algorithm);
        }

        return $result;
    }

    /**
     * Створення хешу з Bcrypt
     */
    public static function hashBcrypt($password, $cost = 12)
    {
        return self::hash($password, self::ALGORITHM_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Створення хешу з Argon2ID (найбезпечніший)
     */
    public static function hashArgon2ID($password, $memoryCost = 65536, $timeCost = 4, $threads = 3)
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new \RuntimeException('Argon2ID is not available in this PHP version');
        }

        return self::hash($password, self::ALGORITHM_ARGON2ID, [
            'memory_cost' => $memoryCost,
            'time_cost' => $timeCost,
            'threads' => $threads
        ]);
    }

    /**
     * Генерація безпечного випадкового пароля
     */
    public static function generateSecure($length = 16, $includeSymbols = true)
    {
        if ($length < 8) {
            throw new \InvalidArgumentException('Password length must be at least 8 characters');
        }

        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $chars = $lowercase . $uppercase . $numbers;
        if ($includeSymbols) {
            $chars .= $symbols;
        }

        $password = '';
        $charsLength = strlen($chars);

        // Гарантувати хоча б один символ кожного типу
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];

        if ($includeSymbols) {
            $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        }

        // Заповнити решту випадковими символами
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }

        // Перемішати символи
        return str_shuffle($password);
    }

    /**
     * Генерація тимчасового пароля
     */
    public static function generateTemporary($length = 12)
    {
        // Виключити схожі символи для кращої читабельності
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        $charsLength = strlen($chars);

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }

        return $password;
    }

    /**
     * Перевірка сили пароля
     */
    public static function getStrength($password)
    {
        if (empty($password)) {
            return [
                'score' => 0,
                'level' => 'none',
                'feedback' => ['Password is required']
            ];
        }

        $score = 0;
        $feedback = [];
        $length = strlen($password);

        // Довжина
        if ($length >= 8) $score += 20;
        if ($length >= 12) $score += 10;
        if ($length >= 16) $score += 10;
        if ($length < 8) $feedback[] = 'Use at least 8 characters';

        // Різні типи символів
        if (preg_match('/[a-z]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Add lowercase letters';
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Add uppercase letters';
        }

        if (preg_match('/[0-9]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Add numbers';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 15;
        } else {
            $feedback[] = 'Add special characters';
        }

        // Штрафи
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score -= 10;
            $feedback[] = 'Avoid repeated characters';
        }

        if (preg_match('/123|abc|qwe|password/i', $password)) {
            $score -= 15;
            $feedback[] = 'Avoid common patterns';
        }

        $score = max(0, min(100, $score));

        // Визначення рівня
        if ($score < 30) $level = 'weak';
        elseif ($score < 60) $level = 'medium';
        elseif ($score < 80) $level = 'strong';
        else $level = 'very_strong';

        return [
            'score' => $score,
            'level' => $level,
            'feedback' => $feedback
        ];
    }

    /**
     * Міграція зі старих хешів
     */
    public static function migrateFromLegacy($password, $legacyHash, $legacyMethod = 'md5')
    {
        $isValid = false;

        switch (strtolower($legacyMethod)) {
            case 'md5':
                $isValid = (md5($password) === $legacyHash);
                break;
            case 'sha1':
                $isValid = (sha1($password) === $legacyHash);
                break;
            case 'sha256':
                $isValid = (hash('sha256', $password) === $legacyHash);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported legacy method: {$legacyMethod}");
        }

        if ($isValid) {
            return [
                'valid' => true,
                'new_hash' => self::hash($password)
            ];
        }

        return ['valid' => false, 'new_hash' => null];
    }

    /**
     * Отримання інформації про хеш
     */
    public static function getInfo($hash)
    {
        return password_get_info($hash);
    }
}

// ============================================================================
// ПРИКЛАДИ ВИКОРИСТАННЯ
// ============================================================================
/*
// 1. БАЗОВЕ ВИКОРИСТАННЯ

echo "=== БАЗОВЕ ВИКОРИСТАННЯ ===\n";

// Створення хешу
$password = 'MySecurePassword123!';
$hash = PasswordManager::hash($password);
echo "Original: {$password}\n";
echo "Hash: {$hash}\n";

// Перевірка пароля
$isValid = PasswordManager::verify($password, $hash);
echo "Valid: " . ($isValid ? 'YES' : 'NO') . "\n\n";

// 2. РІЗНІ АЛГОРИТМИ

echo "=== РІЗНІ АЛГОРИТМИ ===\n";

$password = 'TestPassword123';

// Bcrypt
$bcryptHash = PasswordManager::hashBcrypt($password, 12);
echo "Bcrypt: {$bcryptHash}\n";

// Argon2ID (якщо доступний)
try {
    $argonHash = PasswordManager::hashArgon2ID($password);
    echo "Argon2ID: {$argonHash}\n";
} catch (Exception $e) {
    echo "Argon2ID not available: " . $e->getMessage() . "\n";
}

// 3. АВТЕНТИФІКАЦІЯ З АВТООНОВЛЕННЯМ

echo "\n=== АВТЕНТИФІКАЦІЯ ===\n";

$password = 'UserPassword123';
$oldHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]); // Старий хеш

$auth = PasswordManager::authenticate($password, $oldHash);
echo "Authentication successful: " . ($auth['success'] ? 'YES' : 'NO') . "\n";
echo "Needs rehash: " . ($auth['needs_rehash'] ? 'YES' : 'NO') . "\n";
if ($auth['new_hash']) {
    echo "New hash: {$auth['new_hash']}\n";
}

// 4. ГЕНЕРАЦІЯ ПАРОЛІВ

echo "\n=== ГЕНЕРАЦІЯ ПАРОЛІВ ===\n";

// Безпечний пароль
$securePassword = PasswordManager::generateSecure(16, true);
echo "Secure password: {$securePassword}\n";

// Тимчасовий пароль
$tempPassword = PasswordManager::generateTemporary(10);
echo "Temporary password: {$tempPassword}\n";

// 5. ПЕРЕВІРКА СИЛИ ПАРОЛЯ

echo "\n=== ПЕРЕВІРКА СИЛИ ПАРОЛЯ ===\n";

$passwords = [
    '123456',
    'password',
    'MyPassword123',
    'MyStr0ng!P@ssw0rd'
];

foreach ($passwords as $pwd) {
    $strength = PasswordManager::getStrength($pwd);
    echo "Password: '{$pwd}' - Score: {$strength['score']}, Level: {$strength['level']}\n";
    if (!empty($strength['feedback'])) {
        echo "  Feedback: " . implode(', ', $strength['feedback']) . "\n";
    }
}
 */

class Response
{
    /**
     * Успішна відповідь 200 OK
     */
    public static function success($data = null, $message = null)
    {
        http_response_code(200);
        self::output([
            'success' => true,
            'status_code' => 200,
            'message' => $message ?? 'Success',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Створено 201 Created
     */
    public static function created($data = null, $message = null)
    {
        http_response_code(201);
        self::output([
            'success' => true,
            'status_code' => 201,
            'message' => $message ?? 'Created successfully',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Прийнято 202 Accepted
     */
    public static function accepted($data = null, $message = null)
    {
        http_response_code(202);
        self::output([
            'success' => true,
            'status_code' => 202,
            'message' => $message ?? 'Accepted',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Немає контенту 204 No Content
     */
    public static function noContent()
    {
        http_response_code(204);
        exit;
    }

    /**
     * Неправильний запит 400 Bad Request
     */
    public static function badRequest($message = null, $errors = null)
    {
        http_response_code(400);
        self::output([
            'success' => false,
            'status_code' => 400,
            'message' => $message ?? 'Bad Request',
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Не авторизовано 401 Unauthorized
     */
    public static function unauthorized($message = null)
    {
        http_response_code(401);
        self::output([
            'success' => false,
            'status_code' => 401,
            'message' => $message ?? 'Unauthorized',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Заборонено 403 Forbidden
     */
    public static function forbidden($message = null, $key = null)
    {
        http_response_code(403);
        $response = [
            'success' => false,
            'status_code' => 403,
            'message' => $message ?? 'Forbidden',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($key) {
            $response['key'] = $key;
        }

        self::output($response);
    }

    /**
     * Не знайдено 404 Not Found
     */
    public static function notFound($message = null)
    {
        http_response_code(404);
        self::output([
            'success' => false,
            'status_code' => 404,
            'message' => $message ?? 'Not Found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Метод не дозволений 405 Method Not Allowed
     */
    public static function methodNotAllowed($message = null, $allowed = null)
    {
        http_response_code(405);
        if ($allowed) {
            header('Allow: ' . implode(', ', $allowed));
        }
        self::output([
            'success' => false,
            'status_code' => 405,
            'message' => $message ?? 'Method Not Allowed',
            'allowed_methods' => $allowed,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Конфлікт 409 Conflict
     */
    public static function conflict($message = null, $data = null)
    {
        http_response_code(409);
        self::output([
            'success' => false,
            'status_code' => 409,
            'message' => $message ?? 'Conflict',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Необробна сутність 422 Unprocessable Entity
     */
    public static function unprocessable($message = null, $errors = null)
    {
        http_response_code(422);
        self::output([
            'success' => false,
            'status_code' => 422,
            'message' => $message ?? 'Validation Failed',
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Забагато запитів 429 Too Many Requests
     */
    public static function tooManyRequests($message = null, $retryAfter = null)
    {
        http_response_code(429);
        if ($retryAfter) {
            header('Retry-After: ' . $retryAfter);
        }
        self::output([
            'success' => false,
            'status_code' => 429,
            'message' => $message ?? 'Too Many Requests',
            'retry_after' => $retryAfter,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Внутрішня помилка сервера 500 Internal Server Error
     */
    public static function serverError($message = null, $debug = null)
    {
        http_response_code(500);
        $response = [
            'success' => false,
            'status_code' => 500,
            'message' => $message ?? 'Internal Server Error',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Додати debug інформацію тільки в режимі розробки
        if ($debug && (defined('DEBUG') && DEBUG === true)) {
            $response['debug'] = $debug;
        }

        self::output($response);
    }

    /**
     * Сервіс недоступний 503 Service Unavailable
     */
    public static function serviceUnavailable($message = null, $retryAfter = null)
    {
        http_response_code(503);
        if ($retryAfter) {
            header('Retry-After: ' . $retryAfter);
        }
        self::output([
            'success' => false,
            'status_code' => 503,
            'message' => $message ?? 'Service Unavailable',
            'retry_after' => $retryAfter,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Універсальний метод для будь-якого HTTP коду
     */
    public static function custom($code, $message, $data = null, $success = null)
    {
        http_response_code($code);

        // Автоматично визначити статус за кодом
        if ($success === null) {
            $success = $code >= 200 && $code < 300;
        }

        self::output([
            'success' => $success,
            'status_code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Вивести JSON та завершити виконання
     */
    private static function output($data)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Логування помилок (опційно)
     */
    public static function logError($message, $context = [])
    {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        error_log('API Error: ' . json_encode($log));
    }
}

class NumberToWordsUkrainian
{
    private $ones = [
        '', 'один', 'два', 'три', 'чотири', 'п\'ять', 'шість', 'сім', 'вісім', 'дев\'ять',
        'десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять', 'п\'ятнадцять',
        'шістнадцять', 'сімнадцять', 'вісімнадцять', 'дев\'ятнадцять'
    ];

    private $tens = [
        '', '', 'двадцять', 'тридцять', 'сорок', 'п\'ятдесят', 'шістдесят',
        'сімдесят', 'вісімдесят', 'дев\'яносто'
    ];

    private $hundreds = [
        '', 'сто', 'двісті', 'триста', 'чотириста', 'п\'ятсот', 'шістсот',
        'сімсот', 'вісімсот', 'дев\'ятсот'
    ];

    private $thousands = [
        ['', '', ''],
        ['тисяча', 'тисячі', 'тисяч'],
        ['мільйон', 'мільйони', 'мільйонів'],
        ['мільярд', 'мільярди', 'мільярдів'],
        ['трильйон', 'трильйони', 'трильйонів']
    ];

    /**
     * Основний метод перетворення числа в текст
     */
    public function convert($number)
    {
        if ($number == 0) return 'нуль';

        $number = abs($number);
        $result = '';
        $groups = [];

        // Розбиваємо число на групи по 3 цифри
        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intval($number / 1000);
        }

        // Обробляємо кожну групу
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $group = $groups[$i];
            if ($group == 0) continue;

            $groupText = $this->convertGroup($group, $i);
            if ($result) $result .= ' ';
            $result .= $groupText;
        }

        return trim($result);
    }

    /**
     * Перетворення групи з 3 цифр
     */
    private function convertGroup($number, $groupIndex)
    {
        $result = '';

        // Сотні
        $hundreds = intval($number / 100);
        if ($hundreds > 0) {
            $result .= $this->hundreds[$hundreds];
        }

        // Десятки та одиниці
        $remainder = $number % 100;

        if ($remainder >= 20) {
            $tens = intval($remainder / 10);
            $ones = $remainder % 10;

            if ($result) $result .= ' ';
            $result .= $this->tens[$tens];

            if ($ones > 0) {
                if ($result) $result .= ' ';
                // Для тисяч використовуємо жіночий рід
                if ($groupIndex == 1 && $ones <= 2) {
                    $result .= $ones == 1 ? 'одна' : 'дві';
                } else {
                    $result .= $this->ones[$ones];
                }
            }
        } elseif ($remainder > 0) {
            if ($result) $result .= ' ';
            // Для тисяч використовуємо жіночий рід
            if ($groupIndex == 1 && $remainder <= 2) {
                $result .= $remainder == 1 ? 'одна' : 'дві';
            } else {
                $result .= $this->ones[$remainder];
            }
        }

        // Додаємо назву групи (тисячі, мільйони тощо)
        if ($groupIndex > 0) {
            $result .= ' ' . $this->getGroupName($number, $groupIndex);
        }

        return $result;
    }

    /**
     * Отримання назви групи з правильним відмінком
     */
    private function getGroupName($number, $groupIndex)
    {
        $remainder = $number % 100;

        if ($remainder >= 11 && $remainder <= 19) {
            return $this->thousands[$groupIndex][2]; // багато (тисяч, мільйонів)
        }

        $lastDigit = $number % 10;

        if ($lastDigit == 1) {
            return $this->thousands[$groupIndex][0]; // один (тисяча, мільйон)
        } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
            return $this->thousands[$groupIndex][1]; // кілька (тисячі, мільйони)
        } else {
            return $this->thousands[$groupIndex][2]; // багато (тисяч, мільйонів)
        }
    }

    /**
     * Перетворення суми в гривнях з копійками
     */
    public function convertMoney($amount)
    {
        $hryvnias = intval($amount);
        $kopecks = round(($amount - $hryvnias) * 100);

        $result = $this->convert($hryvnias);
        $result .= ' ' . $this->getCurrencyName($hryvnias, 'hryvnia');

        // Завжди додаємо копійки (навіть якщо 0)
        $result .= ' ' . $this->convert($kopecks);
        $result .= ' ' . $this->getCurrencyName($kopecks, 'kopeck');

        return $result;
    }

    /**
     * Отримання назви валюти з правильним відмінком
     */
    private function getCurrencyName($number, $type)
    {
        $currencies = [
            'hryvnia' => ['гривня', 'гривні', 'гривень'],
            'kopeck' => ['копійка', 'копійки', 'копійок']
        ];

        $remainder = $number % 100;

        if ($remainder >= 11 && $remainder <= 19) {
            return $currencies[$type][2];
        }

        $lastDigit = $number % 10;

        if ($lastDigit == 1) {
            return $currencies[$type][0];
        } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
            return $currencies[$type][1];
        } else {
            return $currencies[$type][2];
        }
    }
}

/*
// Приклади використання:
$converter = new NumberToWordsUkrainian();

echo "<h3>Приклади перетворення чисел:</h3>";
$numbers = [125, 1001, 2345, 38500, 154000, 1234567];

foreach ($numbers as $number) {
    echo "<strong>{$number}:</strong> " . $converter->convert($number) . "<br>";
}

echo "<h3>Приклади сум у гривнях:</h3>";
$amounts = [38500.00, 21250.50, 1234.99, 100.05, 25500.00];

foreach ($amounts as $amount) {
    echo "<strong>{$amount} грн:</strong> " . $converter->convertMoney($amount) . "<br>";
}

// Функція для швидкого використання
function numberToWords($number)
{
    $converter = new NumberToWordsUkrainian();
    return $converter->convert($number);
}

function moneyToWords($amount)
{
    $converter = new NumberToWordsUkrainian();
    return $converter->convertMoney($amount);
}

echo "<h3>Швидке використання:</h3>";
echo "38500: " . numberToWords(38500) . "<br>";
echo "25500.00 грн: " . moneyToWords(25500.00) . "<br>";
*/

class AddressShortener
{
    private $abbreviations = [
        // Області
        'область' => 'обл.',
        'обл' => 'обл.',

        // Райони
        'район' => 'р-н',
        'р-н' => 'р-н',

        // Міста та селища
        'місто' => 'м.',
        'м.' => 'м.',
        'м' => 'м.',
        'село' => 'с.',
        'с.' => 'с.',
        'с' => 'с.',
        'селище' => 'смт.',
        'смт.' => 'смт.',
        'смт' => 'смт.',
        'селище міського типу' => 'смт.',

        // Вулиці
        'вулиця' => 'вул.',
        'вул.' => 'вул.',
        'вул' => 'вул.',
        'провулок' => 'пров.',
        'пров.' => 'пров.',
        'пров' => 'пров.',
        'проспект' => 'просп.',
        'просп.' => 'просп.',
        'просп' => 'просп.',
        'площа' => 'пл.',
        'пл.' => 'пл.',
        'пл' => 'пл.',
        'бульвар' => 'бул.',
        'бул.' => 'бул.',
        'бул' => 'бул.',
        'набережна' => 'наб.',
        'наб.' => 'наб.',
        'наб' => 'наб.',

        // Будинки та квартири
        'будинок' => 'буд.',
        'буд.' => 'буд.',
        'буд' => 'буд.',
        'будівля' => 'буд.',
        'дім' => 'буд.',
        'квартира' => 'кв.',
        'кв.' => 'кв.',
        'кв' => 'кв.',
        'офіс' => 'оф.',
        'оф.' => 'оф.',
        'оф' => 'оф.',
        'приміщення' => 'прим.',
        'прим.' => 'прим.',
        'прим' => 'прим.',
    ];

    /**
     * Скорочення повної адреси
     */
    public function shortenAddress($address)
    {
        // Очищаємо від зайвих пробілів
        $address = trim($address);

        // Розділяємо на частини по комах
        $parts = array_map('trim', explode(',', $address));

        $shortenedParts = [];

        foreach ($parts as $part) {
            $shortenedParts[] = $this->shortenPart($part);
        }

        return implode(', ', $shortenedParts);
    }

    /**
     * Скорочення окремої частини адреси
     */
    private function shortenPart($part)
    {
        $part = trim($part);

        // Розділяємо частину на слова
        $words = preg_split('/(\s+)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($words); $i++) {
            $word = trim($words[$i]);

            // Пропускаємо пробіли та порожні елементи
            if (empty($word) || ctype_space($words[$i])) {
                continue;
            }

            // Перевіряємо кожне слово на скорочення
            foreach ($this->abbreviations as $full => $short) {
                // Точне співпадіння слова (регістронезалежно)
                if (mb_strtolower($word) === mb_strtolower($full)) {
                    $words[$i] = $short;
                    break;
                }
                // Або слово з крапкою в кінці
                if (mb_strtolower(rtrim($word, '.')) === mb_strtolower($full)) {
                    $words[$i] = $short;
                    break;
                }
                // Або слово з комою в кінці
                if (mb_strtolower(rtrim($word, ',')) === mb_strtolower($full)) {
                    $words[$i] = $short . ',';
                    break;
                }
            }
        }

        return implode('', $words);
    }

    /**
     * Додавання нового скорочення
     */
    public function addAbbreviation($full, $short)
    {
        $this->abbreviations[mb_strtolower($full)] = $short;
    }

    /**
     * Отримання всіх скорочень
     */
    public function getAbbreviations()
    {
        return $this->abbreviations;
    }
}



/*
// Приклад використання:
$shortener = new AddressShortener();

// Тестові адреси
$addresses = [
    'Львівська область, Шептицький район, м. Шептицький, вул. Корольова, буд.5, кв. 93',
    'Київська область, Броварський район, місто Бровари, вулиця Шевченка, будинок 15, квартира 22',
    'Одеська область, село Петрівка, провулок Миру, дім 3',
    'Харківська область, м. Харків, проспект Науки, будівля 45, офіс 12',
    'Дніпропетровська область, Кривий Ріг, площа Визволення, буд. 1',
    'Тернопільська область, м. Тернопіль, вул. Областна, буд. 10', // Тест на "область" в назві вулиці
    'Львівська область, Районний центр, селище Нове, вул. Районна, дім 5', // Тест на "район" в назві
    'Київська область, м. Київ, вул. Міська, буд. 7, кв. 15' // Тест на подібні слова
];

echo "<h3>Результати скорочення адрес:</h3>\n";

foreach ($addresses as $address) {
    $shortened = $shortener->shortenAddress($address);
    echo "<strong>Оригінал:</strong> {$address}<br>\n";
    echo "<strong>Скорочено:</strong> {$shortened}<br><br>\n";
}

// Функція для одноразового використання
function shortenAddress($address) {
    $shortener = new AddressShortener();
    return $shortener->shortenAddress($address);
}

// Приклад використання функції
$testAddress = 'Львівська область, Шептицький район, м. Шептицький, вул. Корольова, буд.5, кв. 93';
echo "<h3>Швидке скорочення:</h3>\n";
echo shortenAddress($testAddress);
*/