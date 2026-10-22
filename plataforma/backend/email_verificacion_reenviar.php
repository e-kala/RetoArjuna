<?php
require_once __DIR__.'/auth.php';require_once __DIR__.'/mailer.php';require_login();requerir_csrf_form();
$u=current_user();if(empty($u['email_verificado']))enviar_email_verificacion((int)$u['id'],(string)$u['email']);
$destino=volver_validado((string)($_POST['volver']??''));
header('Location: '.($destino!==''?$destino:BASE_URL.'/panel/dashboard.php'));exit;
