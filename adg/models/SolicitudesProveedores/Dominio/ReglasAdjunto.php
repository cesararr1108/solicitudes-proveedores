<?php
namespace SolicitudesProveedores\Dominio;

use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;

/**
 * Valida los metadatos de un archivo ya subido al FTP (ServiceUploadFile.php)
 * antes de registrarlo. Evita registrar URLs externas o extensiones no permitidas.
 */
class ReglasAdjunto
{
    private $config;

    public function __construct(array $configAdjuntos)
    {
        $this->config = $configAdjuntos;
    }

    /**
     * @param array $adjuntos lista de {nombre, ruta, url, tamano_kb}
     * @param array $extPermitidas
     * @return array adjuntos normalizados
     */
    public function normalizar(array $adjuntos, array $extPermitidas)
    {
        if (count($adjuntos) > $this->config['max_por_envio']) {
            throw new ValidacionException('Maximo ' . $this->config['max_por_envio'] . ' archivos por envio');
        }
        $prefijoRuta = rtrim($this->config['ruta_ftp'], '/') . '/';
        $prefijoUrl  = rtrim($this->config['url_base'], '/') . $prefijoRuta;
        $maxKb       = (int) $this->config['max_mb'] * 1024;
        $salida      = array();

        foreach ($adjuntos as $i => $a) {
            $nombre = isset($a['nombre']) ? trim($a['nombre']) : '';
            $ruta   = isset($a['ruta']) ? trim($a['ruta']) : '';
            $url    = isset($a['url']) ? trim($a['url']) : '';
            $kb     = isset($a['tamano_kb']) ? (int) $a['tamano_kb'] : 0;
            $ext    = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            $campo  = 'adjuntos.' . $i;

            if ($nombre === '' || $ruta === '' || $url === '') {
                throw new ValidacionException('Adjunto incompleto', array($campo => 'Faltan datos del archivo'));
            }
            if (strpos($ruta, $prefijoRuta) !== 0 || strpos($url, $prefijoUrl) !== 0 || strpos($ruta, '..') !== false) {
                throw new ValidacionException('Ubicacion de archivo no valida', array($campo => $nombre));
            }
            if (!in_array($ext, $extPermitidas, true)) {
                throw new ValidacionException(
                    'Tipo de archivo no permitido: .' . $ext,
                    array($campo => 'Permitidos: ' . implode(', ', $extPermitidas))
                );
            }
            if ($kb > $maxKb) {
                throw new ValidacionException('El archivo ' . $nombre . ' supera ' . $this->config['max_mb'] . ' MB');
            }
            $salida[] = array(
                'nombre'    => substr($nombre, 0, 255),
                'ruta'      => $ruta,
                'url'       => $url,
                'extension' => $ext,
                'tamano_kb' => $kb,
            );
        }
        return $salida;
    }
}
