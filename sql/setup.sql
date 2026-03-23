-- Base de datos del sistema de votaciones
CREATE DATABASE IF NOT EXISTS sistema_votaciones;
USE sistema_votaciones;

-- Tabla de administradores
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('superadmin', 'admin') DEFAULT 'admin',
    ultimo_login DATETIME NULL,
    intentos_fallidos INT DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de elecciones/configuraciones
CREATE TABLE elecciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    reglas TEXT,
    estado ENUM('borrador', 'activa', 'cerrada', 'publicada') DEFAULT 'borrador',
    permite_votos_multiple BOOLEAN DEFAULT FALSE,
    max_votos_por_usuario INT DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_fechas (fecha_inicio, fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de candidatos
CREATE TABLE candidatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eleccion_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    foto VARCHAR(255) NULL,
    descripcion TEXT,
    propuesta TEXT,
    categoria VARCHAR(100),
    orden INT DEFAULT 0,
    votos_count INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleccion_id) REFERENCES elecciones(id) ON DELETE CASCADE,
    INDEX idx_eleccion (eleccion_id),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de votos
CREATE TABLE votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidato_id INT NOT NULL,
    eleccion_id INT NOT NULL,
    ip_votante VARCHAR(45),
    hash_votante VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
    FOREIGN KEY (eleccion_id) REFERENCES elecciones(id) ON DELETE CASCADE,
    INDEX idx_candidato (candidato_id),
    INDEX idx_eleccion (eleccion_id),
    INDEX idx_hash_votante (hash_votante),
    UNIQUE KEY unique_voto (eleccion_id, hash_votante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de auditoría
CREATE TABLE logs_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50),
    registro_id INT,
    datos_anteriores JSON,
    datos_nuevos JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_admin (admin_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar admin inicial (password: Admin123!)
INSERT INTO admins (nombre, email, password_hash, rol) VALUES
('Administrador Principal', 'admin@votaciones.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin');
