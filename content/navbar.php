<nav class="navbar navbar-expand-lg fixed-top" style="background-color: rgb(247 147 30)" id="navbar">
  <div class="container-fluid">
    <!--logo-->
    <a class="navbar-brand" href="?action=inicio">
      <img src="./img/Logo+Text4.png" alt="Logo" width="150" class="d-inline-block align-text-top">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
      aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav ms-auto fs-4">

        <li class="nav-item">
          <a class="nav-link" href="?action=inicio">
            <i class="bi bi-house-fill" style="font-size: 2rem; "></i></a>
        </li>

        <!--¿Qué es el Reto Arjuna?-->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle  " href="#" role="button" data-bs-toggle="dropdown"
            aria-expanded="false">
            ¿Qué es el Reto Arjuna?
          </a>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item" href="#temario" onclick="location.href='#temario'">¿Qué es el reto Arjuna?</a>
            </li>
            <li>
              <a class="dropdown-item" href="#inicio" onclick="location.href='#inicio'">¿Cuándo son los inicios?</a>
            </li>
            <li>
              <a class="dropdown-item" href="#temario" onclick="location.href='#temario'">¿Cómo puedo participar?</a>
            </li>
            <li><a class="dropdown-item" href="#temario" onclick="location.href='#temario'">Temario General</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="#temario" onclick="location.href='#temario'">Quiénes somos</a>
        </li>


        <li class="nav-item">
          <a class="nav-link" href="#temario" onclick="location.href='#temario'">Contacto</a>
        </li>

   

        <div class="collapse navbar-collapse" id="navbarNavDarkDropdown">
          <ul class="navbar-nav">
            <li class="nav-item dropstart">
              <button class="btn btn-transparent dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle" style="font-size: 2rem;"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-dark">
                <li><a class="dropdown-item" href="?action=ingreso">Ingresar</a></li>
                <li><a class="dropdown-item" href="?action=registro">Registrarse</a></li>
              </ul>
            </li>
          </ul>
        </div>

      </ul>
    </div>
  </div>
</nav>