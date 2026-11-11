USE tinnitus;

INSERT INTO usuarios (nombre, username, email, password_hash, rol, bio) VALUES
('Administrador Tinnitus', 'admin', 'admin@tinnitus.mx',
 '$2y$10$pxQ7XKPRzqdrWpyiMStfhe/bC3y1Aw7nha9f/7lv02zpkBOjiWvVa', 'admin',
 'Cuenta oficial de administración de Tinnitus.'),
('Mariana López', 'melomana', 'mariana@correo.com',
 '$2y$10$hSblfkIWW.zwrxHBPvQ5Tu1/0EiDT6YPRU2hSa.zPAIj4Tfsy/JyO', 'usuario',
 'Coleccionista de pulseras de festival. Si hay guitarras, ahí estoy.'),
('Carlos Treviño', 'rockero92', 'carlos@correo.com',
 '$2y$10$hSblfkIWW.zwrxHBPvQ5Tu1/0EiDT6YPRU2hSa.zPAIj4Tfsy/JyO', 'usuario',
 'Rock clásico, metal y lo que suene fuerte.'),
('Sofía Garza', 'indiekid', 'sofia@correo.com',
 '$2y$10$hSblfkIWW.zwrxHBPvQ5Tu1/0EiDT6YPRU2hSa.zPAIj4Tfsy/JyO', 'usuario',
 'Indie, pop alternativo y festivales al aire libre.');

INSERT INTO artistas (nombre, genero, pais) VALUES
('Arctic Monkeys', 'Indie Rock', 'Reino Unido'),
('Taylor Swift', 'Pop', 'Estados Unidos'),
('Metallica', 'Heavy Metal', 'Estados Unidos'),
('Zoé', 'Rock Alternativo', 'México'),
('Bad Bunny', 'Reggaetón', 'Puerto Rico'),
('The Strokes', 'Indie Rock', 'Estados Unidos'),
('Coldplay', 'Pop Rock', 'Reino Unido'),
('Café Tacvba', 'Rock Alternativo', 'México');

INSERT INTO recintos (nombre, ciudad, capacidad, tipo, estado) VALUES
('Estadio BBVA', 'Guadalupe, N.L.', 53500, 'Estadio', 'activo'),
('Arena Monterrey', 'Monterrey, N.L.', 17599, 'Arena', 'activo'),
('Auditorio Citibanamex', 'Monterrey, N.L.', 7000, 'Auditorio', 'activo'),
('Parque Fundidora', 'Monterrey, N.L.', 80000, 'Festival', 'activo'),
('Foro Sol', 'Ciudad de México', 65000, 'Estadio', 'activo'),
('Café Iguana', 'Monterrey, N.L.', 600, 'Bar', 'inactivo');

INSERT INTO conciertos (artista_id, recinto_id, gira, fecha, descripcion, destacado) VALUES
(1, 2, 'The Car Tour', '2023-03-08', 'Noche de crooners y guitarras en la Arena Monterrey.', 1),
(2, 5, 'The Eras Tour', '2023-08-24', 'Tres horas y media recorriendo cada era de su carrera.', 1),
(3, 4, 'M72 World Tour', '2024-09-27', 'Escenario en forma de anillo y pirotecnia a todo lo que da.', 1),
(4, 3, 'Sonidos de Karmática', '2024-05-18', 'Gira de regreso con visuales espaciales.', 0),
(5, 1, 'Most Wanted Tour', '2024-11-30', 'Lleno total en el Estadio BBVA.', 1),
(6, 4, 'Pa''l Norte 2023', '2023-03-31', 'Headliners del primer día del festival.', 0),
(7, 1, 'Music of the Spheres', '2022-03-30', 'Pulseras luminosas y confeti en cada canción.', 1),
(8, 3, 'Un Segundo MTV', '2023-10-14', 'Repaso de clásicos con orquesta invitada.', 0),
(1, 4, 'Pa''l Norte 2026', '2026-03-28', 'Cierre del festival frente a 80 mil personas.', 0),
(7, 1, 'Music of the Spheres 2027', '2027-04-17', 'Regreso de Coldplay al Estadio BBVA.', 1);

INSERT INTO resenas (usuario_id, concierto_id, calificacion, texto) VALUES
(2, 1, 5, 'Alex Turner en su faceta más elegante. "505" al final me dejó sin voz.'),
(3, 1, 4, 'Gran sonido, aunque el setlist pudo tener más canciones del AM.'),
(4, 1, 5, 'Mi primer concierto de ellos y fue perfecto.'),
(2, 2, 5, 'Nunca había visto una producción tan grande. Valió cada peso.'),
(4, 2, 4, 'Larguísimo pero increíble. La era de Folklore fue mi favorita.'),
(3, 3, 5, 'El mejor concierto de metal de mi vida. Sigo con el zumbido.'),
(2, 3, 3, 'Buen show, pero desde mi lugar se escuchaba con mucho eco.'),
(4, 4, 4, 'Zoé siempre cumple. Los visuales estuvieron preciosos.'),
(3, 5, 2, 'Mucha gente y mal acceso, aunque el show estuvo bien.'),
(4, 5, 5, 'Todo el estadio bailando, una locura.'),
(2, 7, 5, 'Las pulseras de luz hacen que todo se sienta mágico.'),
(3, 7, 4, 'Muy emotivo. Chris Martin tiene una energía increíble.'),
(4, 6, 4, 'Julian Casablancas sonó mejor de lo que esperaba.'),
(2, 8, 5, 'La versión de "Eres" con orquesta fue lo mejor de la noche.');

INSERT INTO asistencias (usuario_id, concierto_id) VALUES
(2,1),(2,2),(2,3),(2,7),(2,8),
(3,1),(3,3),(3,5),(3,7),
(4,1),(4,2),(4,4),(4,5),(4,6);

INSERT INTO top4 (usuario_id, posicion, concierto_id) VALUES
(2,1,1),(2,2,2),(2,3,7),(2,4,8),
(3,1,3),(3,2,7),
(4,1,2),(4,2,1),(4,3,6);

INSERT INTO reportes (resena_id, usuario_id, motivo) VALUES
(9, 4, 'Comentario poco constructivo sobre el recinto.');

INSERT INTO solicitudes (usuario_id, tipo, artista, gira, fecha, recinto_id, comentario) VALUES
(2, 'concierto', 'Muse', 'Will of the People', '2023-02-10', 2, 'Fui con mis amigos, ¡faltaba en el catálogo!');
INSERT INTO solicitudes (usuario_id, tipo, nombre, ciudad, capacidad, tipo_recinto, comentario) VALUES
(3, 'recinto', 'Showcenter Complex', 'San Pedro Garza García, N.L.', 4000, 'Foro', 'Ahí tocan muchas bandas de rock.');
INSERT INTO solicitudes (usuario_id, tipo, artista, gira, fecha, recinto_id, estado, motivo_rechazo, detalle_rechazo, revisado_por, revisado_en) VALUES
(4, 'concierto', 'Zoé', 'Sonidos de Karmática', '2024-05-18', 3, 'rechazada', 'Ya existe en el catálogo', 'Ese concierto ya está registrado; búscalo en el catálogo de Zoé.', 1, NOW());

INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES
('Juan Pérez', 'juan@correo.com', 'Falta un concierto', '¿Pueden agregar el concierto de Muse de 2023?');
