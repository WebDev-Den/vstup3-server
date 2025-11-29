<?php

require_once __DIR__ . '/src/JWT.php';
require_once __DIR__ . '/src/Key.php';
require_once __DIR__ . '/src/ExpiredException.php';
require_once __DIR__ . '/src/SignatureInvalidException.php';
require_once __DIR__ . '/src/BeforeValidException.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWT_Secret
{
    private $privateKey;
    private $publicKey;
    private $algorithm;

    public function __construct($privateKey, $publicKey = null, $algorithm = 'HS256')
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey ?: $privateKey; // Для симметричных алгоритмов (HS256) ключи совпадают
        $this->algorithm = $algorithm;
    }

    /**
     * Создание JWT токена
     *
     * @param array $payload Данные для кодирования в токен
     * @param int $expireTime Время жизни токена в секундах
     * @return string Сгенерированный JWT токен
     */
    public function generateToken(array $payload, int $expireTime = JWT_TIME): string
    {
        // Добавляем стандартные поля JWT
        $tokenPayload = array_merge($payload, [
            'iat' => time(),              // Время создания токена
            'exp' => time() + $expireTime, // Время истечения токена 
        ]);

        // Генерируем токен
        return JWT::encode($tokenPayload, $this->privateKey, $this->algorithm);
    }

    /**
     * Проверка и декодирование JWT токена
     *
     * @param string $token JWT токен для проверки
     * @return object|null Декодированные данные или null при ошибке
     */
    public function validateToken(string $token)
    {
        $token = trim($token);
        if (empty($token)) {

            \App\Core\Response::badRequest('Token not found');
        }
        try {
            $decoded = JWT::decode($token, new Key($this->publicKey, $this->algorithm));
            if ($decoded->exp <= time()) {

                \App\Core\Response::badRequest('Time expired');
                return null;
            }
            return $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            // Токен истек
            \App\Core\Response::badRequest('Token expired: ' . $e->getMessage());
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            // Неверная подпись
            \App\Core\Response::badRequest('Incorrect token signature: ' . $e->getMessage());
            return null;
        } catch (\Firebase\JWT\BeforeValidException $e) {
            // Токен еще не действителен
            \App\Core\Response::badRequest("The token's not valid yet:" . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            // Прочие ошибки
            \App\Core\Response::badRequest('Token validation error: ' . $e->getMessage());
            return null;
        }
    }
}

