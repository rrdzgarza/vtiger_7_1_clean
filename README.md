# Vtiger 7.1 Docker Deployment (Manual Restoration)

This project provides a **clean, Dockerized environment for Vtiger CRM 7.1** designed to work behind a reverse proxy (like Traefik) and populate data via a manual restoration process from an existing backup.

## Key Features

*   **Base Image**: `php:7.2-apache` (Compatible with Vtiger 7.x).
*   **Production Aligned**: Configuration matches standard VPS setups (Timezone `America/Monterrey`, Extensions `intl`, `gmp`, etc.).
*   **Auto-Configuration**: The container automatically updates `config.inc.php` with:
    *   Database credentials from environment variables (`DB_HOSTNAME`, `DB_USERNAME`, etc.).
    *   `$site_URL` from the `SITE_URL` environment variable.
    *   **Reverse Proxy Fix**: Automatically injects SSL/HTTPS handling logic to prevent "White Screen of Death" (WSOD).
*   **Maintenance Tools**: Includes `nano`, `recalculate.php` (for privilege regeneration), and `test_debug.php` built-in.

## Prerequisites

*   **Docker & Docker Compose** (or Dokploy).
*   **Vtiger Backup**: A local folder containing your PHP source code (`config.inc.php`, `modules`, `user_privileges`, etc.).

## Deployment Steps

1.  **Deploy Container**:
    Push to your git repository or deploy via Dokploy.
    Environment Variables required:
    *   `DB_HOSTNAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`
    *   `SITE_URL` (e.g., `https://vtiger.example.com`)

2.  **Verify Status**:
    The container will start but return a **404 Not Found**. This is **expected** because the web folder is empty.

3.  **Restore Application (Manual)**:
    Use the provided script in the `restaura-vtiger` folder to copy your code and fix permissions.

    **Script: `3 - Configura.sh`** (Adjusted for your needs)
    *   Edit the script to set `CONTAINER="vtiger_crm"` (or dynamic).
    *   Edit `NEW_SITE_URL` at the top of the script.
    *   Run it: `./3\ -\ Configura.sh`

    *Note: Ensure you have run the copy/permission scripts if they are separate, or use the all-in-one approach.*

## Troubleshooting

*   **White Screen (WSOD)**:
    *   Usually caused by `config.inc.php` settings or missing `user_privileges`.
    *   Run `https://your-domain.com/recalculate.php` to regenerate privileges.
    *   Check `https://your-domain.com/test_debug.php` for environment health.

*   **Redirect Loops**:
    *   The container includes a "Proxy Fix" that forces `$_SERVER['HTTPS'] = 'on'`.
    *   Ensure your `SITE_URL` is set to `https://...`.

*   **Editing Files**:
    *   Connect to container: `docker exec -it vtiger_crm bash`
    *   Edit: `nano config.inc.php`

## Docker Structure

*   **/var/www/html**: persistent volume (Web Root).
*   **/usr/src/vtiger-tools**: Maintenance scripts backup location.
