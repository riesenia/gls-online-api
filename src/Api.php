<?php

declare(strict_types=1);

namespace Riesenia\GlsOnline;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class Api
{
    /** @var string */
    protected $wsdl = 'https://online.gls-slovakia.sk/webservices/soap_server.php?wsdl&ver=18.02.20.01';

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string */
    protected $senderId;

    /** @var string */
    protected $printerTemplate;

    /** @var Fpdi */
    protected $fpdi;

    /** @var \SoapClient */
    protected $soap;

    /** @var array */
    protected $errors = [];

    /**
     * Api constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $senderId
     * @param string $printerTemplate
     */
    public function __construct(string $username, string $password, string $senderId, string $printerTemplate = 'A4')
    {
        $this->username = $username;
        $this->password = $password;
        $this->senderId = $senderId;
        $this->printerTemplate = $printerTemplate;
        $this->fpdi = new Fpdi();

        $this->soap = new \SoapClient($this->wsdl);
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

        $response = $this->parseResponse($response);

        return $response;
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
            'printertemplate' => $this->printerTemplate,
            'data' => $data
        ]);

        $response = $this->parseResponse($response);

        return $response;
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
     * Get errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
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
     * Parse response.
     *
     * @param string $response
     *
     * @return array
     */
    protected function parseResponse(string $response): array
    {
        $response = \simplexml_load_string($response);
        $errors = [];
        $items = [];

        foreach ($response->Shipments->Shipment as $item) {
            // skip in case of error
            if (isset($item->Status['ErrorDescription']) && $item->Status['ErrorDescription']) {
                $errors[] = [
                    'shipment' => (string) $item['ClientRef'],
                    'message' => (string) $item->Status['ErrorDescription']
                ];
                continue;
            }

            // parse multiple PDF documents into one
            if (isset($item->Parcels)) {
                if ($item->Parcels->Parcel->count() > 1) {
                    try {
                        foreach ($item->Parcels->Parcel as $parcel) {
                            $this->addPagesToPdf((string) $parcel->Label);
                        }
                        $items[] = $this->fpdi->Output('S');

                        continue;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'parcelId' => $parcel['PclId'],
                            'message' => $e->getMessage()
                        ];

                        continue;
                    }
                } else {
                    $this->addPagesToPdf((string) $item->Parcels->Parcel->Label);
                    $items[] = $this->fpdi->Output('S');

                    continue;
                }
            }

            $items = $item;
        }

        $this->errors = $errors;

        return $items;
    }

    /**
     * Append pages to single pdf.
     *
     * @param string $pdfData
     */
    protected function addPagesToPdf(string $pdfData)
    {
        $pageCount = $this->fpdi->setSourceFile(StreamReader::createByString(\base64_decode($pdfData)));

        for ($i = 1; $i <= $pageCount; ++$i) {
            $template = $this->fpdi->importPage($i);
            $this->fpdi->AddPage();
            $this->fpdi->useTemplate($template);
        }
    }
}
