<?php
/**
 * GPS Tracking System - API Proxy
 * 
 * Bezpieczne proxy do serwera Traccar
 * Filtruje i transformuje dane przed wysłaniem do frontendu
 * 
 * @version 1.0
 * @author Senior Fullstack Developer
 */

// Załaduj konfigurację
require_once 'config.php';

// Ustaw nagłówki odpowiedzi
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Zapobiegaj cachowaniu - zawsze pobieraj świeże dane
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Obsługa CORS
handleCORS();

// Dla POST requests, obsłuż preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Obsługuje Cross-Origin Resource Sharing
 * 
 * @return void
 */
function handleCORS() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = explode(',', ALLOWED_ORIGINS);
    
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 3600');
    }
    
    // Obsługa preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Weryfikuje hasło dostępu
 * 
 * @param string $password Hasło z parametru GET
 * @return bool True jeśli hasło poprawne
 */
function verifyPassword($password) {
    if (empty($password)) {
        return false;
    }
    
    $hashedInput = hash('sha256', $password);
    return hash_equals(ACCESS_PASSWORD_HASH, $hashedInput);
}

/**
 * Pobiera dane z Traccar API używając cURL
 * 
 * @return array|false Dane z API lub false w przypadku błędu
 */
function fetchDataCURL() {
    $positionsUrl = getTraccarBase() . '/positions';
    $ch = initTraccarCurl($positionsUrl);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) {
        error_log("cURL Error: $error (HTTP $httpCode)");
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Pobiera dane z Traccar API używając file_get_contents (fallback)
 * 
 * @return array|false Dane z API lub false w przypadku błędu
 */
function fetchDataFileGetContents() {
    $auth = base64_encode(TRACCAR_USER . ':' . TRACCAR_PASSWORD);
    
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic $auth\r\n" .
                       "Accept: application/json\r\n" .
                       "User-Agent: WAPM-GPS-Tracker/1.0\r\n",
            'timeout' => CURL_TIMEOUT,
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $positionsUrl = getTraccarBase() . '/positions';
    $response = @file_get_contents($positionsUrl, false, $context);
    
    if ($response === false) {
        error_log("file_get_contents Error");
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Inicjalizuje wspólne opcje cURL dla Traccar
 *
 * @param string $url
 * @param array $extraOptions dodatkowe opcje do ustawienia
 * @return resource cURL handle
 */
function initTraccarCurl($url, $extraOptions = []) {
    $ch = curl_init($url);

    $baseOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => CURL_TIMEOUT,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => TRACCAR_USER . ':' . TRACCAR_PASSWORD,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'WAPM-GPS-Tracker/1.0',
    ];

    $options = $extraOptions + $baseOptions;
    curl_setopt_array($ch, $options);
    return $ch;
}

/**
 * Zwraca znormalizowany base URL Traccar i zapewnia, że zawiera '/api'
 * Zwraca URL bez końcowego slasha, np. 'https://host/api'
 *
 * @return string
 */
function getTraccarBase() {
    $base = rtrim(TRACCAR_URL, '/');

    // Jeśli '/api' nie występuje w URL, dopisz je
    if (stripos($base, '/api') === false) {
        $base .= '/api';
    }

    return rtrim($base, '/');
}

/**
 * Pobiera dane z Traccar z automatycznym fallbackiem
 * 
 * @return array|false Dane z API lub false w przypadku błędu
 */
function fetchTraccarData() {
    // Próbuj cURL najpierw
    if (function_exists('curl_init')) {
        $data = fetchDataCURL();
        if ($data !== false) {
            return $data;
        }
    }
    
    // Fallback na file_get_contents
    return fetchDataFileGetContents();
}

/**
 * Pobiera dane z endpointa Traccar
 * 
 * @param string $endpoint Endpoint (np. '/devices', '/positions')
 * @return array|false Dane z API lub false w przypadku błędu
 */
function fetchFromTraccar($endpoint) {
    $url = getTraccarBase() . '/' . ltrim($endpoint, '/');
    // echo "Fetching from Traccar: $url\n"; // Debug log

    // Próbuj cURL
    if (function_exists('curl_init')) {
        $ch = initTraccarCurl($url, [
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            echo $response; // Debug log - sprawdź surową odpowiedź
            return json_decode($response, true);
        }
    }
    
    // Fallback na file_get_contents
    $auth = base64_encode(TRACCAR_USER . ':' . TRACCAR_PASSWORD);
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic $auth\r\n" .
                       "Accept: application/json\r\n",
            'timeout' => CURL_TIMEOUT
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        return json_decode($response, true);
    }
    
    return false;
}

/**
 * Konwertuje czas UTC na lokalną strefę czasową
 * 
 * @param string $utcTime Czas w formacie ISO 8601 (UTC)
 * @return string Czas w lokalnej strefie czasowej (ISO 8601)
 */
function convertToLocalTime($utcTime) {
    try {
        $dt = new DateTime($utcTime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(TIMEZONE));
        return $dt->format('c');
    } catch (Exception $e) {
        // Jeśli konwersja się nie uda, zwróć oryginalny czas
        return $utcTime;
    }
}

/**
 * Transliteruje polskie znaki na ASCII (dla starych baz danych)
 * 
 * @param string $text Tekst z polskimi znakami
 * @return string Tekst bez polskich znaków
 */
function transliteratePolish($text) {
    $polishChars = ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż'];
    $asciiChars = ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'A', 'C', 'E', 'L', 'N', 'O', 'S', 'Z', 'Z'];
    return str_replace($polishChars, $asciiChars, $text);
}

/**
 * Dodaje nowe urządzenie do Traccar
 * 
 * @param array $data Dane urządzenia ['name', 'category', 'description', 'groupId']
 * @return array ['success' => bool, 'device' => array|null, 'message' => string]
 */
function addDevice($data) {
    $name = $data['name'] ?? '';
    $category = $data['category'] ?? 'mobile';
    $description = $data['description'] ?? '';
    $groupId = $data['groupId'] ?? null;
    
    // Transliteruj polskie znaki (demo.traccar.org ma problemy z UTF-8)
    $name = transliteratePolish($name);
    $description = transliteratePolish($description);
    
    // Generuj uniqueId na podstawie kategorii
    $uniqueId = generateUniqueId($category);
    
    // Przygotuj dane urządzenia dla Traccar (bez pola category - używamy uniqueId do rozpoznawania)
    $deviceData = [
        'name' => $name,
        'uniqueId' => $uniqueId,
        'attributes' => [
            'customCategory' => $category,
            'description' => $description
        ]
    ];
    
    if ($groupId) {
        $deviceData['groupId'] = (int)$groupId;
    }
    
    $devicesUrl = getTraccarBase() . '/devices';
    
    // Dodaj urządzenie przez API Traccar
    if (function_exists('curl_init')) {
        $ch = curl_init($devicesUrl);
        
        // Zakoduj dane do JSON z prawidłowym UTF-8
        $jsonData = json_encode($deviceData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_TIMEOUT => CURL_TIMEOUT,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => TRACCAR_USER . ':' . TRACCAR_PASSWORD,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'User-Agent: WAPM-GPS-Tracker/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            error_log("cURL error when adding device to $devicesUrl: $error");
            return [
                'success' => false,
                'message' => "cURL error: $error"
            ];
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $responseData = json_decode($response, true);
            $traccarError = $responseData['message'] ?? $response;
            error_log("Traccar API error (HTTP $httpCode) at $effectiveUrl: $traccarError");
            return [
                'success' => false,
                'message' => "Traccar API error: $traccarError (HTTP $httpCode)",
                'debug' => [
                    'httpCode' => $httpCode,
                    'requestUrl' => $devicesUrl,
                    'effectiveUrl' => $effectiveUrl,
                    'response' => $response,
                    'sentData' => $deviceData
                ]
            ];
        }
        
        $device = json_decode($response, true);
        return [
            'success' => true,
            'device' => $device
        ];
    }
    
    return [
        'success' => false,
        'message' => 'cURL not available'
    ];
}

/**
 * Generuje unikalny uniqueId na podstawie kategorii
 * 
 * @param string $category Kategoria urządzenia
 * @return string uniqueId
 */
function generateUniqueId($category) {
    // Prefiks kategorii
    $prefix = match($category) {
        'ambulance' => '2',
        'pickup' => '3',
        'person' => '1',
        'mobile' => '4',
        default => '4'
    };
    
    // Generuj losowy 9-cyfrowy numer
    $random = str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    
    return $prefix . $random;
}

/**
 * Określa kategorię urządzenia na podstawie uniqueId
 * 
 * @param string $uniqueId Unikalny identyfikator urządzenia
 * @return string Kategoria urządzenia
 */
function getCategoryFromUniqueId($uniqueId) {
    if (empty($uniqueId)) {
        return 'mobile';
    }
    
    $firstChar = substr($uniqueId, 0, 1);
    
    switch ($firstChar) {
        case '2':
            return 'ambulance'; // Karetka
        case '3':
            return 'pickup';    // SZOP
        case '1':
            return 'person';    // Grupa
        case '4':
            return 'mobile';    // Telefon
        default:
            return 'mobile';    // Domyślnie telefon
    }
}

/**
 * Transformuje surowe dane Traccar do formatu wyjściowego
 * Pobiera wszystkie urządzenia i łączy z ich pozycjami
 * 
 * @return array Przefiltrowane dane
 */
function getAllDevicesData() {
    // Pobierz wszystkie urządzenia
    $devices = fetchFromTraccar('/devices');
    var_dump($devices); // Debug log - sprawdź strukturę danych
    echo "Fetched " . (is_array($devices) ? count($devices) : 0) . " devices from Traccar\n"; // Debug log
    if (!is_array($devices) || empty($devices)) {
        return [];
    }
    
    // Pobierz wszystkie pozycje
    $positions = fetchFromTraccar('/positions');
    $positionsMap = [];
    
    if (is_array($positions)) {
        foreach ($positions as $pos) {
            $positionsMap[$pos['deviceId']] = $pos;
        }
    }
    
    // Pobierz grupy
    $groups = getGroups();
    
    $result = [];
    
    foreach ($devices as $device) {
        $deviceId = $device['id'];
        $position = $positionsMap[$deviceId] ?? null;
        
        // Jeśli urządzenie nie ma pozycji, pomiń je
        if (!$position) {
            continue;
        }
        
        $groupId = $device['groupId'] ?? null;
        $groupName = $groupId && isset($groups[$groupId]) ? $groups[$groupId] : null;
        
        // Określ kategorię na podstawie uniqueId
        $uniqueId = $device['uniqueId'] ?? '';
        $category = getCategoryFromUniqueId($uniqueId);
        
        // Konwertuj czas z UTC na lokalną strefę
        $utcTime = $position['fixTime'] ?? $position['deviceTime'] ?? date('c');
        $localTime = convertToLocalTime($utcTime);
        
        $transformed = [
            'id' => $position['id'] ?? $deviceId,
            'deviceId' => $deviceId,
            'name' => $device['name'] ?? 'Unknown',
            'description' => $device['attributes']['description'] ?? '',
            'category' => $category,
            'groupName' => $groupName,
            'lat' => round($position['latitude'] ?? 0, 6),
            'lon' => round($position['longitude'] ?? 0, 6),
            'lastUpdate' => $localTime,
            'batteryLevel' => $position['attributes']['batteryLevel'] ?? null
        ];
        
        $result[] = $transformed;
    }
    
    return $result;
}

/**
 * Pobiera informacje o grupach z Traccar
 * Cache w pamięci dla optymalizacji
 * 
 * @return array Tablica grup (groupId => groupName)
 */
function getGroups() {
    static $groups = null;

    if ($groups !== null) {
        return $groups;
    }

    $groups = [];
    $groupsUrl = getTraccarBase() . '/groups';
    
    // Próbuj cURL
    if (function_exists('curl_init')) {
        $ch = initTraccarCurl($groupsUrl, [CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Accept: application/json']]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $groupsData = json_decode($response, true);
            if (is_array($groupsData)) {
                foreach ($groupsData as $group) {
                    $groups[$group['id']] = $group['name'] ?? "Group {$group['id']}";
                }
            }
        }
    }
    
    return $groups;
}

/**
 * Pobiera informacje o urządzeniu z Traccar
 * Cache w pamięci dla optymalizacji
 * 
 * @param int $deviceId ID urządzenia
 * @return array Informacje o urządzeniu
 */
function getDeviceInfo($deviceId) {
    static $cache = [];
    
    if (isset($cache[$deviceId])) {
        return $cache[$deviceId];
    }
                // Zakoduj dane do JSON z prawidłowym UTF-8
                $jsonData = json_encode($deviceData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $ch = initTraccarCurl($devicesUrl, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $jsonData,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json; charset=utf-8',
                        'Accept: application/json'
                    ]
                ]);
    $devicesUrl = getTraccarBase() . '/devices';
    $groups = getGroups();
    
    // Próbuj cURL
    if (function_exists('curl_init')) {
        $ch = initTraccarCurl($devicesUrl, [CURLOPT_TIMEOUT => 10]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $devices = json_decode($response, true);
            if (is_array($devices)) {
                foreach ($devices as $device) {
                    if ($device['id'] == $deviceId) {
                        $groupId = $device['groupId'] ?? null;
                        $groupName = $groupId && isset($groups[$groupId]) ? $groups[$groupId] : null;
                        
                        $cache[$deviceId] = [
                            'name' => $device['name'] ?? 'Unknown',
                            'description' => $device['attributes']['description'] ?? '',
                            'category' => $device['category'] ?? 'mobile',
                            'groupId' => $groupId,
                            'groupName' => $groupName
                        ];
                        return $cache[$deviceId];
                    }
                }
            }
        }
    }
    
    // Domyślne wartości jeśli nie udało się pobrać
    $cache[$deviceId] = [
        'name' => "Device $deviceId",
        'description' => '',
        'category' => 'mobile',
        'groupId' => null,
        'groupName' => null
    ];
    
    return $cache[$deviceId];
}

/**
 * Wysyła odpowiedź JSON
 * 
 * @param mixed $data Dane do wysłania
 * @param int $code Kod HTTP
 * @return void
 */
function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Wysyła odpowiedź błędu
 * 
 * @param string $message Komunikat błędu
 * @param int $code Kod HTTP
 * @return void
 */
function sendError($message, $code = 400) {
    sendResponse([
        'error' => true,
        'message' => $message,
        'timestamp' => date('c')
    ], $code);
}

// === GŁÓWNA LOGIKA ===

// Weryfikuj hasło dostępu lub pozwól na Basic auth (ułatwia curl/serwer->serwer)
$password = $_REQUEST['pass'] ?? '';
$authorized = false;

// 1) Jeśli podano publiczny pass przez GET/POST, waliduj go
if (!empty($password) && verifyPassword($password)) {
    $authorized = true;
}

// 2) Jeśli brak pass, spróbuj Basic Auth (przydatne dla curl i zapytań serwer->serwer)
if (!$authorized) {
    $authUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    $authPw = $_SERVER['PHP_AUTH_PW'] ?? null;

    // W niektórych konfiguracjach PHP zmienna PHP_AUTH_* może być pusta;
    // sprawdź nagłówek Authorization wtedy
    if ($authUser === null || $authPw === null) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') ;
        if ($authHeader && stripos($authHeader, 'basic ') === 0) {
            $decoded = base64_decode(substr($authHeader, 6));
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                [$u, $p] = explode(':', $decoded, 2);
                $authUser = $u;
                $authPw = $p;
            }
        }
    }

    if ($authUser !== null && $authPw !== null) {
        if ($authUser === TRACCAR_USER && $authPw === TRACCAR_PASSWORD) {
            $authorized = true;
        }
    }
}

if (!$authorized) {
    error_log('Unauthorized access attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    sendError('Forbidden - Invalid password', 403);
}

// Sprawdź akcję (domyślnie: lista urządzeń)
$action = $_REQUEST['action'] ?? 'list';

// Obsługa różnych akcji
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if ($action === 'verify-admin') {
        // Weryfikuj hasło administratora
        $adminPassword = $_REQUEST['admin_password'] ?? '';
        $adminPasswordHash = hash('sha256', $adminPassword);
        
        if ($adminPasswordHash === ADMIN_PASSWORD_HASH) {
            sendResponse([
                'success' => true,
                'message' => 'Admin password verified'
            ]);
        } else {
            error_log('Invalid admin password attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            sendError('Invalid admin password', 403);
        }
    } else {
        // Domyślnie: pobierz listę urządzeń
        $result = getAllDevicesData();
        
        if ($result === false || $result === null) {
            sendError('Unable to fetch data from Traccar server', 502);
        }
        
        sendResponse([
            'success' => true,
            'count' => count($result),
            'timestamp' => date('c'),
            'data' => $result
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dodaj nowe urządzenie - wymaga hasła administratora
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['name']) || !isset($input['category'])) {
        sendError('Missing required fields: name, category', 400);
    }
    
    // Sprawdź hasło administratora
    $adminPassword = $input['admin_password'] ?? '';
    $adminPasswordHash = hash('sha256', $adminPassword);
    
    if ($adminPasswordHash !== ADMIN_PASSWORD_HASH) {
        error_log('Unauthorized device add attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        sendError('Forbidden - Invalid admin password', 403);
    }
    
    $result = addDevice($input);
    
    if ($result['success']) {
        sendResponse([
            'success' => true,
            'message' => 'Device added successfully',
            'device' => $result['device']
        ]);
    } else {
        sendError($result['message'] ?? 'Failed to add device', 500);
    }
    
} else {
    sendError('Method not allowed', 405);
}
