<?php
require_once __DIR__ . '/../../backend/auth.php';
require_role('admin'); requerir_csrf_form();
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion'] ?? '')==='eliminar') {
    $id=(int)$_POST['id']; $stmt=$conn->prepare('DELETE FROM rutas_aprendizaje WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
    if (es_peticion_ajax()) { echo json_encode(['success'=>true,'redirect'=>'rutas.php']); exit; }
    header('Location: rutas.php'); exit;
}
$rutas=$conn->query("SELECT r.*, COUNT(ri.id) total_items FROM rutas_aprendizaje r LEFT JOIN ruta_aprendizaje_items ri ON ri.ruta_id=r.id GROUP BY r.id ORDER BY r.orden,r.titulo")->fetch_all(MYSQLI_ASSOC);
$pageTitle='Rutas de aprendizaje'; include __DIR__.'/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Rutas de aprendizaje</h1><a href="ruta_form.php" class="btn btn-success btn-sm">+ Nueva ruta</a></div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Ruta</th><th>Estado</th><th>Contenidos</th><th>Orden</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rutas as $r): ?><tr><td><strong><?=htmlspecialchars($r['titulo'])?></strong><div class="small text-muted"><?=htmlspecialchars($r['slug'])?></div></td><td><span class="badge <?=$r['estado']==='publicada'?'bg-success':'bg-secondary'?>"><?=htmlspecialchars($r['estado'])?></span></td><td><?=(int)$r['total_items']?></td><td><?=(int)$r['orden']?></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="ruta_form.php?id=<?=(int)$r['id']?>">Editar</a><a class="btn btn-sm btn-outline-secondary" target="_blank" href="../../index.php?action=ruta&slug=<?=urlencode($r['slug'])?>">Ver</a><form method="post" data-ajax-form data-confirm="¿Eliminar esta ruta? No se elimina ningún curso o evento."><?=csrf_field()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-sm btn-outline-danger">Eliminar</button></form></div></td></tr><?php endforeach; ?>
<?php if(!$rutas):?><tr><td colspan="5" class="text-muted text-center">Aún no hay rutas.</td></tr><?php endif;?></tbody></table></div>
<?php include __DIR__.'/_footer.php'; ?>
