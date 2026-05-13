<?php

namespace Replicon;

use Exception;

class RepliconConnector
{
    private $dataService;

    public function __construct()
    {
        $this->dataService = curl_init();
        $this->configureApiSettings();
    }

    private function configureApiSettings()
    {
        curl_setopt_array($this->dataService, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 6000,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Accept: */*",
                "Accept-Encoding: gzip, deflate",
                "Authorization: Basic {$this->getAuthorizationCode()} ",
                "Cache-Control: no-cache",
                "Content-Type: application/json"
            ]
        ));
    }

    public function getDataService()
    {
        return $this->dataService;
    }

    public static function dataService()
    {
        $connector = new self();
        return $connector->getDataService();
    }

    private function getAuthorizationCode(): string
    {
        return base64_encode(getenv('REPLICON_API_COMPANY') . '\\' . getenv('REPLICON_API_USER') . ':' . getenv('REPLICON_API_PASSWORD'));
    }

    public static function singleRequest(array $jsonParams, $repliconService)
    {
        $dataService = self::dataService();

        $userUri = json_encode($jsonParams, JSON_FORCE_OBJECT);
        $requestUrl = getenv('REPLICON_API_ENDPOINT') . $repliconService;

        curl_setopt($dataService, CURLOPT_POSTFIELDS, $userUri);
        curl_setopt($dataService, CURLOPT_URL, $requestUrl);

        $response = curl_exec($dataService);
        $error = curl_error($dataService);

        if ($error) {
            throw new Exception("Replicon Connector cURL Error: " . $error);
        } else {
            return json_decode($response);
        }
    }
}