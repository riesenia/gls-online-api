<?php

declare(strict_types=1);

namespace Riesenia\GlsOnline;

class Api
{
    /** @var string */
    protected $wsdl = 'https://online.gls-slovakia.sk/webservices/soap_server.php?wsdl&ver=18.02.20.01';

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string string */
    protected $senderId;

    /** @var \SoapClient|null */
    protected $soap;

    /** @var array */
    protected $errors = [];

    /**
     * Api constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $senderId
     */
    public function __construct(string $username, string $password, string $senderId)
    {
        $this->username = $username;
        $this->password = $password;
        $this->senderId = $senderId;

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

        $response = \simplexml_load_string($response);

        $this->setErrors($response);

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
            'senderid' => $this->senderId,
            'data' => $data
        ]);

        $response = \simplexml_load_string($response);

        $this->setErrors($response);

        return $response->Shipments->Shipment->Parcels;
    }

    /**
     * Build XML string.
     *
     * @param array $data
     *
     * @return string
     */
    public function prepareRequestData(array $data): string
    {
        $xml = new \SimpleXMLElement('<root></root>');
        $xmlData = $xml->addChild('DTU');

        $this->arrayToXml($data, $xmlData);

        $dom = new \DOMDocument();
        $dom->loadXML($xmlData->asXML());
        if (!$dom->schemaValidate(__DIR__ . '/../DTU.xsd')) {
            throw new \Exception('Invalid schema.');
        }

        return $xmlData->asXML();
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

    /**
     * Set errors from response.
     *
     * @param \SimpleXMLElement $response
     */
    protected function setErrors(\SimpleXMLElement $response)
    {
        $errors = [];
        foreach ($response->Shipments->Shipment as $item) {
            if (isset($item->Status['ErrorDescription']) && $item->Status['ErrorDescription']) {
                $errors[] = [
                    'shipment' => (string) $item['ClientRef'],
                    'message' => (string) $item->Status['ErrorDescription']
                ];
            }
        }

        $this->errors = $errors;
    }

    /**
     * Get errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
