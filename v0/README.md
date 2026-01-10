# Instalación de Vtiger 7.1 en Dokploy

Esta guía detalla cómo desplegar Vtiger CRM 7.1 utilizando Dokploy, asegurando que la redirección HTTPS funcione correctamente con Traefik.

## Prerrequisitos

- Cuenta en Dokploy activa.
- Repositorio Git con estos archivos (`vtiger_7_1_clean`).
- Base de datos MySQL externa lista (Host, Usuario, Contraseña, DB).

## Pasos de Instalación

### 1. Crear el Servicio en Dokploy

1.  Entra a tu proyecto en Dokploy.
2.  Haz clic en **"Create Service"** -> **"Compose"** (Recomendado para usar vólumenes definidos) o **"Application"** (usando Dockerfile).
    *   *Nota: Si usas "Compose", asegúrate de seleccionar Docker Compose Stack.*
    *   *Nota: Si usas "Application", selecciona `Dockerfile` como tipo de build.*

### 2. Configuración del Entorno

Ve a la pestaña **Environment** y agrega las variables que definiste en tu `.env` (o usa el `.env.example` como guía).

```bash
DB_HOSTNAME=tu_host_mysql
DB_USERNAME=tu_usuario_mysql
DB_PASSWORD=tu_password_seguro
DB_NAME=nombre_base_datos
SITE_URL=https://crm.tudominio.com  <-- IMPORTANTE: Debe coincidir con tu dominio en Dokploy
```

> **Nota:** Si llenas estas variables, el contenedor intentará pre-configurar el archivo `config.inc.php`. Si prefieres correr el asistente de instalación desde cero, **NO** agregues estas variables todavía, o asegúrate de borrarlas si falla.

### 3. Volúmenes (Persistencia)

Si elegiste **Compose**, Dokploy leerá el `docker-compose.yml` y creará los volúmenes automágicamente:
- `vtiger_data`: Para el código base `/var/www/html`.
- `vtiger_storage`: Para adjuntos `/var/www/html/storage`.

Si elegiste **Application** (Dockerfile directo), ve a la pestaña **Volumes** y agrega:
- Host Path: (Déjalo vacío o usa una ruta local) -> Container Path: `/var/www/html`
- Host Path: (Déjalo vacío o ruta local) -> Container Path: `/var/www/html/storage`

### 4. Dominios y HTTPS (Traefik)

1.  Ve a la pestaña **Domain**.
2.  Agrega tu dominio (ej: `crm.tudominio.com`).
3.  El puerto del contenedor debe ser **80**.
4.  Activa **HTTPS** (Let's Encrypt).

### 5. Despliegue

1.  Haz clic en **Deploy**.
2.  Espera a que el log muestre que Apache ha iniciado (`exec apache2-foreground`).

### 6. Verificación y Fix SSL

Gracias al archivo `vtiger-ssl-fix.php` incluido en esta imagen, la redirección infinita (Error 301) que ocurre comúnmente con Traefik debería estar resuelta automáticamente.

1.  Abre `https://crm.tudominio.com`.
2.  Deberías ver el **Asistente de Instalación de Vtiger** (si no configuraste variables de entorno) o la pantalla de Login (si ya tenías una BD configurada).

## Solución de Problemas常见

- **Error de Base de Datos**: Revisa el log del contenedor. Asegúrate de que el host de la BD sea accesible desde el contenedor.
- **Permisos de Archivos**: El `Dockerfile` y `entrypoint` se encargan de esto (`chown www-data`). Si tienes problemas al subir archivos, verifica los volúmenes.
- **Redirección Infinita**: Verifica que el archivo `vtiger-ssl-fix.php` se esté cargando. Puedes entrar a la consola del contenedor y verificar `/usr/local/etc/php/conf.d/vtiger-ssl-fix.ini`.
