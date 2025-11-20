<?php

namespace App\Services;

use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;


class SmartschoolSoap
{
    protected SoapClient $client;
    protected string $accesscode;

    public function __construct()
    {
        $wsdl = config('services.smartschool.wsdl');

        $this->client = new SoapClient($wsdl, [
            'trace'      => true,
            'exceptions' => true,
        ]);

        $this->accesscode = config('services.smartschool.accesscode');
    }

    /**
     * Eenvoudige testversie van sendMsg zonder bijlagen.
     */
    public function sendMessage(
        string $userIdentifier,
        string $title,
        string $body,
        ?string $ignoredSender = null,   // wordt genegeerd
        bool $copyToLVS = false
    ) {
        // Verzender uit config halen
        $senderIdentifier = env('SMARTSCHOOL_SENDER_USER');

        try {
            return $this->client->sendMsg(
                $this->accesscode,     // string $accesscode
                $userIdentifier,       // ontvanger
                $title,                // titel
                $body,                 // body
                $senderIdentifier,     // VERPLICHT "lvs"
                null,                  // attachments (geen)
                0,                     // co-account hoofdaccount
                $copyToLVS             // naar LVS kopiëren?
            );
        } catch (SoapFault $e) {
            throw $e;
        }
    }

    public function disableCoAccounts(string $userIdentifier): void
    {
        // Zet co-accounts 1 t.e.m. 6 op 'niet actief'
        for ($i = 1; $i <= 6; $i++) {
            try {
                $this->client->saveUserParameter(
                    $this->accesscode,                 // string $accesscode
                    $userIdentifier,                   // string $userIdentifier
                    "status_coaccount{$i}",            // string $paramName
                    'niet actief'                      // string $paramValue
                );
            } catch (\SoapFault $e) {
                // We loggen, maar gooien niet door zodat de rest (bericht/mail) kan doorgaan
                Log::error("Fout bij uitschakelen co-account {$i} voor {$userIdentifier}: ".$e->getMessage());
            }
        }
    }



    public function getErrorCodes(): array
    {
        // Deze methode heeft volgens de documentatie géén parameters
        $json = $this->client->returnJsonErrorCodes();

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    public function getErrorDescription($code): ?string
    {
        $codes = $this->getErrorCodes();

        return $codes[$code] ?? null;
    }

    public function getUserDetails(string $userIdentifier)
    {
        return $this->client->getUserDetails(
            $this->accesscode,
            $userIdentifier
        );
    }

}
