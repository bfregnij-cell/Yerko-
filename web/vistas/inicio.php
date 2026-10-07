<?php use App\Core\Vista; ?>
<?php if ($error): ?>
  <div class="alerta alerta-error"><?= Vista::e($error) ?></div>
<?php endif; ?>

<section class="tarjeta destacada">
  <h2>⭐ Opción recomendada: el botón del navegador</h2>
  <p>Funciona con <strong>BE FORWARD, Copart e IAAI</strong>. Abres la página del auto como siempre y aprietas
    el botón <strong>“Extraer auto”</strong> en tu barra de favoritos.</p>
  <a class="boton" href="index.php?r=instalar">Instalar el botón (una sola vez)</a>
</section>

<section class="tarjeta">
  <h2>Pegar el link</h2>
  <p class="nota">Funciona bien con BE FORWARD. Copart e IAAI suelen bloquear al servidor: para ellos usa el botón.</p>
  <form method="post" action="index.php?r=extraer" class="fila">
    <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
    <input type="url" name="url" required placeholder="https://www.beforward.jp/…" value="<?= Vista::e($linkPrevio) ?>">
    <button class="boton" type="submit">Extraer</button>
  </form>
</section>

<details class="tarjeta">
  <summary>Pegar datos del botón (si el sitio no dejó enviarlos)</summary>
  <form method="post" action="index.php?r=recibir">
    <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
    <textarea name="datos" rows="5" required placeholder="Pega aquí el texto que copió el botón"></textarea>
    <button class="boton" type="submit">Procesar</button>
  </form>
</details>
