# WAPM GPS Tracker - Dokumentacja Systemu

## 📋 Spis Treści
1. [Przegląd Systemu](#przegląd-systemu)
2. [Przepływ Danych (Data Flow)](#przepływ-danych)
3. [Specyfikacja API](#specyfikacja-api)
4. [Konfiguracja Traccar](#konfiguracja-traccar)
5. [Instalacja i Wdrożenie](#instalacja-i-wdrożenie)
6. [Architektura](#architektura)
7. [Bezpieczeństwo](#bezpieczeństwo)
8. [Dokumentacja Kodu](#dokumentacja-kodu)

---

## 🌐 Przegląd Systemu

**WAPM GPS Tracker** to lekki, bezpieczny system śledzenia GPS zaprojektowany dla 50 jednostek (pojazdy i piesi). System składa się z:

- **Backend**: PHP Proxy (API Gateway)
- **Frontend**: Progressive Web App (PWA) z mapą Leaflet
- **Źródło danych**: Serwer Traccar (zewnętrzny)

### Główne Funkcje
- ✅ Śledzenie w czasie rzeczywistym
- ✅ 4 kategorie jednostek (Karetka, SZOP, Grupa, Telefon)
- ✅ Status offline (brak zasięgu)
- ✅ Mechanizm "ślimaka" (trail)
- ✅ Filtrowanie i wyszukiwanie
- ✅ Nawigacja Google Maps
- ✅ PWA - instalowalne na telefon
- ✅ Bezpieczne logowanie

---

## 🔄 Przepływ Danych

```
┌─────────────────┐
│  GPS Tracker    │
│  (Lokalizator)  │
└────────┬────────┘
         │ HTTP/TCP
         │ (protokół Traccar)
         ▼
┌─────────────────┐
│  Serwer Traccar │
│  (External API) │
└────────┬────────┘
         │ HTTPS + Basic Auth
         │ GET /api/positions
         ▼
┌─────────────────┐
│   PHP Proxy     │
│   (api.php)     │
│                 │
│  • Weryfikacja  │
│  • Transformacja│
│  • Filtracja    │
└────────┬────────┘
         │ HTTPS + ?pass=HASH
         │ JSON Response
         ▼
┌─────────────────┐
│   Frontend PWA  │
│   (index.html)  │
│                 │
│  • Mapa Leaflet │
│  • Real-time    │
│  • Offline PWA  │
└─────────────────┘
```

### Sekwencja Komunikacji

1. **Lokalizator → Traccar**
   - Urządzenie GPS wysyła pozycję co X sekund
   - Protokół zależny od urządzenia (OSMAND, H02, etc.)

2. **Frontend → PHP Proxy**
   - Request: `GET /backend/api.php?pass=secure123`
   - Nagłówki: Standard HTTP + CORS

3. **PHP Proxy → Traccar**
   - Request: `GET https://traccar-server/api/positions`
   - Auth: `Authorization: Basic base64(user:pass)`

4. **Traccar → PHP Proxy**
   - Response: Surowe dane JSON (pozycje + atrybuty)

5. **PHP Proxy → Frontend**
   - Response: Przefiltrowane dane (tylko potrzebne pola)

---

## 🔌 Specyfikacja API

### Endpoint: `GET /backend/api.php`

#### Request
```http
GET /backend/api.php?pass=secure123 HTTP/1.1
Host: twoja-domena.com
Accept: application/json
```

**Parametry Query:**
| Parametr | Typ | Wymagany | Opis |
|----------|-----|----------|------|
| `pass` | string | ✅ Tak | SHA256 hash hasła dostępu |

#### Response - Sukces (200 OK)
```json
{
  "success": true,
  "count": 3,
  "timestamp": "2026-02-11T14:30:00+01:00",
  "data": [
    {
      "id": 12345,
      "deviceId": 1,
      "name": "Karetka 01",
      "description": "Jan Kowalski, Anna Nowak",
      "category": "ambulance",
      "lat": 52.2297,
      "lon": 21.0122,
      "lastUpdate": "2026-02-11T14:29:45+01:00",
      "batteryLevel": 85
    },
    {
      "id": 12346,
      "deviceId": 2,
      "name": "SZOP 03",
      "description": "Patrol A",
      "category": "pickup",
      "lat": 52.2312,
      "lon": 21.0156,
      "lastUpdate": "2026-02-11T14:29:50+01:00",
      "batteryLevel": 62
    },
    {
      "id": 12347,
      "deviceId": 3,
      "name": "Grupa Piesza 5",
      "description": "Marcin, Ewa, Piotr",
      "category": "person",
      "lat": 52.2289,
      "lon": 21.0101,
      "lastUpdate": "2026-02-11T14:20:00+01:00",
      "batteryLevel": null
    }
  ]
}
```

**Pola obiektu urządzenia:**

| Pole | Typ | Opis |
|------|-----|------|
| `id` | number | ID pozycji w Traccar |
| `deviceId` | number | ID urządzenia |
| `name` | string | Nazwa jednostki |
| `description` | string | Opis (obsada, załoga) |
| `category` | string | Kategoria: `ambulance`, `pickup`, `person`, `mobile` |
| `lat` | number | Szerokość geograficzna (6 miejsc po przecinku) |
| `lon` | number | Długość geograficzna (6 miejsc po przecinku) |
| `lastUpdate` | string | Timestamp ISO 8601 ostatniej pozycji |
| `batteryLevel` | number\|null | Poziom baterii (0-100) lub null |

#### Response - Błąd (403 Forbidden)
```json
{
  "error": true,
  "message": "Forbidden - Invalid password",
  "timestamp": "2026-02-11T14:30:00+01:00"
}
```

#### Response - Błąd (502 Bad Gateway)
```json
{
  "error": true,
  "message": "Unable to fetch data from Traccar server",
  "timestamp": "2026-02-11T14:30:00+01:00"
}
```

---

## 📡 Konfiguracja Traccar

### Wymagania
- Traccar Server v5.0+ zainstalowany
- API włączone (domyślnie port 8082)
- Konto administratora z uprawnieniami API

### 1. Dodanie Urządzenia

W panelu Traccar:
1. Przejdź do **Settings → Devices**
2. Kliknij **+** (Add Device)
3. Wypełnij formularz:
   - **Name**: `Karetka 01` (nazwa wyświetlana)
   - **Identifier**: `123456789012345` (IMEI lub unikalny ID)
   - **Category**: `ambulance` ⚠️ **WAŻNE!**

### 2. Konfiguracja Kategorii

**Lista obsługiwanych kategorii:**

| Wartość w Traccar | Ikona w PWA | Kolor | Opis |
|-------------------|-------------|-------|------|
| `ambulance` | 🚑 | Czerwony (#e74c3c) | Karetki pogotowia |
| `pickup` | 🚚 | Pomarańczowy (#e67e22) | Pojazdy SZOP |
| `person` | 🚶 | Niebieski (#3498db) | Grupy piesze |
| `mobile` | 📱 | Zielony (#27ae60) | Telefony użytkowników |

**⚠️ UWAGA:** Pole `category` musi być wpisane **dokładnie** jak w tabeli (małe litery). W przeciwnym razie urządzenie będzie miało domyślną ikonę `mobile`.

### Jak przypisywana jest kategoria

System przypisuje kategorię przede wszystkim na podstawie identyfikatora urządzenia (`uniqueId`) — czyli przez ID/prefix — a nie tylko przez tekstowe pole `category`.

- **Kolejność źródeł wartości (priorytet):**
  1. Prefix `uniqueId` (pierwszy znak) — używany przez backend do wywnioskowania kategorii.
  2. Pole `category` zwrócone przez Traccar (jeśli `uniqueId` nie jest dostępne lub nie pasuje).
  3. Wartość domyślna: `mobile`.

- **Mapowanie ID (prefix → kategoria):**
  - `1` → `person` (Grupa)
  - `2` → `ambulance` (Karetka)
  - `3` → `pickup` (SZOP)
  - `4` → `mobile` (Telefon)

- **Jak to działa w praktyce:**
  - Gdy urządzenie jest dodawane przez nasze API (`backend/api.php`), generujemy `uniqueId` z prefiksem odpowiadającym kategorii (funkcja `generateUniqueId()`), np. `2XXXXXXXXX` dla `ambulance`.
  - Backend wyciąga pierwszy znak `uniqueId` w `getCategoryFromUniqueId()` i mapuje go na jedną z czterech kategorii.
  - Jeśli `uniqueId` nie istnieje lub pierwszy znak nie pasuje do znanych prefiksów, backend sprawdza pole `category` zwrócone przez Traccar, a w ostateczności ustawia `mobile`.

- **Fallback i normalizacja:**
  - Gdy używana jest wartość tekstowa z pola `category`, system normalizuje ją (przycięcie spacji, konwersja do małych liter) i dopuszcza tylko `ambulance`, `pickup`, `person`, `mobile`.
  - Nieznane wartości powodują przypisanie `mobile`.

- **Praktyczne wskazówki konfiguracji:**
  - Jeśli dodajesz urządzenia przez nasze API, nie musisz ręcznie ustawiać `category` — użyj parametru `category` podczas wywołania POST, a `uniqueId` zostanie wygenerowane z odpowiednim prefiksem.
  - Jeśli dodajesz urządzenie bezpośrednio w panelu Traccar, upewnij się, że `uniqueId` zaczyna się od odpowiedniej cyfry (1–4) lub wypełnij pole `category` tekstowo zgodnie z listą.
  - Możesz też użyć atrybutu `customCategory` (dodawany przez API) jako dodatkowej informacji, ale to nie zastępuje mechanizmu opartego na `uniqueId`.

Frontend mapuje ostateczną kategorię na ikony i kolory zgodnie z tabelą powyżej.

### 3. Dodanie Atrybutu Description

Description (obsada) ustawia się w atrybutach urządzenia:

1. Otwórz urządzenie w Traccar
2. Przejdź do zakładki **Attributes**
3. Dodaj atrybut:
   - **Key**: `description`
   - **Type**: `String`
   - **Value**: `Jan Kowalski, Anna Nowak`
4. Kliknij **Save**

### 4. Format Danych z Traccar (Input)

**Przykładowy request do Traccar API:**
```http
GET /api/positions HTTP/1.1
Authorization: Basic YWRtaW46cGFzc3dvcmQ=
```

**Przykładowa odpowiedź Traccar (format wejściowy):**
```json
[
  {
    "id": 12345,
    "deviceId": 1,
    "protocol": "osmand",
    "deviceTime": "2026-02-11T14:29:45.000+01:00",
    "fixTime": "2026-02-11T14:29:45.000+01:00",
    "serverTime": "2026-02-11T14:29:46.000+01:00",
    "outdated": false,
    "valid": true,
    "latitude": 52.229676,
    "longitude": 21.012229,
    "altitude": 120.5,
    "speed": 45.2,
    "course": 178.5,
    "address": null,
    "accuracy": 10.0,
    "network": null,
    "attributes": {
      "batteryLevel": 85.3,
      "distance": 1523.45,
      "totalDistance": 125678.90,
      "motion": true,
      "ignition": true
    }
  }
]
```

---

## 🚀 Instalacja i Wdrożenie

### Wymagania Serwera
- **PHP**: 7.4+ (zalecane 8.0+)
- **Rozszerzenia PHP**: `curl`, `json`, `openssl`
- **SSL**: Certyfikat SSL/TLS (wymagane dla PWA)
- **Hosting**: Standardowy hosting PHP (np. cPanel, Plesk)

### Krok 1: Upload plików

```
twoja-domena.com/
├── backend/
│   ├── config.php
│   └── api.php
├── frontend/
│   ├── index.html
│   ├── manifest.json
│   └── service-worker.js
└── DOCUMENTATION.md
```

### Krok 2: Konfiguracja Backend

Skopiuj i edytuj plik `.env`:

```bash
cd backend
cp .env.example .env
```

Edytuj `backend/.env`:

```bash
# Traccar Server Configuration
TRACCAR_URL=https://twoj-serwer.com:8082/api/positions
TRACCAR_USER=admin
TRACCAR_PASSWORD=twoje_haslo

# API Access Password (hasło do logowania w PWA)
ACCESS_PASSWORD=TWOJE_NOWE_HASLO

# CORS Configuration
ALLOWED_ORIGINS=https://twoja-domena.com,https://www.twoja-domena.com

# Connection Settings
CURL_TIMEOUT=30
```

### Krok 3: Konfiguracja Frontend

Edytuj `frontend/index.html` (linia ~405):

```javascript
const CONFIG = {
    API_URL: 'https://twoja-domena.com/backend/api.php', // Pełny URL do API
    API_PASSWORD: 'TWOJE_NOWE_HASLO', // To samo co w .env (ACCESS_PASSWORD)
    REFRESH_INTERVAL: 15000, // 15 sekund
    // ...
};
```

**⚠️ WAŻNE:** Hasło w `API_PASSWORD` musi być **identyczne** z `ACCESS_PASSWORD` w pliku `.env`!

### Krok 4: Testowanie

1. Otwórz `https://twoja-domena.com/frontend/index.html`
2. Zaloguj się hasłem z CONFIG
3. Sprawdź czy mapa się ładuje
4. Sprawdź konsolę przeglądarki (F12) dla błędów

### Krok 5: Instalacja PWA

Na telefonie:
1. Otwórz stronę w Chrome/Safari
2. Menu → **Add to Home Screen** / **Dodaj do ekranu głównego**
3. Ikona pojawi się na ekranie

---

## 🏗️ Architektura

### Backend (PHP)

**Struktura plików:**
```
backend/
├── .env.example        # Template konfiguracji
├── .env                # Konfiguracja (NIE COMMITUJ!)
├── config.php          # Ładuje zmienne z .env
├── api.php             # Główny plik API
├── .htaccess           # Zabezpieczenia Apache
└── .gitignore          # Ignorowane pliki
```

**Funkcje PHP (api.php):**

| Funkcja | Opis |
|---------|------|
| `handleCORS()` | Obsługa Cross-Origin Resource Sharing |
| `verifyPassword($password)` | Weryfikacja hasła dostępu (hash_equals) |
| `fetchDataCURL()` | Pobieranie danych przez cURL |
| `fetchDataFileGetContents()` | Fallback przez file_get_contents |
| `fetchTraccarData()` | Główna funkcja pobierania z auto-fallback |
| `transformData($positions)` | Transformacja i filtracja danych |
| `getDeviceInfo($deviceId)` | Pobieranie metadanych urządzenia (cache) |
| `sendResponse($data, $code)` | Wysyłanie odpowiedzi JSON |
| `sendError($message, $code)` | Wysyłanie błędu JSON |

**Bezpieczeństwo:**
- ✅ Hash_equals (timing-safe comparison)
- ✅ CORS whitelisting
- ✅ Basic Auth do Traccar
- ✅ SSL required
- ✅ Rate limiting (TODO: można dodać)

### Frontend (PWA)

**Struktura plików:**
```
frontend/
├── index.html          # Główna aplikacja
├── manifest.json       # Konfiguracja PWA
└── service-worker.js   # Obsługa offline
```

**Główne moduły JavaScript:**

| Moduł | Opis |
|-------|------|
| `init()` | Inicjalizacja aplikacji |
| `checkLogin()` | Sprawdzanie sesji localStorage |
| `initMap()` | Inicjalizacja mapy Leaflet |
| `fetchDevices()` | Pobieranie danych z API (async) |
| `updateMarkers()` | Aktualizacja markerów na mapie |
| `createCustomIcon()` | Generowanie custom ikon |
| `updateTrail()` | Rysowanie polyline (ślimak) |
| `isDeviceOffline()` | Sprawdzanie statusu offline |
| `showMyLocation()` | Geolokalizacja użytkownika |
| `navigateTo()` | Deep link do Google Maps |

**Stan aplikacji (State):**
```javascript
const state = {
    map: null,              // Instancja Leaflet
    markers: {},            // Obiekty markerów {deviceId: marker}
    trails: {},             // Polyline dla każdego urządzenia
    devices: [],            // Aktualna lista urządzeń
    filters: {...},         // Status filtrów kategorii
    searchTerm: '',         // Wyszukiwane słowo
    myLocationMarker: null, // Marker lokalizacji użytkownika
    refreshInterval: null   // ID interwału odświeżania
};
```

---

## 🔐 Bezpieczeństwo

### Warstwy Zabezpieczeń

1. **HTTPS Required**
   - PWA wymaga SSL
   - Geolocation API wymaga HTTPS

2. **Autentykacja**
   - Hasło SHA256 (hash)
   - Stored w localStorage (client-side session)
   - Basic Auth do Traccar (server-side)

3. **CORS**
   - Whitelisting domenowy
   - Preflight handling

4. **Walidacja**
   - hash_equals (timing-attack safe)
   - Input sanitization

5. **Separacja Danych**
   - Credentials w config.php (nie w repo)
   - .htaccess dla ochrony backend/

### Zalecane Dodatki

**Plik `.htaccess` dla backend:**
```apache
# Ochrona config.php
<Files "config.php">
    Require all denied
</Files>

# Wymuszenie HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Rate Limiting (TODO):**
```php
// Dodać w api.php przed weryfikacją hasła
function rateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "ratelimit_$ip";
    $limit = 60; // 60 requestów
    $period = 60; // na 60 sekund
    
    // Implementacja z Redis/APCu/File
}
```

---

## 📚 Dokumentacja Kodu

### Backend (PHP)

#### Funkcja: `verifyPassword()`
```php
/**
 * Weryfikuje hasło dostępu
 * 
 * @param string $password Hasło z parametru GET
 * @return bool True jeśli hasło poprawne
 * 
 * @example
 * $isValid = verifyPassword($_GET['pass']);
 * if (!$isValid) {
 *     sendError('Invalid password', 403);
 * }
 */
function verifyPassword($password) {
    if (empty($password)) {
        return false;
    }
    
    $hashedInput = hash('sha256', $password);
    return hash_equals(ACCESS_PASSWORD_HASH, $hashedInput);
}
```

#### Funkcja: `transformData()`
```php
/**
 * Transformuje surowe dane Traccar do formatu wyjściowego
 * 
 * Filtruje niepotrzebne pola i formatuje dane dla frontendu.
 * Łączy pozycje z metadanymi urządzeń (nazwa, kategoria, opis).
 * 
 * @param array $positions Tablica pozycji z Traccar API
 * @return array Przefiltrowane dane w formacie:
 *               [
 *                 'id' => int,
 *                 'deviceId' => int,
 *                 'name' => string,
 *                 'description' => string,
 *                 'category' => string,
 *                 'lat' => float,
 *                 'lon' => float,
 *                 'lastUpdate' => string (ISO 8601),
 *                 'batteryLevel' => int|null
 *               ]
 * 
 * @example
 * $raw = fetchTraccarData();
 * $cleaned = transformData($raw);
 * sendResponse(['data' => $cleaned]);
 */
function transformData($positions) {
    // ...
}
```

### Frontend (JavaScript)

#### Funkcja: `fetchDevices()`
```javascript
/**
 * Pobiera urządzenia z API
 * 
 * Wykonuje request do PHP Proxy i aktualizuje stan aplikacji.
 * Obsługuje błędy połączenia i aktualizuje badge statusu.
 * 
 * @async
 * @returns {Promise<void>}
 * @throws {Error} Jeśli request nie powiedzie się
 * 
 * @example
 * await fetchDevices();
 * // state.devices zawiera teraz aktualne dane
 */
async function fetchDevices() {
    try {
        updateStatus('Pobieranie danych...', 'info');
        
        const response = await fetch(`${CONFIG.API_URL}?pass=${CONFIG.API_PASSWORD}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            state.devices = result.data;
            updateMarkers();
            updateStatus(`Aktywne: ${result.count} urządzeń`, 'success');
        } else {
            throw new Error(result.message || 'Błąd danych');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        updateStatus('Błąd połączenia', 'error');
    }
}
```

#### Funkcja: `createCustomIcon()`
```javascript
/**
 * Tworzy niestandardową ikonę Leaflet
 * 
 * Generuje HTML divIcon z emoji i kolorowym tłem.
 * Wspiera stan offline (grayscale + opacity).
 * 
 * @param {string} emoji - Emoji ikony (np. '🚑')
 * @param {string} color - Kolor HEX tła (np. '#e74c3c')
 * @param {boolean} offline - Czy urządzenie offline
 * @returns {L.DivIcon} Obiekt ikony Leaflet
 * 
 * @example
 * const icon = createCustomIcon('🚑', '#e74c3c', false);
 * L.marker([52.22, 21.01], { icon }).addTo(map);
 */
function createCustomIcon(emoji, color, offline) {
    const opacity = offline ? 0.4 : 1;
    const filter = offline ? 'grayscale(100%)' : 'none';
    
    return L.divIcon({
        html: `<div style="
            background: ${color};
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            opacity: ${opacity};
            filter: ${filter};
        ">${emoji}</div>`,
        className: '',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
}
```

#### Funkcja: `isDeviceOffline()`
```javascript
/**
 * Sprawdza czy urządzenie jest offline
 * 
 * Porównuje ostatnią aktualizację z progiem (domyślnie 10 minut).
 * Używane do wizualnego oznaczania urządzeń bez zasięgu.
 * 
 * @param {string} lastUpdate - Timestamp ISO 8601 ostatniej pozycji
 * @returns {boolean} True jeśli urządzenie offline
 * 
 * @example
 * const isOff = isDeviceOffline('2026-02-11T14:00:00+01:00');
 * if (isOff) {
 *     console.log('Urządzenie bez zasięgu');
 * }
 */
function isDeviceOffline(lastUpdate) {
    const lastTime = new Date(lastUpdate).getTime();
    const now = Date.now();
    return (now - lastTime) > CONFIG.OFFLINE_THRESHOLD;
}
```

---

## 🛠️ Rozwiązywanie Problemów

### Problem: Brak danych na mapie

**Możliwe przyczyny:**
1. Błędne hasło w CONFIG
2. CORS blocked
3. Traccar server niedostępny
4. Błąd w config.php

**Rozwiązanie:**
```bash
# Sprawdź logi PHP
tail -f /var/log/apache2/error.log

# Test API bezpośrednio
curl "https://twoja-domena.com/backend/api.php?pass=secure123"

# Test Traccar
curl -u admin:haslo https://traccar-server:8082/api/positions
```

### Problem: PWA nie instaluje się

**Rozwiązanie:**
- Sprawdź czy strona jest na HTTPS
- Sprawdź manifest.json (valid JSON)
- Sprawdź Service Worker (Chrome DevTools → Application)

### Problem: Ikony nie pokazują się

**Rozwiązanie:**
- Sprawdź pole `category` w Traccar (musi być: ambulance/pickup/person/mobile)
- Sprawdź console w przeglądarce dla błędów JS

---

## 📞 Wsparcie

W przypadku pytań:
1. Sprawdź logi PHP (`error_log`)
2. Sprawdź konsolę przeglądarki (F12)
3. Zweryfikuj konfigurację (config.php vs index.html)

---

## 📄 Licencja

Projekt proprietary - Senior Fullstack Developer Team
© 2026 WAPM GPS Tracker

---

**Wersja:** 1.0  
**Data:** 2026-02-11  
**Autor:** Senior Fullstack Developer (PHP, JavaScript, IoT)
