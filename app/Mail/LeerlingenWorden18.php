<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class LeerlingenWorden18 extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<array{name:string, geboortedatum:\Illuminate\Support\Carbon}> */
    public array $leerlingen;

    // CIDs die de Blade gebruikt
    public string $logoLvsCid;
    public string $logoSchoolCid;

    // DataParts die we later toevoegen aan het Email-object
    private DataPart $lvsPart;
    private DataPart $schoolPart;

    public function __construct(array $leerlingen)
    {
        $this->leerlingen = $leerlingen;
    }

    public function build()
    {
        // 1) Maak inline parts en bepaal de CIDs NU
        $this->lvsPart    = DataPart::fromPath(public_path('images/logo_lvs.png'))->asInline();
        $this->schoolPart = DataPart::fromPath(public_path('images/logo.png'))->asInline();

        $this->logoLvsCid    = 'cid:' . $this->lvsPart->getContentId();
        $this->logoSchoolCid = 'cid:' . $this->schoolPart->getContentId();

        // 2) Geef CIDs mee aan de view (nu zijn ze al bekend)
        $this->subject(config('app.name') . ' - Leerlingen die vandaag 18 worden – ')
            ->view('emails.leerlingen-worden-18')
            ->with([
                'appName'       => config('app.name'),
                'logoLvsCid'    => $this->logoLvsCid,
                'logoSchoolCid' => $this->logoSchoolCid,
            ]);

        // 3) Koppel de inline parts aan het Symfony Email-object
        $this->withSymfonyMessage(function (Email $email) {
            $email->addPart($this->lvsPart);
            $email->addPart($this->schoolPart);
        });

        return $this;
    }
}
