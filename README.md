# Sistema de Votaciones

Panel de administración y sitio público para gestionar votaciones.

## Requisitos

- PHP 8.0+
- MySQL 5.7+
- Servidor web (Apache, Nginx, XAMPP, WAMP)
- Servidor de correo SMTP (para notificaciones)

## Instalación

### 1. Importar base de datos

```bash
mysql -u root -p < sql/setup.sql
```

O desde MySQL Workbench/phpmyadmin:
1. Ejecutar el contenido de `sql/setup.sql`

### 2. Configurar conexión a DB

Editar `includes/database.php` si es necesario:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_votaciones');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Configurar email (notificaciones)

Editar `includes/mail_config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tuemail@gmail.com');
define('SMTP_PASS', 'tu-app-password');
define('SMTP_FROM', 'tuemail@gmail.com');
define('SMTP_FROM_NAME', 'Sistema de Votaciones');

define('ADMIN_EMAIL', 'admin@votaciones.com');
define('NOTIFICAR_VOTOS', true);
```

**Para Gmail:** Necesitas generar una "App Password" en:
1. Mi Cuenta > Seguridad > Contraseñas de aplicaciones
2. Seleccionar "Otro" y generar la contraseña

### 4. Credenciales iniciales

- **Email:** admin@votaciones.com
- **Contraseña:** password (hash bcrypt predefinido)

** IMPORTANTE:** Cambiar la contraseña después del primer login.

### 5. Acceso

- Panel Admin: `http://localhost/votaciones/admin/`
- Página de votación: `http://localhost/votaciones/`

## Estructura

```
votaciones/
├── admin/
│   ├── index.php
│   ├── dashboard.php
│   ├── elecciones.php
│   ├── candidatos.php
│   ├── reportes.php
│   ├── admins.php
│   └── logout.php
├── includes/
│   ├── database.php
│   ├── functions.php
│   ├── mail_config.php
│   └── navbar.php
├── sql/
│   └── setup.sql
└── index.php
```

## Características

- Autenticación con protección contra fuerza bruta (bloqueo 15 min tras 5 intentos)
- Registro de auditoría (logs de todas las acciones)
- Votación segura por IP + User Agent hash
- Estados: Borrador → Activa → Cerrada → Publicada
- Exportación CSV de resultados
- **Notificaciones por email** al admin cuando alguien vota
