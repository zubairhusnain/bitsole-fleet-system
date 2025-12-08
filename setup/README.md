# Live Server Setup Instructions for Alerts & WebSockets

To ensure real-time alerts and websocket notifications work reliably on your live server, you must run background processes using `systemd`.

## Services Overview

We have prepared two service files in this directory:
1.  **`reverb.service`**: Runs the WebSocket server (`php artisan reverb:start`).
2.  **`alerts-poll.service`**: Runs the alert polling script (`php artisan alerts:poll`).

---

## Installation Steps

### 1. Verify Paths
Open `setup/reverb.service` and `setup/alerts-poll.service`.
Ensure the `WorkingDirectory` matches your actual project path on the server.
*   Default: `/var/www/test.softdares.com/html/backend`
*   If your project is elsewhere, update this line in both files.

### 2. Copy Service Files
Run these commands from your project root (e.g., inside `backend/`):

```bash
sudo cp setup/reverb.service /etc/systemd/system/
sudo cp setup/alerts-poll.service /etc/systemd/system/
```

**Why?**
This installs the service definitions into Linux's system manager, allowing the OS to manage these processes.

### 3. Register Services
Tell systemd to read the new files:

```bash
sudo systemctl daemon-reload
```

**Why?**
Systemd needs to refresh its configuration to "see" the new files you just copied.

### 4. Enable Automatic Start
Enable the services so they start automatically if the server reboots:

```bash
sudo systemctl enable reverb alerts-poll
```

**Why?**
Ensures your alerts don't stop working after a server restart or power outage.

### 5. Start Services
Start them immediately:

```bash
sudo systemctl start reverb alerts-poll
```

**Why?**
Starts the processes right now without waiting for a reboot.

---

## Verification & Troubleshooting

### Check Status
Check if the services are active and running:

```bash
sudo systemctl status reverb
sudo systemctl status alerts-poll
```
You should see **`Active: active (running)`** in green.

### View Logs
If something isn't working, check the live logs:

```bash
# Check alert polling logs
journalctl -u alerts-poll -f

# Check websocket server logs
journalctl -u reverb -f
```

### Restarting After Code Changes
If you modify the PHP code for alerts or events, you should restart the services to pick up changes:

```bash
sudo systemctl restart reverb alerts-poll
```
