<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reto Arjuna</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
  integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous" />
<script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
  integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
  crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
<script src="https://accounts.google.com/gsi/client" async defer></script>
<meta name="google-signin-client_id" content="121887533073-18o653lrha5rc3tdm6t533067vjv6bgi.apps.googleusercontent.com">
<script src="content/notify.min.js"></script>
<style>
  @font-face {
    font-family: 'ArchitectsDaughter';
    src: url('fonts/ArchitectsDaughter.ttf') format('ttf');
    font-weight: normal;
    font-style: normal;
  }



  .f1 {
    font-family: 'ArchitectsDaughter';
  }

  .f2 {
    font-family: 'f2', sans-serif;
  }

  #container {
    justify-content: center;
    background: url(img/kuruksetra.jpg) no-repeat center center;

  }

  #countdown {
    font-size: 2em;
    font-weight: bold;
    text-shadow: 0 0 20px gold;
    animation: glow 1.5s infinite alternate;
  }

  @keyframes glow {
    0% {
      text-shadow: 0 0 10px gold;
    }

    100% {
      text-shadow: 0 0 30px gold;
    }
  }

  .contorno {

    text-shadow:
      -1px -1px 0 #000,
      1px -1px 0 #000,
      -1px 1px 0 #000,
      1px 1px 0 #000;
    /* Color del contorno */
  }

  #navbar {
    transition: transform 0.3s ease-in-out;
  }

  .navbar-hidden {
    transform: translateY(-100%);
  }
</style>