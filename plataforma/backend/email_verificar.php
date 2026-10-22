<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/mailer.php';
$token=(string)($_GET['token']??'');$ok=false;
if(preg_match('/^[a-f0-9]{64}$/',$token)){
 $hash=hash('sha256',$token);$stmt=$conn->prepare('SELECT usuario_id FROM email_verificacion_tokens WHERE token_hash=? AND usado_at IS NULL AND expira_en>=NOW() LIMIT 1');$stmt->bind_param('s',$hash);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
 if($row){$uid=(int)$row['usuario_id'];$conn->begin_transaction();try{$stmt=$conn->prepare('UPDATE usuarios_perfil SET email_verificado_at=COALESCE(email_verificado_at,NOW()) WHERE id=?');$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();$stmt=$conn->prepare('UPDATE email_verificacion_tokens SET usado_at=NOW() WHERE usuario_id=?');$stmt->bind_param('i',$uid);$stmt->execute();$stmt->close();$conn->commit();$ok=true;if(is_logged_in()&&(int)$_SESSION['usuario_perfil_id']===$uid)$_SESSION['email_verificado']=true;$stmt=$conn->prepare('SELECT email_cache FROM usuarios_perfil WHERE id=?');$stmt->bind_param('i',$uid);$stmt->execute();$email=$stmt->get_result()->fetch_assoc()['email_cache']??'';$stmt->close();if($email!=='')enviar_email_bienvenida($uid,$email);}catch(Throwable $e){$conn->rollback();}}
}
header('Location: '.BASE_URL.'/index.php?action=email_verificado&estado='.($ok?'ok':'invalido'));exit;
