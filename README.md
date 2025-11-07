# Omayer Fleet System — Setup (ENV‑first), WebSocket & Traccar

## Project Overview
Omayer Fleet System is a Laravel + Vue application for fleet tracking, geofencing, and telemetry, integrated with Traccar. It supports a combined PostgreSQL database with Traccar or MySQL, and uses Laravel Reverb for real‑time updates.

Key capabilities
- Real‑time tracking via WebSocket (Laravel Reverb) with Echo/Pusher‑compatible client
- Zone management with canonical WKT saved to Traccar; Edit renders WKT and auto‑fits the map
- Telemetry decoding:
  - Fuel: percent from keys 89 → 48; liters from key 84; converts percent to liters using Fuel Tank Capacity
  - Odometer: prioritizes io87 → io50 → named odometer/mileage → distance fallbacks; normalized to kilometers
- Database: PostgreSQL shared with Traccar (recommended) or MySQL/MariaDB; migrations create app tables alongside Traccar’s
- Frontend: Vite dev server for local development; optimized production build

This guide shows how to configure `.env`, start the WebSocket server, connect to Traccar, and deploy. Follow the steps in order.

## 1) Prerequisites
- PHP 8.1+ and Composer 2+
- Node.js 18+ and npm
- PostgreSQL (or MySQL/MariaDB). Project supports PostgreSQL and can share a DB with Traccar.
- A running Traccar server (URL + user/password or API token)
- Web server pointing to `backend/public` (Apache/Nginx). XAMPP works on macOS.

## 2) Clone and install
From the project root:

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install
```

## 3) Configure `.env` (backend/.env)
Set these keys; adjust values for your environment.

### App
```
APP_NAME=Omayer Fleet System
APP_ENV=local            # use production on live
APP_DEBUG=true           # use false on live
APP_URL=http://localhost # set live domain in production
```

### Database
Use PostgreSQL if you’re sharing a single DB with Traccar.

PostgreSQL:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
# Optional:
DB_SCHEMA=public         # Traccar default schema
```

MySQL (if you prefer MySQL/MariaDB):
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### Traccar (choose either username/password or token)
```
TRACCAR_BASE_URL=https://your-traccar.example.com
TRACCAR_USERNAME=your_user     # optional if using token
TRACCAR_PASSWORD=your_pass     # optional if using token
TRACCAR_API_TOKEN=your_token   # alternative to user/pass
```

### Broadcasting / WebSocket (Laravel Reverb)
```
BROADCAST_CONNECTION=reverb

# Reverb server (WS daemon)
REVERB_SERVER=reverb
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=6001
REVERB_SERVER_PATH=

# Reverb client (Pusher-compatible)
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=127.0.0.1          # set your domain in production
REVERB_PORT=6001
REVERB_SCHEME=http             # use https on live

# Scaling (optional)
REVERB_SCALING_ENABLED=false   # enable only with Redis
```

## 4) Start services (development)
```bash
npm run dev            # Vite dev server (http://localhost:5174/)
php artisan serve     # or use Apache/Nginx pointing to backend/public
php artisan reverb:start  # WebSocket server; keep running during tests
```

## 5) Frontend WebSocket client
If using Echo (Pusher connector), configure it to match env:
- key → `REVERB_APP_KEY`
- host → `REVERB_HOST`
- port → `REVERB_PORT`
- scheme → `REVERB_SCHEME` (http/https)

In production, terminate TLS at your proxy and ensure Echo uses `wss` (`REVERB_SCHEME=https`).

## 6) Production deployment (live)
- Set in `.env`:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
  - `REVERB_HOST=your-domain`, `REVERB_PORT=6001`, `REVERB_SCHEME=https`
- Build frontend:
  ```bash
  npm ci --omit=dev
  npm run build
  ```
- Web server: Apache/Nginx root must be `backend/public`
- Permissions: ensure `backend/storage` and `backend/bootstrap/cache` are writable
- WebSocket daemon: run `php artisan reverb:start` under Supervisor/systemd; auto‑restart on failure
- Optional cache:
  ```bash
  php artisan config:cache
  php artisan route:cache
  ```

## Database (PostgreSQL + Traccar Combined)
- Enable PHP `pdo_pgsql` extension.
- Use one PostgreSQL database for both the application and Traccar.
  - Example: `CREATE DATABASE omayer_fleet;` with schema `public`.
- Point `backend/.env` to this DB using `DB_CONNECTION=pgsql`.
- If Traccar tables already exist, run application migrations once:
  - `php artisan migrate` — creates app tables alongside Traccar tables.
- Do not drop/rename Traccar tables from app migrations.
- If you later separate DBs, define a second connection in `config/database.php` and point Traccar models to it.

## Traccar tips
- Provide base URL + credentials or token in `.env`.
- Geofences: the app saves canonical WKT to Traccar; the Edit page parses WKT and auto‑fits the map.
- Telemetry:
  - Fuel: percent from keys 89 → 48; liters from key 84. Set Fuel Tank Capacity to convert percent into liters.
  - Odometer: prioritize io87 → io50 → named odometer/mileage → distance fallbacks; units normalized to km.

## Troubleshooting
- Vite not reachable: `npm run dev` then open `http://localhost:5174/`.
- WebSocket not connecting:
  - Check `REVERB_HOST/PORT/SCHEME` in `.env`.
  - Ensure `php artisan reverb:start` is running and your proxy allows WS.
- Git remote already exists:
  ```bash
  git remote set-url origin <your_repo_url>
  # or
  git remote remove origin && git remote add origin <your_repo_url>
  ```

## Support
- Laravel logs: `backend/storage/logs`
- Clear and re‑cache config if env changes:
  ```bash
  php artisan config:clear
  php artisan config:cache
  ```
