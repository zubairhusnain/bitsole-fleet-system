# Live Server Setup Instructions

To ensure real-time alerts work on your live server, you need to run two background processes:
1. `reverb:start` - The WebSocket server.
2. `alerts:poll` - The database polling service.

## Step 1: Install Systemd Services

1.  **Edit the service files:**
    Open `setup/reverb.service` and `setup/alerts-poll.service` and ensure the `WorkingDirectory` matches your live server path (e.g., `/var/www/test.softdares.com/html/backend`).

2.  **Copy to systemd:**
    ```bash
    sudo cp setup/reverb.service /etc/systemd/system/
    sudo cp setup/alerts-poll.service /etc/systemd/system/
    ```

3.  **Reload and Start:**
    ```bash
    sudo systemctl daemon-reload
    sudo systemctl enable --now reverb
    sudo systemctl enable --now alerts-poll
    ```

## Step 2: Verify Status

Check if they are running:
```bash
sudo systemctl status reverb
sudo systemctl status alerts-poll
```

If `reverb` is failing, check if port 8000 is free.
If `alerts-poll` is failing, check the logs:
```bash
journalctl -u alerts-poll -f
```
