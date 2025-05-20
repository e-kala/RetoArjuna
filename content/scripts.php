<!-- Bootstrap Dependenci-->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
    crossorigin="anonymous"></script>

<!--Cuenta regresiva script IN-->
<script>
    //año, mes y dia
    const targetDate = new Date("2025-06-26T20:00:00").getTime();

    function updateCountdown() {
        const now = new Date().getTime();
        const timeLeft = targetDate - now;

        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor(
            (timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
        );
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

        document.getElementById(
            "countdown"
        ).innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;

        if (timeLeft < 0) {
            clearInterval(interval);
            document.getElementById("countdown").innerHTML = "¡Ya Iniciamos!";
        }
    }

    const interval = setInterval(updateCountdown, 1000);
    updateCountdown();
</script>
<!--Cuenta regresiva script END-->

<!--Función ocultar navbar scroll IN-->
<script>
    let lastScroll = 0;
    const navbar = document.getElementById('navbar');
    const navbarHeight = navbar.offsetHeight;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;

        // Si el scroll es hacia abajo y mayor que la altura del navbar
        if (currentScroll > lastScroll && currentScroll > navbarHeight) {
            navbar.classList.add('navbar-hidden');
        }
        // Si el scroll es hacia arriba
        else {
            navbar.classList.remove('navbar-hidden');
        }

        lastScroll = currentScroll;
    });
</script>
<!--Función ocultar navbar scroll END-->

<!-- Google Auth IN -->

<script>
    function onSignIn(googleUser) {
        var profile = googleUser.getBasicProfile();
        console.log('ID: ' + profile.getId());
        console.log('Name: ' + profile.getName());
        console.log('Image URL: ' + profile.getImageUrl());
        console.log('Email: ' + profile.getEmail());

        // Aquí puedes enviar el token al servidor para autenticar al usuario
        var id_token = googleUser.getAuthResponse().id_token;
        console.log(id_token);
        // Enviar id_token a tu servidor
    }
</script>
<!-- Google Auth IN -->