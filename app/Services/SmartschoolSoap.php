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
     * sendMsg via Webservices V3 (SOAP)
     *
     * @param string      $userIdentifier   Smartschool unieke identifier van de gebruiker (meestal gebruikersnaam)
     * @param string      $title            Onderwerp
     * @param string      $body             Berichttekst
     * @param string|null $senderIdentifier Uniek veld gebruiker van de verzender. Geef null om geen verzender in te stellen.
     * @param bool        $copyToLVS        true = kopie naar LVS (indien ondersteund/gewild)
     * @param int         $coaccount        0 = hoofdaccount, 1..6 = co-accounts
     * @param mixed|null  $attachments      null of array/JSON-string volgens Smartschool doc (optioneel)
     */
    public function sendMessage(
        string $userIdentifier,
        string $title,
        string $body,
        ?string $senderIdentifier = null,
        bool $copyToLVS = false,
        int $coaccount = 0,
        $attachments = null
    ) {
        // Default sender:
        // - als expliciet meegegeven: gebruik die
        // - anders: env SMARTSCHOOL_SENDER_USER
        // - anders: 'lvs'
        $sender = $senderIdentifier;
        if ($sender === null || trim($sender) === '') {
            $sender = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';
        }

        // Coaccount afkappen op 0..6 (fail-safe)
        $coaccount = max(0, min(6, (int) $coaccount));

        try {
            /**
             * Signature volgens doc:
             * sendMsg(string $accesscode, string $userIdentifier, string $title, string $body,
             *         string $senderIdentifier, mixed $attachments, integer $coaccount, boolean $copyToLVS=false)
             */
            return $this->client->sendMsg(
                $this->accesscode,
                $userIdentifier,
                $title,
                $body,
                $sender === null ? 'Null' : $sender, // Smartschool gebruikt soms letterlijk 'Null'
                $attachments,                        // null of array/json-string
                $coaccount,
                $copyToLVS
            );
        } catch (SoapFault $e) {
            // extra logging kan nuttig zijn
            Log::error("Smartschool sendMsg faalde (user={$userIdentifier}, co={$coaccount}, sender={$sender}): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Zet co-accounts 1..6 op 'niet actief'
     * (logt fouten maar stopt de flow niet)
     */
    public function disableCoAccounts(string $userIdentifier): void
    {
        for ($i = 1; $i <= 6; $i++) {
            try {
                $this->client->saveUserParameter(
                    $this->accesscode,
                    $userIdentifier,
                    "status_coaccount{$i}",
                    'niet actief'
                );
            } catch (SoapFault $e) {
                Log::error("Fout bij uitschakelen co-account {$i} voor {$userIdentifier}: " . $e->getMessage());
            }
        }
    }

    /**
     * Optioneel: co-account terug activeren (als je later nodig hebt)
     */
    public function enableCoAccount(string $userIdentifier, int $coaccountNr = 1): void
    {
        $coaccountNr = max(1, min(6, (int) $coaccountNr));

        try {
            $this->client->saveUserParameter(
                $this->accesscode,
                $userIdentifier,
                "status_coaccount{$coaccountNr}",
                'actief'
            );
        } catch (SoapFault $e) {
            Log::error("Fout bij activeren co-account {$coaccountNr} voor {$userIdentifier}: " . $e->getMessage());
            throw $e;
        }
    }

    public function getErrorCodes(): array
    {
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
