<p>Beste ouder/voogd van {{ $voornaam }} {{ $naam }}</p>

<p>
    Dit is een automatische melding vanuit {{ $school->schoolnaam ?? 'de school' }}.<br>
</p>


<p>
    Binnen een week wordt {{ $voornaam }} {{ $naam }} 18 jaar.<br>
    Vanaf de leeftijd van 18 beslist de leerling zelf wie toegang heeft tot zijn/haar gegevens in Smartschool.<br>
</p>

<p>
    Daarom zullen de co-accounts (ouder-/voogdaccounts) automatisch uitgeschakeld worden op de 18e verjaardag, conform de privacywetgeving.<br>
</p>

<p>
    Indien de leerling nadien toestemming geeft, kan het ouderaccount opnieuw geactiveerd worden.
</p>

<p>
    Met vriendelijke groeten<br>
    <strong>{{ $school->schoolnaam ?? 'GO! Atheneum Sint-Truiden – campus Speelhof' }}</strong>
</p>
