<p>Beste</p>

<p>
    Hierbij een overzicht van de leerlingen die vandaag 18 worden ({{ now('Europe/Brussels')->format('d-m-Y') }})<br>
    School: {{ $school->schoolnaam ?? '—' }}<br>

</p>

<p>
    @foreach($payload as $l)
        - {{ $l['voornaam'] }} {{ $l['naam'] }} (klas {{ $l['klas'] }}) — geboortedatum {{ optional($l['geboortedatum'])->format('d-m-Y') }}<br>
        Smartschool bericht (co-accounts): {{ ($l['smartschool_bericht_verzonden'] ?? false) ? 'JA' : 'NEE' }}<br>
        Co-accounts uitgeschakeld: {{ ($l['coaccounts_uitgeschakeld'] ?? false) ? 'JA' : 'NEE' }}<br>
        Bericht secretariaat: {{ ($l['secretariaat_bericht_verzonden'] ?? false) ? 'JA' : 'NEE' }}<br>
    @endforeach
</p>

<p>
    Dit is een automatisch bericht, gelieve hier niet op te antwoorden.
</p>

<p>
    Met vriendelijke groeten<br>
    <strong>{{ $school->schoolnaam ?? 'GO! Atheneum Sint-Truiden – campus Speelhof' }}</strong>
</p>