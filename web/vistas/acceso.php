<?php use App\Core\Vista; ?>
<section class="tarjeta angosta">
  <h1>Entrar</h1>
  <?php if ($error): ?><div class="alerta alerta-error"><?= Vista::e($error) ?></div><?php endif; ?>
  <form method="post" action="index.php?r=acceso">
    <input type="password" name="clave" placeholder="Contraseña" required autofocus>
    <button class="boton" type="submit">Entrar</button>
  </form>
</section>
