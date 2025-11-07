Omayer Fleet System — Step‑by‑Step Setup (ENV‑first), WebSocket & Traccar

This guide shows exactly how to configure your .env, start the WebSocket server, and connect to Traccar. Follow the steps in order.

1) Prerequisites
- PHP 8.1+ and Composer 2+
- Node.js 18+ and npm
- PostgreSQL (or MySQL/MariaDB). Project supports PostgreSQL and can share a DB with Traccar.
- A running Traccar server (URL + user/password or API token)
- Web server pointing to `backend/public` (Apache/Nginx). XAMPP works on macOS.

2) Clone and install
- From project root: `cd backend`
- Copy env: `cp .env.example .env`
- Install backend: `composer install`
- Init app: `php artisan key:generate && php artisan migrate && php artisan storage:link`
- Install frontend: `npm install`

3) Configure .env (backend/.env)
Set these keys; adjust values for your environment.

- App
  - `APP_NAME=Omayer Fleet System`
  - `APP_ENV=local` (use `production` on live)
  - `APP_DEBUG=true` (use `false` on live)
  - `APP_URL=http://localhost` (set your live domain in production)

- Database
  - For PostgreSQL (shared with Traccar):
    - `DB_CONNECTION=pgsql`
    - `DB_HOST=127.0.0.1`
    - `DB_PORT=5432`
    - `DB_DATABASE=your_db`
    - `DB_USERNAME=your_user`
    - `DB_PASSWORD=your_password`
    - Optional: `DB_SCHEMA=public` (default schema used by Traccar)
  - For MySQL:
    - `DB_CONNECTION=mysql`
    - `DB_HOST=127.0.0.1`
    - `DB_PORT=3306`
    - `DB_DATABASE=your_db`
    - `DB_USERNAME=your_user`
    - `DB_PASSWORD=your_password`

- Traccar (choose either username/password or token)
  - `TRACCAR_BASE_URL=https://your-traccar.example.com`
  - `TRACCAR_USERNAME=your_user`  (optional if using token)
  - `TRACCAR_PASSWORD=your_pass`  (optional if using token)
  - `TRACCAR_API_TOKEN=your_token` (optional alternative to user/pass)

- Broadcasting / WebSocket (Reverb)
  - `BROADCAST_CONNECTION=reverb`
  - Server (daemon that accepts WS connections):
    - `REVERB_SERVER=reverb`
    - `REVERB_SERVER_HOST=0.0.0.0`
    - `REVERB_SERVER_PORT=6001`
    - `REVERB_SERVER_PATH=` (leave empty)
  - Client (Pusher‑compatible options used by Laravel broadcasting):
    - `REVERB_APP_ID=local`
    - `REVERB_APP_KEY=local`
    - `REVERB_APP_SECRET=local`
    - `REVERB_HOST=127.0.0.1` (set to your domain in production)
    - `REVERB_PORT=6001`
    - `REVERB_SCHEME=http` (use `https` on live)
  - Scaling (optional):
    - `REVERB_SCALING_ENABLED=false` (enable only with Redis)

4) Start services (development)
- Vite: `npm run dev` (opens http://localhost:5174/)
- Laravel: `php artisan serve` (or use Apache pointing to backend/public)
- WebSocket: `php artisan reverb:start`
  - Keep this running while you test events/live features.

5) Frontend WebSocket client
- If you use Echo (Pusher connector), configure it to match env:
  - key → `REVERB_APP_KEY`
  - host → `REVERB_HOST`
  - port → `REVERB_PORT`
  - scheme → `REVERB_SCHEME` (http/https)
- In production, ensure TLS is terminated at your proxy and Echo uses `wss` (`REVERB_SCHEME=https`).

6) Production deployment (live)
- Set in .env:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
  - `REVERB_HOST=your-domain`, `REVERB_PORT=6001`, `REVERB_SCHEME=https`
- Build frontend:
  - `npm ci --omit=dev && npm run build`
- Web server:
  - Apache/Nginx root must be `backend/public`
- Permissions:
  - Ensure `backend/storage` and `backend/bootstrap/cache` are writable
- WebSocket daemon:
  - Run `php artisan reverb:start` under Supervisor/systemd; auto‑restart on failure
- Cache configs/routes (optional):
  - `php artisan config:cache && php artisan route:cache`

Database (PostgreSQL + Traccar Combined)
- Enable PHP pdo_pgsql extension.
- Use a single PostgreSQL database for both the application and Traccar.
  - Create DB (example): `CREATE DATABASE omayer_fleet;`
  - Ensure schema is `public` (Traccar default).
- Point backend/.env to this DB using `DB_CONNECTION=pgsql`.
- If Traccar tables already exist, run application migrations once:
  - `php artisan migrate`
  - This creates app tables without touching Traccar tables.
- Tip: do not drop/rename Traccar tables from app migrations. If you prefer separate DBs, define a second connection in `config/database.php` and point Traccar models to it.

7) Traccar tips
- Provide base URL + credentials or token in .env
- Geofences: the app saves canonical WKT to Traccar; Edit page parses WKT and auto‑fits map
- Telemetry: fuel percent from 89→48, liters from 84; odometer from io87→io50→named keys; set vehicle Fuel Tank Capacity to convert percent into liters

8) Troubleshooting
- Vite not reachable: run `npm run dev` and open http://localhost:5174/
- WebSocket not connecting:
  - Check `REVERB_HOST/PORT/SCHEME` in .env
  - Ensure `php artisan reverb:start` is running and your proxy allows WS
- Git remote already exists:
  - `git remote set-url origin <your_repo_url>`
  - or `git remote remove origin && git remote add origin <your_repo_url>`

Support
- Check Laravel logs in `backend/storage/logs`
- Clear and re‑cache config if env changes: `php artisan config:clear && php artisan config:cache`