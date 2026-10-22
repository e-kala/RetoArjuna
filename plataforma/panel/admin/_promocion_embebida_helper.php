<?php
function promocion_embebida_cargar(mysqli $conn, string $tipo, int $itemId): array
{
    $vacio = ['id'=>0,'nombre'=>'','modalidad'=>'promocion','tipo_descuento'=>'porcentaje','valor'=>'','fecha_inicio'=>'','fecha_fin'=>'','combinable'=>0,'activo'=>0];
    if ($itemId <= 0) return $vacio;
    $col = ['curso'=>'curso_id','evento'=>'evento_id','producto'=>'producto_id'][$tipo];
    $stmt=$conn->prepare("SELECT * FROM promociones WHERE {$col}=? ORDER BY activo DESC, updated_at DESC LIMIT 1");
    $stmt->bind_param('i',$itemId);$stmt->execute();$fila=$stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$fila) return $vacio;
    if (str_starts_with((string) $fila['fecha_inicio'], '2000-01-01')) $fila['fecha_inicio'] = '';
    if (str_starts_with((string) $fila['fecha_fin'], '2099-12-31')) $fila['fecha_fin'] = '';
    return $fila;
}

function promocion_embebida_validar(array $post, string $tipo): string
{
    if (!isset($post['promo_activa'])) return '';
    $nombre = trim((string) ($post['promo_nombre'] ?? ''));
    $valor = (float) ($post['promo_valor'] ?? 0);
    $precioRegular = (float) ($post['promo_precio_regular'] ?? 0);
    $inicio = trim((string) ($post['promo_fecha_inicio'] ?? ''));
    $fin = trim((string) ($post['promo_fecha_fin'] ?? ''));
    if ($nombre === '' || $precioRegular <= 0 || $valor < 0) return 'La oferta pública necesita nombre, precio regular y precio actual válidos.';
    if ($valor >= $precioRegular) return 'El precio actual de la oferta debe ser menor que el precio regular tachado.';
    if ($inicio !== '' && $fin !== '' && $fin <= $inicio) return 'El fin de la oferta debe ser posterior al inicio.';
    if (($post['promo_modalidad'] ?? 'promocion') === 'preventa' && $tipo !== 'curso') return 'La modalidad preventa solo está disponible para cursos.';
    return '';
}

function promocion_embebida_guardar(mysqli $conn, string $tipo, int $itemId, array $post, int $promoId = 0): void
{
    $activa=isset($post['promo_activa'])?1:0;
    if (!$activa && !$promoId) return;
    $col = ['curso'=>'curso_id','evento'=>'evento_id','producto'=>'producto_id'][$tipo];
    if (!$activa) { $stmt=$conn->prepare('UPDATE promociones SET activo=0 WHERE id=?');$stmt->bind_param('i',$promoId);$stmt->execute();$stmt->close();return; }
    $nombre=trim((string)($post['promo_nombre']??''));
    $modalidad=$tipo === 'curso' && ($post['promo_modalidad']??'') === 'preventa' ? 'preventa' : 'promocion';
    $tipoDesc=in_array($post['promo_tipo_descuento']??'', ['porcentaje','precio_fijo'],true)?$post['promo_tipo_descuento']:'porcentaje';
    $valor=max(0,(float)($post['promo_valor']??0));
    $inicio=str_replace('T',' ',trim((string)($post['promo_fecha_inicio']??''))) ?: '2000-01-01 00:00:00';
    $fin=str_replace('T',' ',trim((string)($post['promo_fecha_fin']??''))) ?: '2099-12-31 23:59:59';
    $combinable=isset($post['promo_combinable'])?1:0;
    if ($promoId) {
        $stmt=$conn->prepare("UPDATE promociones SET {$col}=?, nombre=?, modalidad=?, tipo_descuento=?, valor=?, fecha_inicio=?, fecha_fin=?, combinable=?, activo=1 WHERE id=?");
        $stmt->bind_param('isssdssii',$itemId,$nombre,$modalidad,$tipoDesc,$valor,$inicio,$fin,$combinable,$promoId);
    } else {
        $stmt=$conn->prepare("INSERT INTO promociones ({$col},nombre,modalidad,tipo_descuento,valor,fecha_inicio,fecha_fin,combinable,activo) VALUES(?,?,?,?,?,?,?,?,1)");
        $stmt->bind_param('isssdssi',$itemId,$nombre,$modalidad,$tipoDesc,$valor,$inicio,$fin,$combinable);
    }
    $stmt->execute();$stmt->close();
}
