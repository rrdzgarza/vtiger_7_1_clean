# Guía de Migración y Despliegue Vtiger CRM 7.1 (Dokploy)

Esta documentación detalla los pasos para migrar una instancia de Vtiger existente (ej. Google Cloud) a un entorno contenedorizado en Dokploy, asegurando la persistencia de datos y la correcta configuración de permisos y seguridad.

## 1. Migración de Base de Datos
Antes de desplegar la aplicación, necesitamos preparar la base de datos externa en Dokploy.

### 1.1. Backup del Origen (Google Cloud u Otro)
Ejecuta este comando desde una máquina con acceso a la base de datos origen.
```bash
# Comando usado para el backup (ajusta IP y credenciales)
mysqldump -h 130.211.204.194 -u root -p \
  --routines --triggers --events --single-transaction --hex-blob --set-gtid-purged=OFF \
  --result-file=vtiger_20260215.sql vtiger
```

### 1.2. Restauración en Dokploy (MySQL 5.7 Project)
Este proceso se realiza **directamente en el VPS** donde instalaste Dokploy.

1.  **Subir el Backup:** Sube el archivo `.sql` al VPS (ej. a `/tmp`).
    ```bash
    scp vtiger_20251130.sql usuario@tu-vps:/tmp/
    ```

2.  **Ubicar el Contenedor de MySQL:**
    En el VPS, busca el ID del contenedor de tu servicio MySQL (creado con Dokploy).
    ```bash
    sudo docker ps --format "table {{.ID}}\t{{.Names}}\t{{.Ports}}" | grep mysql
    # Anota el CONTAINER ID (ej. 50605d0a1af1)
    ```

3.  **Ejecutar la Restauración:**
    Usa el comando `docker exec` para inyectar el SQL directamente al contenedor.
    *(Asegúrate de tener el password de root o del usuario definido en Dokploy)*.

    ```bash
    # Sintaxis: sudo docker exec -i [CONTAINER_ID] mysql -u [USER] -p[PASSWORD] [DB_NAME] < [ARCHIVO_HOST]
    
    sudo docker exec -i da8c61420798 mariadb -u root -pKerakae1 vtiger < /tmp/vtiger_20260215.sql
    ```
    sudo docker exec -i 240f5039b6b2 mariadb -u root -pKerakae1 vtiger < /tmp/vtiger_20260215.sql
    ```
    sudo docker exec -i d2be09415449 mariadb -u root -pKerakae1 vtiger < /tmp/vtiger_20260215.sql
    
---

## 2. Configuración del Servicio Vtiger en Dokploy
Crea un servicio **Application** (o Docker Compose) en Dokploy apuntando a este repositorio (o tu imagen construida).

### 2.1. Variables de Entorno y Parametrización
Configura las siguientes variables en la sección "Environment" de Dokploy para conectar la aplicación con la BD y ajustar el comportamiento:

| Variable | Valor Recomendado | Descripción |
| :--- | :--- | :--- |
| `DB_HOSTNAME` | `mysql-service` (o IP) | Host del servicio MySQL 5.7. |
| `DB_PORT` | `3306` | Puerto de la BD. |
| `DB_NAME` | `vtiger` | Nombre de la base de datos restaurada. |
| `DB_USERNAME` | `root` (o usuario) | Usuario de la BD. |
| `DB_PASSWORD` | `******` | Contraseña de la BD. |
| `DB_SSL` | `false` | `false` para conexión interna Docker (evita error de conexión). |
| `SITE_URL` | `https://tu-dominio.com` | URL pública final (Https). |
| `TIMEZONE` | `America/Monterrey` | Ajusta la hora del sistema y PHP. |
| `ENABLE_DEBUG` | `false` | `true` solo para ver errores en pantalla (Production: false). |
| `SKIP_PERMISSIONS`| `false` | `true` para saltar el fix de permisos en rearranques rápidos (evita Timeout 504). |
| `FORCE_RECALCULATE`| `false` | `true` UNA VEZ si necesitas regenerar privilegios rotos. Dejar en `false` normalmente. |

---

## 3. Despliegue Inicial (Deploy)
1.  Haz clic en **"Deploy"** en Dokploy.
2.  Espera a que el contenedor inicie.
    *   *Nota:* El primer arranque puede tardar unos minutos debido a la asignación de permisos (`chown -R`).
3.  **Monitoreo:** Revisa los logs. Deberías ver:
    *   `[HOOK] 3-config-main.sh: Core Configuration`
    *   `[HOOK] Permisos aplicados.`

---

## 4. Asignación de Dominio
1.  En Dokploy, ve a la sección **"Domains"**.
2.  Agrega tu dominio (ej. `crm.tuempresa.com`).
3.  Activa **SSL (Let's Encrypt)**.
4.  El puerto del contenedor debe ser **80**.

---

## 5. Restauración de Archivos (Backup de Vtiger)
Una vez que el contenedor está corriendo (aunque se vea "limpio" o "roto"), necesitamos inyectar los archivos de tu Vtiger original (imágenes, documentos, módulos custom).

### 5.1. Copia al VPS
Sube tu backup de archivos (`vtiger_files.tar.gz` o carpeta) al servidor VPS donde corre Dokploy.
```bash
scp -r /ruta/local/vtiger_backup usuario@tu-vps:/home/usuario/
```

### 5.2. Inyección al Contenedor
Usa los scripts provistos en la carpeta `/restaura` del repositorio (ahora copiados al contenedor o ejecútalos desde el host Docker).

**Opción A: Desde el Host Docker (Recomendado)**
Ejecuta los scripts que están en `vtiger_7_1_clean/restaura/`:

1.  **Copiar Archivos Globales:**
    Este script copia todo el código fuente del backup al contenedor, sobrescribiendo la instalación limpia.
    ```bash
    # Edita el script para ajustar rutas CONTAINER y BACKUP
    ./restaura/1\ -\ Copia\ archivos.sh
    ```

2.  **Copiar User Privileges (Crítico):**
    Restaura los permisos y accesos específicos de tu instalación.
    ```bash
    ./restaura/2\ -\ Copia\ UserPrivilegies.sh
    ```

---

## 6. Re-Deploy y Ajuste Final
Después de copiar los archivos "viejos", es probable que `config.inc.php` se haya sobrescrito con valores viejos (rutas incorrectas, DB vieja).

1.  **Vuelve a hacer "Deploy"** (o "Redeploy") en Dokploy.
    *   **¿Por qué?** Al reiniciar, nuestros scripts de inicio (`docker-entrypoint-init.d/*`) se ejecutarán nuevamente.
    *   El script `3-config-main.sh` detectará las variables de entorno (`DB_...`, `SITE_URL`) y **corregirá automáticamente** el `config.inc.php` que acabas de restaurar.
    *   El script `4-config-patches.sh` aplicará los parches de Proxy Reversa y regenerará `tabdata.php` para asegurar compatibilidad.

## 7. Verificación
1.  Espera a que el contenedor esté `Healthy`.
2.  Accede a `https://tu-dominio.com`.
3.  Ingresa con tus credenciales.
4.  Verifica que módulos como "Productos" o "Contactos" carguen correctamente (sin pantalla blanca).

---

## Solución de Problemas Comunes

### Pantalla Blanca (WSOD) en Módulos
*   **Causa:** Archivos `tabdata.php` o módulos desactualizados.
*   **Solución:** Reinicia el contenedor. El script `11-force-refresh-tabdata.sh` se ejecuta al inicio y regenera el mapa de módulos automáticamente.

### Error 504 Gateway Timeout al Desplegar
*   **Causa:** El proceso de permisos (`chown`) tarda demasiado en carpetas grandes (`storage`).
*   **Solución:** Después del primer despliegue exitoso, agrega la variable `SKIP_PERMISSIONS=true` en Dokploy.

### Se borran los permisos/usuarios al reiniciar
*   **Causa:** Regeneración forzada de privilegios.
*   **Solución:** Asegúrate de que `FORCE_RECALCULATE` esté en `false` (o no definida). Solo ponla en `true` si realmente necesitas reconstruir los permisos desde cero.
