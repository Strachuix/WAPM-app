# WAPM GPS Tracker

System śledzenia GPS dla 50 jednostek (pojazdy i piesi) - lekki, bezpieczny, gotowy do wdrożenia.

## 🚀 Quick Start

### 1. Konfiguracja Backend

Skopiuj `.env.example` jako `.env` i wypełnij:
```bash
cd backend
cp .env.example .env
nano .env  # lub inny edytor
```

Edytuj `backend/.env`:
```bash
TRACCAR_URL=https://twoj-serwer-traccar.com/api
TRACCAR_USER=admin
TRACCAR_PASSWORD=twoje_haslo
ACCESS_PASSWORD=NOWE_HASLO
ALLOWED_ORIGINS=https://twoja-domena.com
```

### 2. Konfiguracja Frontend

Edytuj `frontend/index.html` (linia ~405):
```javascript
const CONFIG = {
    API_URL: 'https://twoja-domena.com/backend/api.php',
    API_PASSWORD: 'NOWE_HASLO',  // To samo co ACCESS_PASSWORD w .env
    // ...
};
```

### 3. Upload na serwer

```
twoja-domena.com/
├── backend/
│   ├── config.php
│   └── api.php
└── frontend/
    ├── index.html
    ├── manifest.json
    └── service-worker.js
```

### 4. Otwórz w przeglądarce

`https://twoja-domena.com/frontend/index.html`

## 🧪 Testowanie Lokalne

Jeśli chcesz przetestować aplikację lokalnie bez hostingu:

### ⚠️ WAŻNE: Użyj PHP Server (nie Python!)

Backend wymaga **PHP** do działania. Python HTTP Server NIE wykonuje kodu PHP!

```powershell
# Przejdź do folderu WAPM-app (folder główny projektu)
cd c:\Users\stani\Desktop\WAPM-app

# Uruchom PHP Built-in Server
php -S localhost:8000
```

**Następnie otwórz w przeglądarce:**
```
http://localhost:8000/frontend/index.html
```

### Sprawdzenie czy PHP działa:
Otwórz bezpośrednio API w przeglądarce:
```
http://localhost:8000/backend/api.php?pass=secure123
```

**Powinno pokazać JSON z danymi, NIE kod PHP!**

**⚠️ Jeśli nie masz PHP:**
- Windows: Pobierz z https://windows.php.net/download/
- Linux: `sudo apt install php-cli php-curl php-json`
- macOS: `brew install php`

**Sprawdź czy PHP jest zainstalowane:**
```powershell
php -v
```

**⚠️ Uwagi:**
- Serwer **MUSI** być uruchomiony w folderze `WAPM-app/` (folder główny)
- NIE używaj Python Server - nie obsługuje PHP!
- Backend wymaga PHP z rozszerzeniami curl/json
- Do produkcji użyj Apache/Nginx z SSL

### Struktura katalogów:
```
WAPM-app/              ← Uruchom serwer TUTAJ: php -S localhost:8000
├── backend/
│   ├── .env
│   ├── config.php
│   └── api.php
└── frontend/
    └── index.html     ← Otwórz http://localhost:8000/frontend/index.html
```

## 📋 Funkcje

- ✅ **4 Kategorie**: Karetka 🚑, SZOP 🚚, Grupa 🚶, Telefon 📱
- ✅ **Real-time tracking** (odświeżanie co 15s)
- ✅ **Status offline** (szare ikony po 10min)
- ✅ **Filtrowanie** (pokaż/ukryj kategorie)
- ✅ **Wyszukiwarka** (nazwa + opis)
- ✅ **Nawigacja** (Google Maps integration)
- ✅ **PWA** (instalowalne, działa offline)
- ✅ **Bezpieczne** (SHA256 hash, HTTPS required)

## 📡 Konfiguracja Traccar

W panelu Traccar ustaw dla każdego urządzenia:

**Category:**
- `ambulance` → 🚑 Czerwony
- `pickup` → 🚚 Pomarańczowy
- `person` → 🚶 Niebieski
- `mobile` → 📱 Zielony

**Description (atrybut):**
```
Key: description
Type: String
Value: Jan Kowalski, Anna Nowak
```

## 🔧 Wymagania

- PHP 7.4+ (curl, json, openssl)
- SSL Certificate (wymagane dla PWA)
- Dostęp do Traccar API

## 📚 Dokumentacja

Pełna dokumentacja: [DOCUMENTATION.md](DOCUMENTATION.md)

## 🔐 Bezpieczeństwo

- [x] SHA256 hash passwords
- [x] HTTPS required
- [x] CORS whitelisting
- [x] Basic Auth do Traccar
- [x] Timing-safe comparison

## 📱 PWA Features

- Offline mode (Service Worker)
- Add to Home Screen
- Push notifications ready
- Background sync ready

## 🛠️ Troubleshooting

**Brak danych na mapie?**
```bash
# Test API
curl "https://twoja-domena.com/backend/api.php?pass=secure123"

# Sprawdź logi PHP
tail -f /var/log/apache2/error.log
```

**CORS error?**
- Sprawdź `ALLOWED_ORIGINS` w config.php
- Upewnij się że frontend i backend są na tej samej domenie lub CORS jest skonfigurowany

**PWA nie instaluje się?**
- Wymagany HTTPS
- Sprawdź manifest.json (valid JSON)
- Service Worker musi być zarejestrowany

## 📄 Struktura Projektu

```
WAPM-app/
├── backend/
│   ├── .env.example        # Template konfiguracji
│   ├── .env                # Konfiguracja (credentials)
│   ├── config.php          # Ładuje zmienne z .env
│   ├── api.php             # PHP Proxy API
│   ├── .htaccess           # Zabezpieczenia Apache
│   └── .gitignore          # Ignorowane pliki
├── frontend/
│   ├── index.html          # Główna aplikacja PWA
│   ├── manifest.json       # PWA manifest
│   └── service-worker.js   # Offline support
├── .gitignore              # Główny gitignore
├── DOCUMENTATION.md        # Pełna dokumentacja
└── README.md              # Ten plik
```

## 🎯 Stack Technologiczny

- **Backend**: Vanilla PHP (bez frameworków)
- **Frontend**: Vanilla JavaScript + Leaflet.js
- **Map**: OpenStreetMap + Leaflet
- **PWA**: Service Workers, manifest.json
- **Security**: SHA256, HTTPS, CORS

## 📞 Wsparcie

Dokumentacja zawiera:
- Data Flow diagram
- API Specification (Input/Output)
- Instrukcje konfiguracji Traccar
- PHPDoc/JSDoc dla każdej funkcji
- Troubleshooting guide

---

**Version:** 1.0  
**License:** Proprietary  
**Author:** Senior Fullstack Developer Team
