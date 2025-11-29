<?php
// Пример использования

// 1. Создаем экземпляр класса с секретным ключом (для HS256)
$jwtManager = new JWT_Secret('ваш_секретный_ключ_должен_быть_достаточно_длинным');

// 2. Создаем токен с пользовательскими данными
$userData = [
    'user_id' => 123,
    'email' => 'user@example.com',
    'role' => 'admin'
];

$token = $jwtManager->generateToken($userData, 7200); // токен на 2 часа
echo "Созданный токен: " . $token . PHP_EOL;

// 3. Проверяем и декодируем токен
$decoded = $jwtManager->validateToken($token);
if ($decoded) {
    echo "Токен действителен. Данные пользователя:" . PHP_EOL;
    echo "ID: " . $decoded->user_id . PHP_EOL;
    echo "Email: " . $decoded->email . PHP_EOL;
    echo "Роль: " . $decoded->role . PHP_EOL;
    echo "Срок действия до: " . date('Y-m-d H:i:s', $decoded->exp) . PHP_EOL;
} else {
    echo "Токен недействителен!" . PHP_EOL;
}

// Пример использования асимметричного шифрования (RS256)
// Обратите внимание, что для этого нужно сгенерировать пару ключей
function exampleWithRSA()
{
    // Генерация ключевой пары для тестирования
    $privateKeyRes = openssl_pkey_new([
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    // Получаем закрытый ключ
    openssl_pkey_export($privateKeyRes, $privateKey);

    // Получаем публичный ключ
    $publicKeyData = openssl_pkey_get_details($privateKeyRes);
    $publicKey = $publicKeyData['key'];

    // Создаем экземпляр класса с парой ключей
    $jwtManagerRSA = new JWT_Secret($privateKey, $publicKey, 'RS256');

    // Тестируем создание и проверку токена
    $userData = ['user_id' => 456, 'username' => 'admin'];
    $token = $jwtManagerRSA->generateToken($userData);

    $decoded = $jwtManagerRSA->validateToken($token);
    if ($decoded) {
        echo "RS256 токен действителен" . PHP_EOL;
    }
}

// Раскомментируйте для тестирования RSA
// exampleWithRSA();