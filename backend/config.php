<?php
/**
 * Plik konfiguracyjny - Ładuje zmienne z .env
 * 
 * INSTRUKCJA:
 * 1. Skopiuj .env.example jako .env
 * 2. Wypełnij dane w pliku .env
 * 3. Nie commituj .env do repozytorium!
 */

/**
 * Ładuje zmienne środowiskowe z pliku .env
 * 
 * @param string $path Ścieżka do pliku .env
 * @return void
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        die('Error: .env file not found. Copy .env.example to .env and configure it.');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Pomiń komentarze
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsuj linie KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Usuń cudzysłowy jeśli istnieją
            $value = trim($value, '"\'');
            
            // Ustaw jako zmienną środowiskową
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Pobiera zmienną środowiskową
 * 
 * @param string $key Klucz zmiennej
 * @param mixed $default Wartość domyślna
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    return $value;
}

// Załaduj plik .env
loadEnv(__DIR__ . '/.env');

// Konfiguracja serwera Traccar
define('TRACCAR_URL', env('TRACCAR_URL'));
define('TRACCAR_USER', env('TRACCAR_USER'));
define('TRACCAR_PASSWORD', env('TRACCAR_PASSWORD'));

// Hasło dostępu do API (SHA256 hash)
define('ACCESS_PASSWORD_HASH', hash('sha256', env('ACCESS_PASSWORD', 'secure123')));

// Czas timeout dla połączeń (sekundy)
define('CURL_TIMEOUT', (int)env('CURL_TIMEOUT', 30));

// Strefa czasowa
define('TIMEZONE', env('TIMEZONE', 'Europe/Warsaw'));

// Dozwolone originy CORS (oddzielone przecinkami)
define('ALLOWED_ORIGINS', env('ALLOWED_ORIGINS', 'http://localhost'));
