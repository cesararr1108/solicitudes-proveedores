<?php
namespace SolicitudesProveedores\Dominio\Validacion;

/**
 * Contrato de una regla de validacion.
 * Para crear una nueva regla: implemente esta interfaz en Reglas/ y usela en el Validador.
 */
interface Regla
{
    /**
     * @param mixed  $valor
     * @param string $etiqueta nombre legible del campo
     * @param array  $datos    todos los datos (para reglas que comparan campos)
     * @return string|null mensaje de error, o null si es valido
     */
    public function validar($valor, $etiqueta, array $datos);
}
