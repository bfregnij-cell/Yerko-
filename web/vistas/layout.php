<?php use App\Core\Vista; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= Vista::e($titulo ?? 'Extractor de Autos') ?></title>
  <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
  <header class="barra">
    <a class="marca" href="index.php">🚗 Extractor de Autos</a>
    <nav>
      <a href="index.php?r=instalar">Instalar botón</a>
      <a href="index.php?r=plantilla">Plantilla</a>
      <?php if (!empty($_SESSION['autenticado'])): ?><a href="index.php?r=salir">Salir</a><?php endif; ?>
    </nav>
  </header>
  <main class="contenedor">
    <?= $contenido ?>
  </main>
  <script src="assets/app.js"></script>
</body>
</html>
