<?php
//http://192.168.1.174/?debug=y

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

//error_reporting(E_ALL);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

function dd($data)
{
    $isObject = false;
    if (is_object($data)) {
        $data = (array)$data;
        $isObject = true;
    }
    $data = is_array($data) ? $data : ["dd" => $data];
    $data['data'] = date('d.m.Y H:i:s');
    $data['isObject'] = $isObject ? 1 : 0;
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

//Config
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/include.php';
require_once __DIR__ . '/controllers/index.php';
require_once __DIR__ . '/models/jWT.php';
require_once __DIR__ . '/services/Templates.php';
//
if ($_GET['debug'] == 'y') {

    $processor = new \PDFTPProc('/var/www/vstup-api/templates/contract.pdftp');
    echo '<pre>';
    print_r($processor->getFields());
    exit();
}

$JWT = new JWT_Secret(JWT_KEY);
R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
// Обробка CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!R::testConnection()) {
    App\Core\Response::forbidden("Data base not connect");
}


class ApiAPP
{
    private $version = 'v0';
    private $controllersPath;
    private $modelsPath;

    public function __construct()
    {
        $this->controllersPath = __DIR__ . '/controllers/';
        $this->modelsPath = __DIR__ . '/models/';

        try {
            //    $this->validateLicense();
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

        $cleanPath = preg_replace('#^/api/#', '', $path);

        $segments = array_filter(explode('/', trim($cleanPath, '/')));

        if (count($segments) < 3) {
            throw new Exception("API structure must be: /api/version/controller/method", 400);
        }
        $version = $segments[0];
        $controller = $segments[1];
        $action = $segments[2];

        // Валідація версії
        if ($version !== $this->version) {
            throw new Exception("Unsupported API version: {$version}. Current version: {$this->version}", 404);
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
            throw new Exception("Models class not found: {$controllerClass}", 404);
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

    /**
     * Отримання інформації про API
     */
    public function getApiInfo()
    {
        return [
            'version' => $this->version,
            'supported_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
            'structure' => '/api/{version}/{controller}/{action}',
            'example' => "/api/{$this->version}/user/login"
        ];
    }
}

// Ініціалізація API
new ApiAPP();

/*

// Замість \App\Sub\Show::end()
Response::success($data);
Response::success($data, 'User logged in successfully');

// Замість \App\Sub\Show::error()
Response::forbidden('Access denied');
Response::unauthorized('Invalid token');
Response::notFound('User not found');
Response::badRequest('Missing required fields', ['login' => 'required']);

// Нові можливості
Response::created($newUser, 'User created');
Response::unprocessable('Validation failed', $validationErrors);
Response::tooManyRequests('Rate limit exceeded', 60);
Response::custom(418, "I'm a teapot"); // :)


 */