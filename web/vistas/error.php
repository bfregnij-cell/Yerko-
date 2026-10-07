<?php use App\Core\Vista; ?>
<section class="tarjeta">
  <h1>Ups</h1>
  <p class="alerta alerta-error"><?= Vista::e($mensaje) ?></p>
  <p><a href="index.php">← Volver al inicio</a></p>
</section>
