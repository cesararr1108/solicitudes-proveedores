<?php
namespace SolicitudesProveedores\Dominio\Plantilla;

use SolicitudesProveedores\Dominio\Excepcion\ValidacionException;

class RegistroPlantillas
{
    private $plantillas = array();

    public function __construct(array $plantillas)
    {
        foreach ($plantillas as $p) {
            $this->registrar($p);
        }
    }

    public static function predeterminado()
    {
        return new self(array(new PlantillaLibre(), new PlantillaDescuentos(), new PlantillaBonificados()));
    }

    public function registrar(PlantillaDetalle $p)
    {
        $this->plantillas[$p->codigo()] = $p;
    }

    /** @return PlantillaDetalle */
    public function obtener($codigo)
    {
        if (!isset($this->plantillas[$codigo])) {
            throw new ValidacionException('Plantilla no soportada: ' . $codigo);
        }
        return $this->plantillas[$codigo];
    }

    public function codigos()
    {
        return array_keys($this->plantillas);
    }

    public function catalogo()
    {
        $lista = array();
        foreach ($this->plantillas as $p) {
            $lista[] = array(
                'codigo'         => $p->codigo(),
                'nombre'         => $p->nombre(),
                'requiere_excel' => $p->requiereExcel(),
                'columnas'       => $p->columnas(),
            );
        }
        return $lista;
    }
}
