<?php use App\Core\Vista; ?>
<h1>Plantilla del texto</h1>
<?php if ($mensaje): ?><div class="alerta alerta-ok"><?= Vista::e($mensaje) ?></div><?php endif; ?>

<div class="columnas">
  <section class="tarjeta">
    <form method="post" action="index.php?r=plantilla">
      <input type="hidden" name="csrf" value="<?= Vista::e($csrf) ?>">
      <textarea name="plantilla" rows="22"><?= Vista::e($plantilla) ?></textarea>
      <div class="fila">
        <button class="boton" type="submit">Guardar</button>
        <button class="boton secundario" type="submit" name="restaurar" value="1"
                onclick="return confirm('¿Volver a la plantilla original?')">Restaurar original</button>
      </div>
    </form>
  </section>
  <section class="tarjeta">
    <h2>Cómo funciona</h2>
    <ul>
      <li>Cada <code>{campo}</code> se reemplaza por el dato del auto.</li>
      <li>Si un dato no existe, <strong>esa línea completa se omite</strong>.</li>
      <li>Puedes cambiar emojis, el orden o agregar tu teléfono y precio.</li>
    </ul>
    <p>Campos disponibles:</p>
    <p class="campos"><?php foreach ($campos as $c): ?><code>{<?= Vista::e($c) ?>}</code> <?php endforeach; ?></p>
    <h2>Vista previa (auto de ejemplo)</h2>
    <pre class="previa"><?= Vista::e($ejemplo) ?></pre>
  </section>
</div>
