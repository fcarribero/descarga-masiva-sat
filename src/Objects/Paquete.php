<?php

namespace Webneex\DescargaMasivaSAT\Objects;

use DOMDocument;

class Paquete {

    public $IdPaquete;
    public $Contenido;
    public $CodEstatus;

    public function __construct($id_paquete, $soap = null) {
        if ($soap) {
            $dom = new DOMDocument;
            $dom->loadXML($soap);

            $this->IdPaquete = $id_paquete;
            $this->CodEstatus = $dom->getElementsByTagName("respuesta")->item(0)->getAttribute('CodEstatus');
            $this->Mensaje = $dom->getElementsByTagName("respuesta")->item(0)->getAttribute('Mensaje');
            $this->Contenido = $dom->getElementsByTagName("Paquete")->item(0)->nodeValue;
        }
    }
}