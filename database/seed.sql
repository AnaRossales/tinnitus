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

-- Fotos de perfil de los usuarios de prueba (las de usuarios reales se suben a uploads/avatars/).
UPDATE usuarios SET avatar_url = CONCAT('assets/img/usuarios/', username, '.jpg');

INSERT INTO artistas (nombre, genero, pais, foto_url) VALUES
('Arctic Monkeys', 'Indie Rock', 'Reino Unido', 'assets/img/artistas/arctic-monkeys.jpg'),
('Taylor Swift', 'Pop', 'Estados Unidos', 'assets/img/artistas/taylor-swift.jpg'),
('Metallica', 'Heavy Metal', 'Estados Unidos', 'assets/img/artistas/metallica.jpg'),
('Zoé', 'Rock Alternativo', 'México', 'assets/img/artistas/zoe.jpg'),
('Bad Bunny', 'Reggaetón', 'Puerto Rico', 'assets/img/artistas/bad-bunny.jpg'),
('The Strokes', 'Indie Rock', 'Estados Unidos', 'assets/img/artistas/the-strokes.jpg'),
('Coldplay', 'Pop Rock', 'Reino Unido', 'assets/img/artistas/coldplay.jpg'),
('Café Tacvba', 'Rock Alternativo', 'México', 'assets/img/artistas/cafe-tacvba.jpg'),
('Panteón Rococó', 'Ska', 'México', 'assets/img/artistas/panteon-rococo.jpg'),
('Milo J', 'Urbano', 'Argentina', 'assets/img/artistas/milo-j.jpg'),
('Guns N'' Roses', 'Hard Rock', 'Estados Unidos', 'assets/img/artistas/guns-n-roses.jpg');

INSERT INTO recintos (nombre, ciudad, capacidad, tipo, estado, foto_url) VALUES
('Estadio BBVA', 'Guadalupe, N.L.', 53500, 'Estadio', 'activo', 'assets/img/recintos/estadio-bbva.jpg'),
('Arena Monterrey', 'Monterrey, N.L.', 17599, 'Arena', 'activo', NULL),
('Auditorio Citibanamex', 'Monterrey, N.L.', 7000, 'Auditorio', 'activo', 'assets/img/recintos/auditorio-citibanamex.jpg'),
('Parque Fundidora', 'Monterrey, N.L.', 80000, 'Festival', 'activo', 'assets/img/recintos/parque-fundidora.jpg'),
('Foro Sol', 'Ciudad de México', 65000, 'Estadio', 'activo', 'assets/img/recintos/foro-sol.jpg'),
('Café Iguana', 'Monterrey, N.L.', 600, 'Bar', 'inactivo', 'assets/img/recintos/cafe-iguana.jpg');

INSERT INTO conciertos (artista_id, recinto_id, gira, fecha, poster_url, descripcion, destacado) VALUES
(1, 2, 'The Car Tour', '2023-03-08', 'assets/img/posters/arctic-monkeys-the-car-tour.jpg', 'Noche de crooners y guitarras en la Arena Monterrey.', 1),
(2, 5, 'The Eras Tour', '2023-08-24', 'assets/img/posters/taylor-swift-eras-tour.jpg', 'Tres horas y media recorriendo cada era de su carrera.', 1),
(3, 4, 'M72 World Tour', '2024-09-27', 'assets/img/posters/metallica-m72.jpg', 'Escenario en forma de anillo y pirotecnia a todo lo que da.', 1),
(4, 3, 'Sonidos de Karmática', '2024-05-18', 'assets/img/posters/zoe-sonidos-de-karmatica.jpg', 'Gira de regreso con visuales espaciales.', 0),
(5, 1, 'Debí Tirar Más Fotos World Tour', '2024-11-30', 'assets/img/posters/bad-bunny.jpg', 'Lleno total en el Estadio BBVA.', 1),
(6, 4, 'Pa''l Norte 2023', '2023-03-31', 'assets/img/posters/the-strokes-pal-norte-2023.jpg', 'Headliners del primer día del festival.', 0),
(7, 1, 'Music of the Spheres', '2022-03-30', 'assets/img/posters/coldplay-music-of-the-spheres.jpg', 'Pulseras luminosas y confeti en cada canción.', 1),
(8, 3, 'Un Segundo MTV', '2023-10-14', 'assets/img/posters/cafe-tacvba-un-segundo.jpg', 'Repaso de clásicos con orquesta invitada.', 0),
(1, 4, 'Pa''l Norte 2026', '2026-03-28', 'assets/img/posters/pal-norte-2026.jpg', 'Cierre del festival frente a 80 mil personas.', 0),
(7, 1, 'Music of the Spheres 2027', '2027-04-17', NULL, 'Regreso de Coldplay al Estadio BBVA.', 1),
(9, 5, 'XXX Generación del 95', '2025-11-28', 'assets/img/posters/panteon-rococo.jpg', 'Treinta años de ska con invitados en el antes Foro Sol.', 0),
(10, 3, 'La vida era más corta Tour', '2025-06-02', 'assets/img/posters/milo-j.jpg', 'Primera vuelta de su gira mundial en Monterrey.', 0),
(11, 1, 'Gira 2021', '2021-10-12', 'assets/img/posters/guns-n-roses-monterrey.jpg', 'Regreso a Monterrey con un setlist de casi tres horas.', 0);

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
(2, 8, 5, 'La versión de "Eres" con orquesta fue lo mejor de la noche.'),
(3, 2, 4, 'No soy fan, pero el show es impresionante de principio a fin.'),
(2, 4, 4, 'Sonó increíble aunque el auditorio estaba muy caliente.'),
(3, 6, 3, 'Buen set, pero muy corto para ser headliner.'),
(4, 7, 5, 'Lloré con "Fix You". Una noche que no voy a olvidar.'),
(3, 8, 4, 'Rubén se aventó todos los clásicos. Gran ambiente.'),
(2, 9, 5, 'Cerrar Pa''l Norte con Arctic Monkeys fue perfecto.'),
(4, 9, 4, 'Mucho calor y mucha fila, pero el cierre valió la pena.'),
(2, 11, 5, 'Treinta años y siguen con la misma energía. ¡Puro ska!'),
(3, 11, 4, 'El slam no paró en toda la noche, salí molido pero feliz.'),
(4, 12, 5, 'Milo J canta igual que en el disco. El auditorio retumbaba.'),
(3, 13, 5, 'Axl todavía la rompe. "November Rain" con lluvia de verdad.'),
(2, 13, 4, 'Casi tres horas de rock, aunque el sonido falló al inicio.');

INSERT INTO asistencias (usuario_id, concierto_id) VALUES
(2,1),(2,2),(2,3),(2,7),(2,8),
(3,1),(3,3),(3,5),(3,7),
(4,1),(4,2),(4,4),(4,5),(4,6),
(2,4),(2,9),(2,11),(2,13),
(3,2),(3,6),(3,8),(3,11),(3,13),
(4,7),(4,9),(4,12);

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
INSERT INTO solicitudes (usuario_id, tipo, artista, gira, fecha, recinto_id, estado, motivo_rechazo, detalle_rechazo, creado_id, revisado_por, revisado_en, creado_en) VALUES
(2, 'concierto', 'Panteón Rococó', 'XXX Generación del 95', '2025-11-28', 5, 'aprobada', NULL, NULL, 11, 1, '2025-12-03 10:05', '2025-12-02 18:20'),
(4, 'concierto', 'Milo J', 'La vida era más corta Tour', '2025-06-02', 3, 'aprobada', NULL, NULL, 12, 1, '2026-01-15 09:30', '2026-01-14 21:40'),
(3, 'concierto', 'Guns N'' Roses', 'Gira 2021', '2021-10-12', 1, 'aprobada', NULL, NULL, 13, 1, '2026-02-05 16:45', '2026-02-03 12:00'),
(2, 'concierto', 'Coldplay', 'Music of the Spheres', '2022-03-30', 1, 'rechazada', 'Ya existe en el catálogo', NULL, NULL, 1, '2026-03-11 11:00', '2026-03-10 19:00'),
(3, 'concierto', 'Arctic Monkeys', 'Pa''l Norte 2026', '2026-03-28', 4, 'aprobada', NULL, NULL, 9, 1, '2026-04-02 13:00', '2026-04-02 08:15'),
(3, 'concierto', 'Metalica', 'M72', '2024-09-27', 4, 'rechazada', 'Datos incompletos', 'Falta el nombre completo de la gira y el artista está mal escrito.', NULL, 1, '2026-05-22 09:00', '2026-05-20 22:10'),
(4, 'concierto', 'The Strokes', 'Pa''l Norte 2023', '2023-03-30', 4, 'rechazada', 'Información incorrecta', 'La fecha correcta es el 31 de marzo de 2023.', NULL, 1, '2026-07-08 20:15', '2026-07-08 17:30'),
(4, 'concierto', 'Zoé', 'Sonidos de Karmática', '2024-05-18', 3, 'rechazada', 'Ya existe en el catálogo', 'Ese concierto ya está registrado; búscalo en el catálogo de Zoé.', NULL, 1, '2026-08-20 10:00', '2026-08-19 11:00');
INSERT INTO solicitudes (usuario_id, tipo, nombre, ciudad, capacidad, tipo_recinto, estado, motivo_rechazo, detalle_rechazo, creado_id, revisado_por, revisado_en, creado_en) VALUES
(2, 'recinto', 'Café Iguana', 'Monterrey, N.L.', 600, 'Bar', 'aprobada', NULL, NULL, 6, 1, '2025-11-11 09:40', '2025-11-10 15:00'),
(4, 'recinto', 'Arena Mty', 'Monterrey, N.L.', 17000, 'Arena', 'rechazada', 'Ya existe en el catálogo', 'Es la Arena Monterrey, ya está registrada.', NULL, 1, '2026-09-03 10:20', '2026-09-02 13:00');

INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES
('Juan Pérez', 'juan@correo.com', 'Falta un concierto', '¿Pueden agregar el concierto de Muse de 2023?');
