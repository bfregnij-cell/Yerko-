<?php
use App\Core\Vista;

/** @var App\Modelos\Vehiculo $vehiculo */
$id = $vehiculo->id;
?>
<h1><?= Vista::e($vehiculo->titulo()) ?></h1>
<p class="nota">Fuente: <?= Vista::e($vehiculo->datos['fuente'] ?? '') ?> ·
  <a href="<?= Vista::e($vehiculo->url) ?>" target="_blank" rel="noopener noreferrer">ver página original</a></p>

<div class="columnas">
  <section class="tarjeta">
    <h2>Texto para publicar</h2>
    <textarea id="texto" rows="18"><?= Vista::e($vehiculo->texto) ?></textarea>
    <div class="fila">
      <button class="boton" type="button" data-copiar="#texto">📋 Copiar texto</button>
      <a class="boton secundario" href="index.php">Nuevo auto</a>
    </div>
    <p class="nota">Puedes corregir el texto aquí antes de copiarlo.</p>
  </section>

  <section class="tarjeta">
    <h2>Fotos (<?= count($vehiculo->fotos) ?>)</h2>
    <?php if ($vehiculo->fotos): ?>
      <a class="boton" href="index.php?r=zip&amp;id=<?= Vista::e($id) ?>">⬇️ Descargar todas (ZIP)</a>
      <div class="galeria">
        <?php foreach ($vehiculo->fotos as $f): ?>
          <a href="index.php?r=foto&amp;id=<?= Vista::e($id) ?>&amp;n=<?= Vista::e($f) ?>&amp;descargar=1" title="Descargar">
            <img loading="lazy" src="index.php?r=foto&amp;id=<?= Vista::e($id) ?>&amp;n=<?= Vista::e($f) ?>" alt="<?= Vista::e($f) ?>">
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="alerta">No se pudieron guardar fotos en el servidor.</p>
    <?php endif; ?>

    <?php if ($vehiculo->fotosRemotas): ?>
      <details>
        <summary><?= count($vehiculo->fotosRemotas) ?> fotos no se pudieron descargar desde el servidor</summary>
        <p class="nota">Ábrelas y guárdalas con clic derecho → “Guardar imagen como…”.</p>
        <div class="galeria">
          <?php foreach ($vehiculo->fotosRemotas as $u): ?>
            <a href="<?= Vista::e($u) ?>" target="_blank" rel="noopener noreferrer">
              <img loading="lazy" referrerpolicy="no-referrer" src="<?= Vista::e($u) ?>" alt="">
            </a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  </section>
</div>

<details class="tarjeta">
  <summary>Datos detectados (para revisar)</summary>
  <table class="tabla">
    <?php foreach ($vehiculo->datos as $campo => $valor): ?>
      <tr><th><?= Vista::e($campo) ?></th><td><?= Vista::e($valor) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($vehiculo->pares): ?>
    <h3>Todo lo que se leyó en la página</h3>
    <table class="tabla">
      <?php foreach ($vehiculo->pares as [$etiqueta, $valor]): ?>
        <tr><th><?= Vista::e($etiqueta) ?></th><td><?= Vista::e($valor) ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</details>
