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

    /** @var string */
    protected $senderId;

    /** @var string */
    protected $printerTemplate;

    /** @var \SoapClient */
    protected $soap;

    /**
     * Api constructor.
     *
     * @param string $username
     * @param string $password
     * @param string $senderId
     * @param string $printerTemplate
     */
    public function __construct(string $username, string $password, string $senderId, string $printerTemplate = 'A4', bool $debugMode = false)
    {
        $this->username = $username;
        $this->password = $password;
        $this->senderId = $senderId;
        $this->printerTemplate = $printerTemplate;

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
        $auth = [
            'username' => $this->username,
            'password' => $this->password,
            'senderid' => $this->senderId
        ];

        $data = array_merge($auth, $shipment);
        $data['hash'] = $this->soap->__soapCall('getglshash', $data);

        $response = $this->soap->__soapCall('printlabel', $data);

        if (isset($response->successfull) && $response->successfull !== true) {
            throw new \Exception('Request failed with: ' . $response->errcode . '. ' . $response->errdesc);
        }

        return $response;
    }

    /**
     * Get hash based on request fields.
     *
     * @param array $shipment
     *
     * @return string
     */
    protected function _getPrintLabelHash(array $shipment): string
    {
        $hashBase = '';
        foreach($shipment as $key => $value) {
            if (!in_array($key, ['services', 'hash', 'timestamp', 'printit', 'printertemplate', 'customlabel'])) {
                $hashBase .= (string) $value;
            }
        }

        return sha1($hashBase);
    }
}
