<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foro de Discusión</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .category-icon {
            font-size: 2rem;
            margin-right: 15px;
            color: #6c757d;
        }
        .thread-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .badge-custom {
            font-size: 0.75rem;
        }
        .forum-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .breadcrumb {
            background-color: transparent;
            padding: 0;
        }
        .thread-stats {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .post-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .post-content {
            border-left: 3px solid #dee2e6;
            padding-left: 15px;
        }
        .post-footer {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">Foro Comunitario</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-home me-1"></i> Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-list me-1"></i> Categorías</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-users me-1"></i> Miembros</a>
                    </li>
                </ul>
                <form class="d-flex me-2">
                    <input class="form-control me-2" type="search" placeholder="Buscar en el foro...">
                    <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                </form>
                <div class="d-flex align-items-center">
                    <img src="https://via.placeholder.com/40" alt="Avatar" class="rounded-circle me-2">
                    <span class="text-white">Usuario</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <!-- Migas de pan -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="#">Foro</a></li>
                <li class="breadcrumb-item active" aria-current="page">Discusión General</li>
            </ol>
        </nav>

        <!-- Encabezado del foro -->
        <div class="forum-header p-3 rounded mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-0"><i class="fas fa-comments me-2"></i> Discusión General</h1>
                    <p class="mb-0 text-muted">Habla sobre cualquier tema en esta categoría general</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newThreadModal">
                    <i class="fas fa-plus me-1"></i> Nuevo Tema
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex">
                        <div class="dropdown me-2">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-filter me-1"></i> Filtros
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Más recientes</a></li>
                                <li><a class="dropdown-item" href="#">Más antiguos</a></li>
                                <li><a class="dropdown-item" href="#">Más populares</a></li>
                                <li><a class="dropdown-item" href="#">Sin respuesta</a></li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-sort me-1"></i> Ordenar por
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Fecha de publicación</a></li>
                                <li><a class="dropdown-item" href="#">Última respuesta</a></li>
                                <li><a class="dropdown-item" href="#">Número de respuestas</a></li>
                                <li><a class="dropdown-item" href="#">Número de vistas</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-muted small">
                        Mostrando 1-10 de 125 temas
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de hilos -->
        <div class="list-group mb-4">
            <!-- Hilo destacado -->
            <div class="list-group-item list-group-item-warning">
                <div class="d-flex w-100 justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-thumbtack text-danger me-2"></i>
                        <h5 class="mb-1">Normas del foro - Por favor leer antes de publicar</h5>
                    </div>
                    <small class="text-muted">3 días atrás</small>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <div>
                        <span class="badge bg-primary me-1">Importante</span>
                        <span class="badge bg-info">Normas</span>
                    </div>
                    <div class="thread-stats">
                        <span class="me-2"><i class="far fa-eye me-1"></i> 245</span>
                        <span><i class="far fa-comment me-1"></i> 12</span>
                    </div>
                </div>
            </div>

            <!-- Hilo normal -->
            <a href="#" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <div class="d-flex align-items-center">
                        <img src="https://via.placeholder.com/40" alt="Avatar" class="thread-avatar me-2">
                        <div>
                            <h5 class="mb-1">¿Cómo configurar mi primer proyecto en Bootstrap 5?</h5>
                            <p class="mb-1 small text-muted">Publicado por <strong>JuanPerez</strong> en <strong>Desarrollo Web</strong></p>
                        </div>
                    </div>
                    <small class="text-muted">2 horas atrás</small>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <div>
                        <span class="badge bg-success me-1">Nuevo</span>
                        <span class="badge bg-secondary">Bootstrap</span>
                    </div>
                    <div class="thread-stats">
                        <span class="me-2"><i class="far fa-eye me-1"></i> 45</span>
                        <span><i class="far fa-comment me-1"></i> 3</span>
                    </div>
                </div>
            </a>

            <!-- Más hilos... -->
            <a href="#" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <div class="d-flex align-items-center">
                        <img src="https://via.placeholder.com/40" alt="Avatar" class="thread-avatar me-2">
                        <div>
                            <h5 class="mb-1">Problema con la versión móvil de mi sitio</h5>
                            <p class="mb-1 small text-muted">Publicado por <strong>MariaGomez</strong> en <strong>Diseño Responsivo</strong></p>
                        </div>
                    </div>
                    <small class="text-muted">5 horas atrás</small>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <div>
                        <span class="badge bg-success me-1">Nuevo</span>
                        <span class="badge bg-secondary">CSS</span>
                    </div>
                    <div class="thread-stats">
                        <span class="me-2"><i class="far fa-eye me-1"></i> 78</span>
                        <span><i class="far fa-comment me-1"></i> 5</span>
                    </div>
                </div>
            </a>

            <a href="#" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <div class="d-flex align-items-center">
                        <img src="https://via.placeholder.com/40" alt="Avatar" class="thread-avatar me-2">
                        <div>
                            <h5 class="mb-1">Recomendaciones para hosting económico</h5>
                            <p class="mb-1 small text-muted">Publicado por <strong>CarlosLopez</strong> en <strong>Hosting y Dominios</strong></p>
                        </div>
                    </div>
                    <small class="text-muted">1 día atrás</small>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <div>
                        <span class="badge bg-warning text-dark me-1">Popular</span>
                        <span class="badge bg-secondary">Hosting</span>
                    </div>
                    <div class="thread-stats">
                        <span class="me-2"><i class="far fa-eye me-1"></i> 132</span>
                        <span><i class="far fa-comment me-1"></i> 18</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Paginación -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item disabled">
                    <a class="page-link" href="#" tabindex="-1">Anterior</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                    <a class="page-link" href="#">Siguiente</a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Modal para nuevo hilo -->
    <div class="modal fade" id="newThreadModal" tabindex="-1" aria-labelledby="newThreadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newThreadModalLabel">Crear nuevo tema</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label for="threadTitle" class="form-label">Título del tema</label>
                            <input type="text" class="form-control" id="threadTitle" placeholder="Escribe un título descriptivo">
                        </div>
                        <div class="mb-3">
                            <label for="threadCategory" class="form-label">Categoría</label>
                            <select class="form-select" id="threadCategory">
                                <option selected>Selecciona una categoría</option>
                                <option>Discusión General</option>
                                <option>Desarrollo Web</option>
                                <option>Diseño Responsivo</option>
                                <option>Hosting y Dominios</option>
                                <option>Problemas Técnicos</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="threadContent" class="form-label">Contenido</label>
                            <textarea class="form-control" id="threadContent" rows="8" placeholder="Escribe tu mensaje aquí..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="threadTags" class="form-label">Etiquetas</label>
                            <input type="text" class="form-control" id="threadTags" placeholder="bootstrap, diseño, css (separadas por comas)">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Publicar tema</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pie de página -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="mb-3">Foro Comunitario</h5>
                    <p class="text-muted small">Un lugar para compartir conocimientos y resolver dudas sobre desarrollo web y tecnología.</p>
                </div>
                <div class="col-md-2 mb-3">
                    <h5 class="mb-3">Enlaces</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-decoration-none text-muted small">Inicio</a></li>
                        <li><a href="#" class="text-decoration-none text-muted small">Categorías</a></li>
                        <li><a href="#" class="text-decoration-none text-muted small">Miembros</a></li>
                        <li><a href="#" class="text-decoration-none text-muted small">Normas</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h5 class="mb-3">Estadísticas</h5>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="fas fa-users me-1"></i> 1,245 miembros</li>
                        <li><i class="fas fa-comments me-1"></i> 5,678 temas</li>
                        <li><i class="fas fa-reply me-1"></i> 23,456 respuestas</li>
                    </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h5 class="mb-3">Redes Sociales</h5>
                    <div class="d-flex">
                        <a href="#" class="me-3 text-decoration-none text-muted"><i class="fab fa-facebook-f fa-lg"></i></a>
                        <a href="#" class="me-3 text-decoration-none text-muted"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="me-3 text-decoration-none text-muted"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-decoration-none text-muted"><i class="fab fa-github fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center text-muted small">
                © 2023 Foro Comunitario. Todos los derechos reservados.
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>