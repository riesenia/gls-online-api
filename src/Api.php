<?php

declare(strict_types=1);

namespace Riesenia\GlsOnline;

class Api
{
    /** @var string */
    protected $wsdl = 'https://api.mygls.{tld}/ParcelService.svc?singleWsdl';

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string */
    protected $webEngine;

    /** @var \SoapClient */
    protected $soap;

    /**
     * Api constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $tld
     * @param string $printerTemplate
     * @param bool   $debugMode
     */
    public function __construct(string $username, string $password, string $tld = 'sk', bool $debugMode = false, string $webEngine = 'Rshop')
    {
        $this->wsdl = \str_replace('{tld}', $tld, $this->wsdl);
        $this->username = $username;
        $this->password = \hash('sha512', $password, true);
        $this->webEngine = $webEngine;

        $this->soap = new \SoapClient($this->wsdl, [
            'trace' => $debugMode
        ]);
    }

    /**
     * Create shipment and return labels.
     *
     * @param array $shipment
     *
     * @return \stdClass
     */
    public function send(array $shipment): \stdClass
    {
        $data = [
            'Username' => $this->username,
            'Password' => $this->password,
            'ParcelList' => [(object) $shipment],
            'WebshopEngine' => $this->webEngine
        ];

        $response = $this->soap->PrintLabels(['printLabelsRequest' => $data]);

        if (isset($response->ErrorCode)) {
            throw new \Exception('Request failed with: ' . $response->ErrorCode . '. ' . $response->ErrorDescription);
        }

        return $response;
    }

    /**
     * Get parcel status.
     *
     * @param string $parcelNumber
     * @param bool $pdfResponse
     * @param string $languageIsoCode
     *
     * @return \stdClass
     */
    public function getParcelStatus(string $parcelNumber, bool $pdfResponse = false, string $languageIsoCode = 'EN')
    {
        $data = [
            'Username' => $this->username,
            'Password' => $this->password,
            'ParcelNumber' => $parcelNumber,
            'ReturnPOD' => $pdfResponse,
            'LanguageIsoCode' => $languageIsoCode
        ];

        $response = $this->soap->GetParcelStatuses(['getParcelStatusesRequest' => $data]);

        if (isset($response->ErrorCode)) {
            throw new \Exception('Request failed with: ' . $response->ErrorCode . '. ' . $response->ErrorDescription);
        }

        return $response;
    }
}
