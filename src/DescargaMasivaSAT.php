<?php

namespace Webneex\DescargaMasivaSAT;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Exception;
use Webneex\DescargaMasivaSAT\Objects\Paquete;
use Webneex\DescargaMasivaSAT\Objects\SolicitaDescargaResult;
use Webneex\DescargaMasivaSAT\Objects\VerificaSolicitudDescargaResult;
use Webneex\SelloCFDI\Sello;

class DescargaMasivaSAT {

    protected $token;

    /**
     * @var Sello $sello
     */
    protected $sello;

    /**
     * SatServicioDescarga constructor.
     * @param Sello $sello
     */
    public function __construct($sello) {
        $this->SetSello($sello);
    }

    /**
     * @param Sello $sello.
     */
    public function SetSello($sello) {
        $this->sello = $sello;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function Autenticar() {

        $service_url = 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc';

        //CALCULAR EL TIMESTAMP
        $time_created = gmdate("Y-m-d\TH:i:s", time()) . '.001Z';
        $time_expires = gmdate("Y-m-d\TH:i:s", time() + 300) . '.001Z';
        //CALCULAR EL DIGEST
        $canonicalTimestamp = '<u:Timestamp xmlns:u="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" u:Id="_0"><u:Created>' . $time_created . '</u:Created><u:Expires>' . $time_expires . '</u:Expires></u:Timestamp>';
        $digest = base64_encode(sha1($canonicalTimestamp, TRUE));
        //CALCULAR LA FIRMA
        $canonicalSignedInfo = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#"><CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></CanonicalizationMethod><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod><Reference URI="#_0"><Transforms><Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></Transform></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod><DigestValue>' . $digest . '</DigestValue></Reference></SignedInfo>';

        $signature = $this->sello->sign($canonicalSignedInfo);

        //CALCULAR EL UUID
        $random = md5(uniqid(rand(), true));
        $uuid = 'uuid-' . substr($random, 0, 8) . "-" . substr($random, 8, 4) . "-" . substr($random, 12, 4) . "-" . substr($random, 16, 4) . "-" . substr($random, 20, 12) . '-1';
        //CONCATENAR EL XML FINAL PARA EL SOAP REQUEST
        $soap_request = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:u="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><s:Header><o:Security s:mustUnderstand="1" xmlns:o="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"><u:Timestamp u:Id="_0"><u:Created>' . $time_created . '</u:Created><u:Expires>' . $time_expires . '</u:Expires></u:Timestamp><o:BinarySecurityToken u:Id="' . $uuid . '" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">' . $this->sello->getPublicKey(false) . '</o:BinarySecurityToken><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/><SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/><Reference URI="#_0"><Transforms><Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/></Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/><DigestValue>' . $digest . '</DigestValue></Reference></SignedInfo><SignatureValue>' . $signature . '</SignatureValue><KeyInfo><o:SecurityTokenReference><o:Reference ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" URI="#' . $uuid . '"/></o:SecurityTokenReference></KeyInfo></Signature></o:Security></s:Header><s:Body><Autentica xmlns="http://DescargaMasivaTerceros.gob.mx"/></s:Body></s:Envelope>';
        //ENVIAR CON CURL
        $headers = array(
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://DescargaMasivaTerceros.gob.mx/IAutenticacion/Autentica"',
            'Content-Length: ' . strlen($soap_request),
            'Expect: 100-continue',
            'Connection: Keep-Alive'
        );

        $ch = curl_init($service_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);

        curl_close($ch);
        $dom = new DOMDocument;
        $dom->loadXML($result);

        if ($dom->getElementsByTagName('faultstring')->length > 0) {
            throw new Exception('SAT: ' . $dom->getElementsByTagName('faultstring')->item(0)->nodeValue);
        }

        $this->token = urldecode($dom->getElementsByTagName('AutenticaResult')->item(0)->nodeValue);
    }

    /**
     * @param $fecha_inicial
     * @param $fecha_final
     * @param $rfc_receptor
     * @param $rfc_emisor
     * @return SolicitaDescargaResult
     * @throws Exception
     */
    public function SolicitaDescarga(Carbon $fecha_inicial, Carbon $fecha_final, $rfc_emisor = null, $rfc_receptor = null) {
        if (!$this->token) {
            $this->Autenticar();
        }
        if (!$rfc_emisor && !$rfc_receptor) {
            throw new Exception('Debe indicar al menos un RFC emisor o receptor');
        }

        //Genero el SOAP de solicitud de cancelación
        $xmldoc = new DOMDocument();
        $xmldoc->preserveWhiteSpace = false;
        $xmldoc->formatOutput = false;
        $xmldoc->loadXML('<des:SolicitaDescarga xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx">
    <des:solicitud />
</des:SolicitaDescarga>');

        /** @var DOMElement $solicitud */
        $solicitud = $xmldoc->getElementsByTagName('solicitud')->item(0);
        if ($rfc_emisor) $solicitud->setAttribute('RfcEmisor', $rfc_emisor);
        if ($rfc_receptor) $solicitud->setAttribute('RfcReceptor', $rfc_receptor);
        $solicitud->setAttribute('RfcSolicitante', $this->sello->getPublicKeyRFC());
        $solicitud->setAttribute('FechaInicial', $fecha_inicial->format('Y-m-d\TH:i:s'));
        $solicitud->setAttribute('FechaFinal', $fecha_final->format('Y-m-d\TH:i:s'));
        $solicitud->setAttribute('TipoSolicitud', 'CFDI');

        $this->sello->signXml($solicitud);

        $dom_envelope = new DOMDocument;
        $dom_envelope->loadXML('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></s:Body></s:Envelope>');
        $dom_envelope->getElementsByTagName('Body')->item(0)->appendChild($dom_envelope->importNode($xmldoc->documentElement, true));
        $soap_request = $dom_envelope->saveXML();
        $curl = curl_init();

        curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml;charset=utf-8',
            'Content-Length: ' . strlen($soap_request),
            'SOAPAction: "http://DescargaMasivaTerceros.sat.gob.mx/ISolicitaDescargaService/SolicitaDescarga"',
            'Authorization: WRAP access_token="' . $this->token . '"'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soap_request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60 * 2);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60 * 5);
        $response = curl_exec($curl);

        return new SolicitaDescargaResult($response);
    }

    /**
     * @param $id_solicitud
     * @return VerificaSolicitudDescargaResult
     * @throws Exception
     */
    public function VerificaSolicitudDescarga($id_solicitud) {
        if (!$this->token) {
            $this->Autenticar();
        }

        //Genero el SOAP de solicitud de cancelación
        $xmldoc = new DOMDocument();
        $xmldoc->preserveWhiteSpace = false;
        $xmldoc->formatOutput = false;
        $xmldoc->loadXML('<des:VerificaSolicitudDescarga xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx">
    <des:solicitud />
</des:VerificaSolicitudDescarga>');
        $solicitud = $xmldoc->getElementsByTagName('solicitud')->item(0);
        $solicitud->setAttribute('IdSolicitud', $id_solicitud);
        $solicitud->setAttribute('RfcSolicitante', $this->sello->getPublicKeyRFC());

        $this->sello->signXml($solicitud);

        $dom_envelope = new DOMDocument;
        $dom_envelope->loadXML('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></s:Body></s:Envelope>');
        $dom_envelope->getElementsByTagName('Body')->item(0)->appendChild($dom_envelope->importNode($xmldoc->documentElement, true));
        $soap_request = $dom_envelope->saveXML();
        $curl = curl_init();

        curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml;charset=utf-8',
            'Content-Length: ' . strlen($soap_request),
            'SOAPAction: "http://DescargaMasivaTerceros.sat.gob.mx/IVerificaSolicitudDescargaService/VerificaSolicitudDescarga"',
            'Authorization: WRAP access_token="' . $this->token . '"'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soap_request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/VerificaSolicitudDescargaService.svc');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        $response = curl_exec($curl);

        return new VerificaSolicitudDescargaResult($response);
    }

    /**
     * @param $id_paquete
     * @return Paquete
     * @throws Exception
     */
    public function PeticionDescarga($id_paquete) {
        if (!$this->token) {
            $this->Autenticar();
        }

        //Genero el SOAP de solicitud de cancelación
        $xmldoc = new DOMDocument();
        $xmldoc->preserveWhiteSpace = false;
        $xmldoc->formatOutput = false;
        $xmldoc->loadXML('<des:PeticionDescargaMasivaTercerosEntrada xmlns:des="http://DescargaMasivaTerceros.sat.gob.mx">
    <des:peticionDescarga />
</des:PeticionDescargaMasivaTercerosEntrada>');

        /** @var DOMElement $solicitud */
        $solicitud = $xmldoc->getElementsByTagName('peticionDescarga')->item(0);
        $solicitud->setAttribute('IdPaquete', $id_paquete);
        $solicitud->setAttribute('RfcSolicitante', $this->sello->getPublicKeyRFC());

        $this->sello->signXml($solicitud);

        $dom_envelope = new DOMDocument;
        $dom_envelope->loadXML('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></s:Body></s:Envelope>');
        $dom_envelope->getElementsByTagName('Body')->item(0)->appendChild($dom_envelope->importNode($xmldoc->documentElement, true));
        $soap_request = $dom_envelope->saveXML();
        $curl = curl_init();

        curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml;charset=utf-8',
            'Content-Length: ' . strlen($soap_request),
            'SOAPAction: "http://DescargaMasivaTerceros.sat.gob.mx/IDescargaMasivaTercerosService/Descargar"',
            'Authorization: WRAP access_token="' . $this->token . '"'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $soap_request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, 'https://cfdidescargamasiva.clouda.sat.gob.mx/DescargaMasivaService.svc');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60 * 2);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60 * 5);
        $response = curl_exec($curl);

        return new Paquete($id_paquete, $response);
    }

}