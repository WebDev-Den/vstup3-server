<?php
date_default_timezone_set('Europe/Kiev');

// CORS заголовки - ПЕРШЕ що виконується
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Key-License');
header('Access-Control-Max-Age: 3600');

// Обробка OPTIONS запитів
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Функція для дебагу
function dd($data)
{
    $isObject = false;
    if (is_object($data)) {
        $data = (array)$data;
        $isObject = true;
    }
    $data = is_array($data) ? $data : ["dd" => $data];
    $data['date'] = date('d.m.Y H:i:s');
    $data['isObject'] = $isObject ? 1 : 0;
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Config
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/include.php';
require_once __DIR__ . '/controllers/index.php';
require_once __DIR__ . '/models/jWT.php';
require_once __DIR__ . '/services/Templates.php';

// Debug mode (тільки для розробки!)
if (isset($_GET['debug']) && $_GET['debug'] === 'y') {
    echo '<h2>Debug Information</h2>';
    echo '<h3>Server Variables:</h3>';
    echo '<pre>';
    print_r($_SERVER);
    echo '</pre>';
    echo '<h3>API Info:</h3>';
    echo '<pre>';
    echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
    echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
    echo "\nAPI Structure: /api/{version}/{controller}/{method}\n";
    echo "Example: /api/v0/user/login\n";
    echo '</pre>';
    exit();
}

// Ініціалізація
$JWT = new JWT_Secret(JWT_KEY);
R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!R::testConnection()) {
    App\Core\Response::forbidden("Data base not connect");
}

class ApiAPP
{
    private $version = 'v3.0';
    private $controllersPath;
    private $modelsPath;

    public function __construct()
    {
        $this->controllersPath = __DIR__ . '/controllers/';
        $this->modelsPath = __DIR__ . '/models/';

        try {
            // $this->validateLicense();
            $params = $this->parseRequest();
            $this->loadAndExecute($params);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Перевірка ліцензійного ключа
     */
    private function validateLicense()
    {
        global $HTTP_KEY_LICENSE;

        $key = $_SERVER['HTTP_KEY_LICENSE'] ?? null;

        if (empty($key)) {
            throw new Exception("License key is required", 403);
        }

        if (!in_array($key, $HTTP_KEY_LICENSE)) {
            throw new Exception("Invalid license key", 403);
        }
    }

    /**
     * Парсинг запиту та валідація структури
     */
    private function parseRequest()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH);

        if (!$path) {
            throw new Exception("Invalid request URI", 400);
        }

        // Видаляємо /api/ з початку
        $cleanPath = preg_replace('#^/api/?#', '', $path);
        
        // Розбиваємо на сегменти
        $segments = array_filter(explode('/', trim($cleanPath, '/')));

        // Якщо запит на корінь /api/ - показуємо інфо
        if (count($segments) === 0) {
            $this->showApiInfo();
        }

        // Перевірка структури: мінімум 3 сегменти (version/controller/action)
        if (count($segments) < 3) {
            throw new Exception(
                "Invalid API structure. Expected: /api/{version}/{controller}/{method}\n" .
                "Example: /api/v0/user/login\n" .
                "Received: " . $path,
                400
            );
        }

        $version = $segments[0];
        $controller = $segments[1];
        $action = $segments[2];

        // Валідація версії
        if ($version !== $this->version) {
            throw new Exception(
                "Unsupported API version: '{$version}'. Current version: '{$this->version}'",
                404
            );
        }

        // Валідація назви контролера
        if (!$this->isValidControllerName($controller)) {
            throw new Exception("Invalid controller name: {$controller}", 400);
        }

        // Валідація назви методу
        if (!$this->isValidMethodName($action)) {
            throw new Exception("Invalid method name: {$action}", 400);
        }

        return [
            'version' => $version,
            'controller' => ucfirst(strtolower($controller)),
            'action' => ucfirst(strtolower($action)),
            'method' => ucfirst(strtolower($_SERVER['REQUEST_METHOD'])),
            'uri_params' => array_slice($segments, 3),
            'request_data' => $this->getRequestData()
        ];
    }

    /**
     * Показати інформацію про API
     */
    private function showApiInfo()
    {
        $info = [
            'name' => 'VSTUP API',
            'version' => $this->version,
            'status' => 'active',
            'supported_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
            'structure' => '/api/{version}/{controller}/{method}',
            'examples' => [
                "/api/{$this->version}/user/login",
                "/api/{$this->version}/student/list",
                "/api/{$this->version}/application/create"
            ],
            'documentation' => 'Contact administrator for API documentation',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Отримання даних запиту
     */
    private function getRequestData()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        switch ($method) {
            case 'GET':
                return $_GET;

            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);

                if (json_last_error() !== JSON_ERROR_NONE && !empty($raw)) {
                    throw new Exception("Invalid JSON data: " . json_last_error_msg(), 400);
                }

                return $data ?: [];

            default:
                return [];
        }
    }

    /**
     * Завантаження та виконання контролера
     */
    private function loadAndExecute($params)
    {
        $controllerFile = $this->controllersPath . ucfirst(strtolower($params['controller'])) . '.php';
        $modelFile = $this->modelsPath . ucfirst(strtolower($params['controller'])) . '.php';

        if (!file_exists($controllerFile)) {
            throw new Exception("Controller file not found: {$params['controller']}", 404);
        }
        if (!file_exists($modelFile)) {
            throw new Exception("Model file not found: {$params['controller']}", 404);
        }

        // Завантаження файлів
        require_once($controllerFile);
        require_once($modelFile);

        $controllerClass = "App\\Controllers\\Controller{$params['controller']}";

        // Перевірка існування класу
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class not found: {$controllerClass}", 404);
        }
        
        $modelsClass = "App\\Models\\{$params['controller']}";
        if (!class_exists($modelsClass)) {
            throw new Exception("Models class not found: {$modelsClass}", 404);
        }

        $methodName = "query_{$params['method']}{$params['action']}";

        // Перевірка існування методу
        if (!method_exists($controllerClass, $methodName)) {
            throw new Exception("Method not found: {$methodName} in {$controllerClass}", 404);
        }

        // Створення екземпляра та виклик методу
        $controllerData = [
            'uri' => $params['uri_params'],
            'query' => $params['request_data']
        ];

        $controller = new $controllerClass($controllerData);
        $result = $controller->$methodName();

        // Успішна відповідь
        App\Core\Response::success($result);
    }

    /**
     * Валідація назви контролера
     */
    private function isValidControllerName($name)
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name) && strlen($name) <= 50;
    }

    /**
     * Валідація назви методу
     */
    private function isValidMethodName($name)
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name) && strlen($name) <= 50;
    }

    /**
     * Обробка помилок
     */
    private function handleError(Exception $e)
    {
        $code = $e->getCode() ?: 500;
        $message = $e->getMessage() ?: 'Internal Server Error';

        // Логування помилки
        error_log("API Error [{$code}]: {$message} in " . $e->getFile() . ":" . $e->getLine());

        // Визначення типу помилки за кодом
        switch ($code) {
            case 400:
                App\Core\Response::badRequest($message);
                break;
            case 401:
                App\Core\Response::unauthorized($message);
                break;
            case 403:
                App\Core\Response::forbidden($message);
                break;
            case 404:
                App\Core\Response::notFound($message);
                break;
            case 405:
                App\Core\Response::methodNotAllowed($message);
                break;
            case 422:
                App\Core\Response::unprocessable($message);
                break;
            default:
                App\Core\Response::serverError('Internal Server Error');
        }
    }
}

// Ініціалізація API
new ApiAPP();