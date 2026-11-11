-- Para una base tinnitus ya creada con la versión anterior de schema.sql.
-- Agrega la explicación opcional del rechazo y pasa los motivos escritos a mano
-- a la nueva lista fija ("Otro" + el texto original como explicación).
USE tinnitus;

ALTER TABLE solicitudes
    ADD COLUMN detalle_rechazo VARCHAR(255) NULL AFTER motivo_rechazo;

UPDATE solicitudes
SET detalle_rechazo = motivo_rechazo,
    motivo_rechazo  = 'Otro'
WHERE estado = 'rechazada'
  AND motivo_rechazo NOT IN ('Ya existe en el catálogo', 'Datos incompletos', 'Información incorrecta', 'Otro');
