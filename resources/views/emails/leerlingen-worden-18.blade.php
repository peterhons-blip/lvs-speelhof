<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leerlingen die vandaag 18 worden</title>
    <style>
        /* Minimal: inline styles gebruiken we vooral in tags zelf voor client-compatibiliteit */
        @media (max-width: 620px) {
            .container { width: 100% !important; padding: 16px !important; }
            .content   { padding: 16px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f4f7f9;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f4f7f9;">
    <tr>
        <td align="center" style="padding:24px;">
            <table class="container" role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px; max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05);">

                <!-- Header met schoollogo links en LVS-logo rechts -->
                <tr>
                    <td style="background:#0d7a43; padding:16px 24px;" align="left">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <!-- Schoollogo links -->
                                <td align="left" valign="middle">
                                    <img src="{{ $logoSchoolCid }}" width="180" alt="Atheneum Sint-Truiden"
                                         style="display:block; border:0; outline:none; text-decoration:none; max-width:100%;" />
                                </td>

                                <!-- LVS-logo rechts met exact passende witte achtergrond -->
                                <td align="right" valign="middle">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                                           style="background:#ffffff; border-radius:12px; padding:6px;">
                                        <tr>
                                            <td align="center" valign="middle">
                                                <img src="{{ $logoLvsCid }}" width="64" height="64" alt="LVS"
                                                     style="display:block; border:0; outline:none; text-decoration:none; border-radius:8px; background:#ffffff;" />
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>



                <!-- Titel -->
                <tr>
                    <td class="content" style="padding:24px;">
                        <h1 style="margin:0 0 12px; font-family:Arial,Helvetica,sans-serif; font-size:22px; line-height:1.3; color:#111;">
                            Leerlingen die vandaag 18 jaar worden 🎉
                        </h1>
                        <p style="margin:0 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#333;">
                            Overzicht gegenereerd door {{ $appName ?? config('app.name') }}.
                        </p>

                        @if(count($leerlingen) > 0)
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:8px 0 16px;">
                                @foreach($leerlingen as $l)
                                    <tr>
                                        <td style="padding:10px 12px; border:1px solid #e7eef3; border-radius:8px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#222; background:#fbfdff;">
                                            <strong>{{ $l['voornaam'] }} {{ $l['naam'] }}</strong>
                                            <span style="color:#666;">
                                              — klas {{ $l['klas'] }}, geboortedatum {{ $l['geboortedatum']->format('d-m-Y') }}
                                            </span>
                                        </td>
                                    </tr>
                                    <!-- lege rij als spatie -->
                                    <tr>
                                        <td style="height:8px; line-height:8px;">&nbsp;</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <p style="margin:0 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#333;">
                                Er wordt vandaag niemand 18 jaar.
                            </p>
                        @endif

                        <p style="margin:20px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#666;">
                            Deze e-mail is automatisch verstuurd op {{ now('Europe/Brussels')->format('d-m-Y H:i') }}.
                        </p>
                    </td>
                </tr>
            </table>

            <div style="padding:12px; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#9aa7b2;">
                © {{ date('Y') }} {{ $appName ?? config('app.name') }} — Alle rechten voorbehouden.
            </div>
        </td>
    </tr>
</table>
</body>
</html>
