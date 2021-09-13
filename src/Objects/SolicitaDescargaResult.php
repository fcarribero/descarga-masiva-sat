<?php

namespace Webneex\DescargaMasivaSAT\Objects;

use DOMDocument;
use Exception;

class SolicitaDescargaResult {

    public $IdSolicitud;
    public $CodEstatus;
    public $Mensaje;

    public function __construct($soap = null) {
        if ($soap) {
            $dom = new DOMDocument;
            $dom->loadXML($soap);

            if (($fault = $dom->getElementsByTagName("Fault")->item(0))) {
                throw new Exception($dom->getElementsByTagName('faultstring')->item(0)->nodeValue);
            }

            $element = $dom->getElementsByTagName("SolicitaDescargaResult")->item(0);

            $this->IdSolicitud = $element->getAttribute('IdSolicitud');
            $this->CodEstatus = $element->getAttribute('CodEstatus');
            $this->Mensaje = $element->getAttribute('Mensaje');
        }
    }
}