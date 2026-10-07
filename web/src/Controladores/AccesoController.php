<?php
declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;

final class AccesoController extends Controlador
{
    public function entrar(): void
    {
        if (!$this->auth->requiereClave() || $this->auth->autenticado()) {
            $this->redirigir('index.php');
            return;
        }
        $error = null;
        if ($this->esPost()) {
            usleep(500000); // frena intentos repetidos
            if ($this->auth->entrar((string) ($_POST['clave'] ?? ''))) {
                $this->redirigir('index.php');
                return;
            }
            $error = 'Contraseña incorrecta.';
        }
        $this->vista->mostrar('acceso', ['titulo' => 'Entrar', 'error' => $error]);
    }

    public function salir(): void
    {
        $this->auth->salir();
        $this->redirigir('index.php');
    }
}
