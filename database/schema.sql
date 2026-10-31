DROP DATABASE IF EXISTS tinnitus;
CREATE DATABASE tinnitus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tinnitus;

CREATE TABLE usuarios (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100)  NOT NULL,
    username       VARCHAR(30)   NOT NULL UNIQUE,
    email          VARCHAR(150)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255)  NOT NULL,
    rol            ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
    bio            VARCHAR(500)  NULL,
    avatar_url     VARCHAR(500)  NULL,
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta   DATETIME   NULL,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_username CHECK (username REGEXP '^[a-z0-9_]{3,30}$')
) ENGINE=InnoDB;

CREATE TABLE artistas (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(120) NOT NULL UNIQUE,
    genero    VARCHAR(60)  NULL,
    pais      VARCHAR(60)  NULL
) ENGINE=InnoDB;

CREATE TABLE recintos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(120) NOT NULL,
    ciudad     VARCHAR(80)  NOT NULL,
    capacidad  INT UNSIGNED NOT NULL,
    tipo       ENUM('Estadio','Arena','Auditorio','Foro','Festival','Bar') NOT NULL,
    estado     ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    CONSTRAINT chk_capacidad CHECK (capacidad BETWEEN 1 AND 200000),
    CONSTRAINT uq_recinto UNIQUE (nombre, ciudad)
) ENGINE=InnoDB;

CREATE TABLE conciertos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artista_id   INT UNSIGNED NOT NULL,
    recinto_id   INT UNSIGNED NOT NULL,
    gira         VARCHAR(150) NOT NULL,
    fecha        DATE         NOT NULL,
    poster_url   VARCHAR(500) NULL,
    descripcion  TEXT         NULL,
    destacado    TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_concierto_artista FOREIGN KEY (artista_id)
        REFERENCES artistas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_concierto_recinto FOREIGN KEY (recinto_id)
        REFERENCES recintos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT uq_concierto UNIQUE (artista_id, recinto_id, fecha),
    CONSTRAINT chk_fecha CHECK (fecha >= '1950-01-01'),
    INDEX idx_conciertos_fecha (fecha)
) ENGINE=InnoDB;

CREATE TABLE resenas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    concierto_id  INT UNSIGNED NOT NULL,
    calificacion  TINYINT UNSIGNED NOT NULL,
    texto         VARCHAR(1000) NOT NULL,
    oculta        TINYINT(1) NOT NULL DEFAULT 0,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 1 AND 5),
    CONSTRAINT chk_texto CHECK (CHAR_LENGTH(texto) BETWEEN 10 AND 1000),
    CONSTRAINT uq_resena UNIQUE (usuario_id, concierto_id),
    CONSTRAINT fk_resena_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_resena_concierto FOREIGN KEY (concierto_id)
        REFERENCES conciertos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reportes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resena_id   INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    motivo      VARCHAR(255) NOT NULL,
    estado      ENUM('pendiente','resuelto') NOT NULL DEFAULT 'pendiente',
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_reporte UNIQUE (resena_id, usuario_id),
    CONSTRAINT fk_reporte_resena FOREIGN KEY (resena_id)
        REFERENCES resenas(id) ON DELETE CASCADE,
    CONSTRAINT fk_reporte_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE asistencias (
    usuario_id    INT UNSIGNED NOT NULL,
    concierto_id  INT UNSIGNED NOT NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, concierto_id),
    CONSTRAINT fk_asistencia_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_asistencia_concierto FOREIGN KEY (concierto_id)
        REFERENCES conciertos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE top4 (
    usuario_id    INT UNSIGNED NOT NULL,
    posicion      TINYINT UNSIGNED NOT NULL,
    concierto_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, posicion),
    CONSTRAINT uq_top4_concierto UNIQUE (usuario_id, concierto_id),
    CONSTRAINT chk_posicion CHECK (posicion BETWEEN 1 AND 4),
    CONSTRAINT fk_top4_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_top4_concierto FOREIGN KEY (concierto_id)
        REFERENCES conciertos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE solicitudes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    tipo            ENUM('concierto','recinto') NOT NULL,
    estado          ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    artista         VARCHAR(120) NULL,
    gira            VARCHAR(150) NULL,
    fecha           DATE         NULL,
    recinto_id      INT UNSIGNED NULL,
    nombre          VARCHAR(120) NULL,
    ciudad          VARCHAR(80)  NULL,
    capacidad       INT UNSIGNED NULL,
    tipo_recinto    ENUM('Estadio','Arena','Auditorio','Foro','Festival','Bar') NULL,
    comentario      VARCHAR(500) NULL,
    motivo_rechazo  VARCHAR(255) NULL,
    creado_id       INT UNSIGNED NULL,
    revisado_por    INT UNSIGNED NULL,
    revisado_en     DATETIME     NULL,
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_solicitud_datos CHECK (
        (tipo = 'concierto' AND artista IS NOT NULL AND gira IS NOT NULL AND fecha IS NOT NULL AND recinto_id IS NOT NULL)
        OR (tipo = 'recinto' AND nombre IS NOT NULL AND ciudad IS NOT NULL AND tipo_recinto IS NOT NULL
            AND capacidad BETWEEN 1 AND 200000)),
    CONSTRAINT fk_solicitud_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_recinto FOREIGN KEY (recinto_id)
        REFERENCES recintos(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicitud_revisor FOREIGN KEY (revisado_por)
        REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_solicitudes_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE mensajes_contacto (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    asunto     VARCHAR(150) NOT NULL,
    mensaje    TEXT NOT NULL,
    leido      TINYINT(1) NOT NULL DEFAULT 0,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
