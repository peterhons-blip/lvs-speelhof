<p>Beste</p>

<p>
    Hierbij een overzicht van de leerlingen die vandaag 18 worden ({{ now('Europe/Brussels')->format('d-m-Y') }})<br><br>
    
    School: {{ $school->schoolnaam ?? '—' }}<br>
</p>

<p>
    @foreach($payload as $l)
        <strong>{{ $l['voornaam'] }} {{ $l['naam'] }}</strong><br>
        Klas: {{ $l['klas'] ?? '—' }}<br>
        Geboortedatum: {{ optional($l['geboortedatum'])->format('d-m-Y') }}<br><br>
        Bericht co-accounts verstuurd: {{ !empty($l['smartschool_bericht_verzonden']) ? 'JA' : 'NEE' }}<br>
        Co-accounts uitgeschakeld: {{ !empty($l['coaccounts_uitgeschakeld']) ? 'JA' : 'NEE' }}<br>
        Bericht alngskomensecretariaat verstuurd: {{ !empty($l['secretariaat_bericht_verzonden']) ? 'JA' : 'NEE' }}<br>
        ---<br>
    @endforeach
</p>

<p>
    Dit is een automatisch bericht, gelieve hier niet op te antwoorden.
</p>

<p>
    Met vriendelijke groeten<br>
    <strong>{{ $school->schoolnaam ?? 'GO! Atheneum Sint-Truiden – campus Speelhof' }}</strong>
</p>