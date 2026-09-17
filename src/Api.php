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

    /** @var string|null */
    protected $typeOfPrinter;

    /**
     * Api constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $tld
     * @param bool   $debugMode
     * @param string $webEngine
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
     * Set printer type for labels (A4_2x2, A4_4x1, Connect, Thermo, ThermoZPL, ThermoZPL300).
     *
     * @param string|null $typeOfPrinter
     *
     * @return $this
     */
    public function setTypeOfPrinter(string $typeOfPrinter = null): self
    {
        $this->typeOfPrinter = $typeOfPrinter;

        return $this;
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

        if ($this->typeOfPrinter) {
            $data['TypeOfPrinter'] = $this->typeOfPrinter;
        }

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
