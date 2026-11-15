-- Para una base tinnitus ya creada con la versión anterior de schema.sql.
-- Agrega la foto opcional de artistas y recintos (URL externa o ruta dentro de assets/img/).
USE tinnitus;

ALTER TABLE artistas
    ADD COLUMN foto_url VARCHAR(500) NULL AFTER pais;

ALTER TABLE recintos
    ADD COLUMN foto_url VARCHAR(500) NULL AFTER estado;
