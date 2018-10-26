<?php

declare(strict_types=1);

namespace Riesenia\GlsOnline;

class Api
{
    /** @var string */
    protected $wsdl = 'https://online.gls-slovakia.sk/webservices/soap_server.php?wsdl&ver=18.02.20.01';

    /** @var string|null */
    protected $username;

    /** @var string|null */
    protected $password;

    /** @var \SoapClient|null */
    protected $soap;

    /** @var string|null */
    protected $messages;

    /**
     * Api constructor.
     *
     * @param string|null $username
     * @param string|null $password
     */
    public function __construct(string $username = null, string $password = null)
    {
        $this->username = $username;
        $this->password = $password;

        try {
            $this->soap = new \SoapClient($this->wsdl);
        } catch (\Exception $e) {
            throw new \Exception('Failed to build soap client');
        }
    }

    /**
     * Create shipments.
     *
     * @param array $shipments
     *
     * @return \SimpleXMLElement
     */
    public function send(array $shipments): \SimpleXMLElement
    {
        $data = $this->prepareRequestData($shipments);

        $response = $this->soap->__soapCall('preparelabels_xml', [
            'username' => $this->username,
            'password' => $this->password,
            'senderid' => $this->username,
            'data' => $data
        ]);

        $response = simplexml_load_string($response);

        if (isset($response->Shipments->Shipment->Status['ErrorCode']) && $response->Shipments->Shipment->Status['ErrorCode'] !== 0) {
            throw new \Exception('Request failed: ' . $response->Shipments->Shipment->Status['ErrorDescription']);
        }

        return $response->Shipments;
    }

    /**
     * Fetch printed labels.
     *
     * @param array $data
     *
     * @return \SimpleXMLElement
     */
    public function getPrintedLabels(array $data): \SimpleXMLElement
    {
        $data = $this->prepareRequestData($data);

        $response = $this->soap->_soapCall('getprintedlabels_xml', [
            'username' => $this->username,
            'password' => $this->password,
            'senderid' => $this->username,
            'data' => $data
        ]);

        $response = simplexml_load_string($response);

        if (isset($response->Shipments->Shipment->Status['ErrorCode']) && $response->Shipments->Shipment->Status['ErrorCode'] !== 0) {
            throw new \Exception('Request failed: ' . $response->Shipments->Shipment->Status['ErrorDescription']);
        }

        return $response->Shipments->Shipment->Parcels;
    }

    /**
     * Build XML string.
     *
     * @param array $data
     *
     * @return string
     */
    protected function prepareRequestData(array $data): string
    {
        $xml = new \SimpleXMLElement('<root></root>');
        $xmlData = $xml->addChild('DTU');

        $this->arrayToXml($data, $xmlData);

        $dom = \dom_import_simplexml($xml);

        $innerXML = '';
        foreach ($dom->childNodes as $node) {
            $innerXML .= $node->ownerDocument->saveXML($node);
        }

        return $innerXML;
    }

    /**
     * Convert array to XML.
     *
     * @param array             $array
     * @param \SimpleXMLElement $xml
     */
    protected function arrayToXml(array $array, \SimpleXMLElement $xml)
    {
        foreach ($array as $key => $value) {
            if (\is_string($key) && \strpos($key, '@') === 0) {
                $xml->addAttribute(\substr($key, 1), $value);
                continue;
            }
            if (\is_array($value)) {
                if (\array_keys($value) === \range(0, \count($value) - 1)) {
                    foreach ($value as $item) {
                        if (\is_array($item)) {
                            $subnode = $xml->addChild($key);
                            $this->arrayToXml($item, $subnode);
                        } else {
                            $xml->addChild($key, "$item");
                        }
                    }
                } else {
                    $subnode = $xml->addChild($key);
                    $this->arrayToXml($value, $subnode);
                }
            } else {
                $xml->addChild($key, \htmlspecialchars($value));
            }
        }
    }
}
