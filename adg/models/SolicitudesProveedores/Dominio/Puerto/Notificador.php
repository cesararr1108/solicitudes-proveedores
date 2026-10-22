<?php
namespace SolicitudesProveedores\Dominio\Puerto;

/**
 * Puerto de notificaciones (correo, push, etc.). Hoy se usa una implementacion nula;
 * para enviar correos cree Infraestructura\Notificacion\NotificadorCorreo y cambielo en el Contenedor.
 *
 * Eventos: SOLICITUD_CREADA, SOLICITUD_ASIGNADA, SOLICITUD_ESTADO, SOLICITUD_COMENTARIO
 */
interface Notificador
{
    public function notificar($evento, array $datos);
}
