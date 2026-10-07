<?php
declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Modelos\Captura;
use App\Modelos\RepositorioVehiculos;
use App\Servicios\ClienteHttp;
use App\Servicios\GeneradorPublicacion;
use App\Servicios\NormalizadorSpecs;
use App\Servicios\ServicioExtraccion;
use App\Servicios\SitioBloqueadoException;
use App\Servicios\Url;
use Throwable;

/**
 * Flujo principal:
 *   1. "extraer" (link) o "recibir" (botón del navegador) guardan el trabajo y redirigen.
 *   2. "procesando" muestra la espera y llama a "ejecutar", que hace la extracción.
 *   3. "resultado" muestra el texto y las fotos; "foto" y "zip" las entregan.
 */
final class ExtraccionController extends Controlador
{
    private const MAX_DATOS = 12 * 1024 * 1024;

    public function inicio(): void
    {
        $this->vista->mostrar('inicio', [
            'titulo' => 'Extractor de Autos',
            'csrf' => $this->auth->tokenCsrf(),
            'error' => $_GET['error'] ?? null,
            'linkPrevio' => $_GET['link'] ?? '',
        ]);
    }

    public function extraerPorLink(): void
    {
        if (!$this->esPost() || !$this->csrfValido()) {
            $this->redirigir('index.php');
            return;
        }
        $url = Url::normalizarEntrada((string) ($_POST['url'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->redirigir('index.php?error=' . urlencode('Ese link no parece válido.'));
            return;
        }
        $id = $this->repositorio()->guardarPendiente(['tipo' => 'link', 'url' => $url]);
        $this->redirigir('index.php?r=procesando&id=' . $id);
    }

    /** Recibe los datos que envía el botón del navegador (o que se pegan a mano). */
    public function recibirDesdeMarcador(): void
    {
        if (!$this->esPost()) {
            $this->redirigir('index.php');
            return;
        }
        $porToken = $this->auth->tokenMarcadorValido((string) ($_POST['token'] ?? ''));
        $porSesion = $this->auth->autenticado() && $this->csrfValido();
        if (!$porToken && !$porSesion) {
            http_response_code(403);
            $this->vista->mostrar('error', [
                'mensaje' => 'El botón no es válido (¿cambiaste la contraseña?). Vuelve a instalarlo desde "Instalar botón".',
            ]);
            return;
        }
        if ($porToken) {
            $this->auth->marcarAutenticado();
        }

        $json = (string) ($_POST['datos'] ?? '');
        $datos = strlen($json) <= self::MAX_DATOS ? json_decode($json, true, 512) : null;
        if (!is_array($datos) || !preg_match('#^https?://#i', (string) ($datos['url'] ?? ''))) {
            $this->redirigir('index.php?error=' . urlencode('Los datos recibidos no son válidos.'));
            return;
        }
        $id = $this->repositorio()->guardarPendiente(['tipo' => 'marcador', 'datos' => $datos]);
        $this->redirigir('index.php?r=procesando&id=' . $id);
    }

    public function procesando(): void
    {
        $this->vista->mostrar('procesando', [
            'titulo' => 'Procesando…',
            'id' => (string) ($_GET['id'] ?? ''),
            'csrf' => $this->auth->tokenCsrf(),
        ]);
    }

    /** Hace la extracción. Responde JSON a la página "procesando". */
    public function ejecutar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->esPost() || !$this->csrfValido()) {
            http_response_code(403);
            echo json_encode(['error' => 'Solicitud no válida. Recarga la página.']);
            return;
        }
        session_write_close(); // no bloquear otras pestañas mientras se descargan las fotos

        $repositorio = $this->repositorio();
        $trabajo = $repositorio->tomarPendiente((string) ($_GET['id'] ?? ''));
        if ($trabajo === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Este trabajo ya se procesó o expiró. Vuelve a intentarlo.']);
            return;
        }

        try {
            $servicio = $this->servicio($repositorio);
            $vehiculo = $trabajo['tipo'] === 'link'
                ? $servicio->desdeLink((string) $trabajo['url'])
                : $servicio->procesar(Captura::desdeMarcador((array) $trabajo['datos']));
            $repositorio->limpiarAntiguos((int) $this->config['dias_retencion']);
            echo json_encode(['ok' => true, 'redirigir' => 'index.php?r=resultado&id=' . $vehiculo->id]);
        } catch (SitioBloqueadoException $e) {
            echo json_encode(['error' => $e->getMessage(), 'bloqueado' => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('[ExtractorAutos] ' . $e);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function resultado(): void
    {
        $vehiculo = $this->repositorio()->buscar((string) ($_GET['id'] ?? ''));
        if (!$vehiculo) {
            http_response_code(404);
            $this->vista->mostrar('error', ['mensaje' => 'No se encontró ese resultado (se borran después de unos días).']);
            return;
        }
        $this->vista->mostrar('resultado', ['titulo' => $vehiculo->titulo(), 'vehiculo' => $vehiculo]);
    }

    public function foto(): void
    {
        $repositorio = $this->repositorio();
        $vehiculo = $repositorio->buscar((string) ($_GET['id'] ?? ''));
        $ruta = $vehiculo ? $repositorio->rutaFoto($vehiculo, (string) ($_GET['n'] ?? '')) : null;
        if (!$ruta) {
            http_response_code(404);
            return;
        }
        $tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        header('Content-Type: ' . $tipos[pathinfo($ruta, PATHINFO_EXTENSION)]);
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=86400');
        if (isset($_GET['descargar'])) {
            header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
        }
        readfile($ruta);
    }

    public function zip(): void
    {
        $repositorio = $this->repositorio();
        $vehiculo = $repositorio->buscar((string) ($_GET['id'] ?? ''));
        if (!$vehiculo) {
            http_response_code(404);
            return;
        }
        $ruta = $repositorio->crearZip($vehiculo);
        $nombre = preg_replace('/[^A-Za-z0-9 _-]/', '', $vehiculo->titulo()) ?: 'auto';
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: attachment; filename="' . $nombre . ' - fotos.zip"');
        readfile($ruta);
    }

    // ------------------------------------------------------------------ dependencias

    private function repositorio(): RepositorioVehiculos
    {
        return new RepositorioVehiculos($this->config['almacen']);
    }

    private function servicio(RepositorioVehiculos $repositorio): ServicioExtraccion
    {
        return new ServicioExtraccion(
            new ClienteHttp((bool) $this->config['permitir_red_local']),
            $repositorio,
            new NormalizadorSpecs(),
            new GeneradorPublicacion(PlantillaController::leer($this->config['almacen'])),
            (int) $this->config['max_fotos'],
        );
    }
}
