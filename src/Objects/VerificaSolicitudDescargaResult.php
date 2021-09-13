<?php

namespace Webneex\DescargaMasivaSAT\Objects;

use DOMDocument;

class VerificaSolicitudDescargaResult {

    public $CodEstatus;
    public $EstadoSolicitud;
    public $EstadoSolicitudMensaje;
    public $CodigoEstadoSolicitud;
    public $NumeroCFDIs;
    public $Mensaje;
    public $IdsPaquetes;

    public function __construct($soap = null) {
        if ($soap) {
            $dom = new DOMDocument;
            $dom->loadXML($soap);
            $element = $dom->getElementsByTagName("VerificaSolicitudDescargaResult")->item(0);

            $mensajes = [
                1 => 'Aceptada',
                2 => 'En Proceso',
                3 => 'Terminada',
                4 => 'Error',
                5 => 'Rechazada',
                6 => 'Vencida',
            ];

            $this->CodEstatus = $element->getAttribute('CodEstatus');
            $this->EstadoSolicitud = $element->getAttribute('EstadoSolicitud');
            $this->EstadoSolicitudMensaje = @$mensajes[$element->getAttribute('EstadoSolicitud')];
            $this->CodigoEstadoSolicitud = $element->getAttribute('CodigoEstadoSolicitud');
            $this->NumeroCFDIs = $element->getAttribute('NumeroCFDIs');
            $this->Mensaje = $element->getAttribute('Mensaje');

            foreach ($element->getElementsByTagName('IdsPaquetes') as $elm) {
                $this->IdsPaquetes[] = $elm->nodeValue;
            }
        }
    }
}