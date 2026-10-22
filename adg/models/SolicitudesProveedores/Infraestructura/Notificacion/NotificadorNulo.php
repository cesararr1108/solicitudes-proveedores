<?php
namespace SolicitudesProveedores\Infraestructura\Notificacion;

use SolicitudesProveedores\Dominio\Puerto\Notificador;

/** No envia nada. Reemplace por una implementacion de correo cuando se requiera. */
class NotificadorNulo implements Notificador
{
    public function notificar($evento, array $datos)
    {
    }
}
