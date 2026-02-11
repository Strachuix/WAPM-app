# Railway Deployment Guide dla WAPM GPS Tracker

## Instrukcja wdrożenia na Railway.app

### 1. Przygotowanie

1. Stwórz konto na [Railway.app](https://railway.app)
2. Zainstaluj Railway CLI (opcjonalnie):
   ```bash
   npm i -g @railway/cli
   ```

### 2. Deploy przez GitHub (ZALECANE)

1. Wypchnij kod do repozytorium GitHub
2. W Railway Dashboard:
   - Kliknij "New Project"
   - Wybierz "Deploy from GitHub repo"
   - Autoryzuj Railway do dostępu do GitHub
   - Wybierz repozytorium WAPM-app

### 3. Konfiguracja zmiennych środowiskowych

W Railway Dashboard → Variables dodaj:

```
TRACCAR_URL=https://demo.traccar.org/api/positions
TRACCAR_USER=stanislawpokropek@gmail.com
TRACCAR_PASSWORD=twoje-haslo
ACCESS_PASSWORD=secure123
ALLOWED_ORIGINS=https://twoja-domena.railway.app,https://twoja-domena.up.railway.app
CURL_TIMEOUT=30
TIMEZONE=Europe/Warsaw
```

**WAŻNE:** Zaktualizuj `ALLOWED_ORIGINS` o domenę Railway (znajdziesz ją w Settings → Domains)

### 4. Deploy

Railway automatycznie:
- Wykryje PHP projekt
- Zbuduje używając konfiguracji z `nixpacks.toml`
- Uruchomi PHP built-in server z router.php
- Wdroży aplikację

**Uwaga:** Aplikacja używa PHP built-in server z custom routerem dla uproszczenia deploymentu.

### 5. Po deployment

1. Otwórz domenę Railway (np. `https://wapm-production.up.railway.app`)
2. Zaloguj się używając hasła z `ACCESS_PASSWORD`
3. Sprawdź czy urządzenia są widoczne na mapie

### 6. Custom Domain (opcjonalnie)

W Railway Dashboard → Settings → Domains:
- Dodaj swoją domenę
- Zaktualizuj DNS CNAME na Railway
- Aktualizuj `ALLOWED_ORIGINS` w zmiennych środowiskowych

### 7. Troubleshooting

**Problem:** Backend API 502/503
- Sprawdź logi: `railway logs --tail 100`
- Upewnij się że zmienne środowiskowe są ustawione
- Sprawdź czy CORS origin zawiera domenę Railway
- Sprawdź czy backend/.env istnieje (Railway używa zmiennych środowiskowych)

**Problem:** Frontend nie ładuje się
- Sprawdź logi deploymentu
- Upewnij się że router.php działa poprawnie
- Railway automatycznie ustawia zmienną `PORT`

**Problem:** Brak połączenia z Traccar
- Sprawdź credentials w zmiennych środowiskowych
- Upewnij się że Traccar URL jest poprawny
- Sprawdź timeout (zwiększ `CURL_TIMEOUT` jeśli potrzeba)

**Problem:** Ikony SVG nie ładują się
- Upewnij się że router.php poprawnie obsługuje /frontend/assets/
- Sprawdź MIME types w router.php

### 8. Monitorowanie

Railway zapewnia:
- Logi w czasie rzeczywistym
- Metryki CPU/RAM
- Restart automatyczny przy błędach

### 9. Aktualizacje

Każdy push do głównej gałęzi GitHub automatycznie wdraża nową wersję.

### 10. Koszty

Railway oferuje:
- $5 miesięcznie w darmowym planie (hobby)
- Płatność za faktyczne użycie zasobów

## Pliki konfiguracyjne Railway

- `nixpacks.toml` - Konfiguracja buildu (PHP 8.2 z rozszerzeniami)
- `router.php` - Custom router dla PHP built-in server
- `railway.json` - Ustawienia deployment
- `.env.example` - Przykład zmiennych środowiskowych
- `nginx.conf` - (opcjonalny) Konfiguracja Nginx dla bardziej zaawansowanych deploymentów

## Uwagi bezpieczeństwa

✅ `.env` jest w `.gitignore` - nie commituj credentials!
✅ Używaj zmiennych środowiskowych Railway
✅ Regularnie zmieniaj `ACCESS_PASSWORD`
✅ Ogranicz `ALLOWED_ORIGINS` tylko do zaufanych domen
