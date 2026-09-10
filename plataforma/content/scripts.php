<!-- Bootstrap Dependenci-->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
    crossorigin="anonymous">
</script>

<?php if (current_user()): ?>
<script src="content/session_watch.js" data-check-url="backend/session_check.php"></script>
<?php endif; ?>

<!--Función ocultar navbar scroll IN-->
<script>
    let lastScroll = 0;
    const navbar = document.getElementById('navbar');
    // navbar siempre debería existir (navbar.php le pone id="navbar" a su
    // <nav>) — el guard es solo para no tumbar el resto de los scripts de
    // esta página si algún día vuelve a desincronizarse, como pasó antes:
    // el <nav> se quedó sin id tras un rediseño y esto tronaba en TODA
    // página del sitio antes de siquiera llegar a addEventListener.
    if (navbar) {
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
    }
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
