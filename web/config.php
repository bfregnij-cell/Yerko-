<?php
/*
 * Configuración del Extractor de Autos.
 */
return [
    // Contraseña para entrar. Déjala vacía ('') para uso libre.
    // Si el sitio queda en internet, se recomienda ponerle una.
    'clave' => '',

    // Carpeta donde se guardan los resultados (fotos y textos).
    'almacen' => getenv('EXTRACTOR_ALMACEN') ?: __DIR__ . '/almacen',

    // Días que se conservan los resultados antes de borrarse solos.
    'dias_retencion' => 7,

    // Máximo de fotos por auto.
    'max_fotos' => 60,

    // Solo para pruebas: permite descargar desde 127.0.0.1 / redes privadas.
    'permitir_red_local' => getenv('EXTRACTOR_RED_LOCAL') === '1',
];
