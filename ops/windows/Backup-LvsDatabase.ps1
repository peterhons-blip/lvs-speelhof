param(
    [string]$Root = 'C:\LVS-Backup',
    [string]$StatusDirectory = 'C:\LVS-Status',
    [string]$DefaultsFile = 'C:\LVS-Backup\private\mysql-backup.cnf',
    [string]$DumpExe = 'C:\xampp\mysql\bin\mysqldump.exe',
    [string]$GpgExe = 'C:\Program Files\GnuPG\bin\gpg.exe',
    [Parameter(Mandatory=$true)][ValidatePattern('^[A-Fa-f0-9]{40,64}$')][string]$Recipient,
    [string]$AzCopyExe = 'C:\LVS-Tools\azcopy.exe',
    [string]$BlobContainer = '',
    [string]$AzureApplicationId = '',
    [string]$AzureTenantId = '',
    [string]$AzureCertificatePath = 'C:\LVS-Backup\private\lvs-azure-backup.pfx',
    [string]$AzurePasswordFile = 'C:\LVS-Backup\private\azure-pfx-password.dpapi'
)
$ErrorActionPreference = 'Stop'
$stamp = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffZ')
$state = [ordered]@{ timestamp=[DateTimeOffset]::UtcNow.ToString('o'); local='failed'; external='not_configured'; bytes=0 }
$staging = Join-Path $Root 'private'
$archive = Join-Path $Root 'encrypted'
$plain = Join-Path $staging ("speelhof_lvs-$stamp.sql")
$encrypted = Join-Path $archive ("speelhof_lvs-$stamp.sql.gpg")
$lock = $null
function Write-Status {
    $target = Join-Path $StatusDirectory 'backup-status.json'
    $temp = Join-Path $StatusDirectory ('backup-'+[guid]::NewGuid().ToString('N')+'.tmp')
    [IO.File]::WriteAllText($temp,($state | ConvertTo-Json -Compress),[Text.UTF8Encoding]::new($false))
    try {
        if (Test-Path -LiteralPath $target) { [IO.File]::Replace($temp,$target,[NullString]::Value) }
        else { [IO.File]::Move($temp,$target) }
    } finally { if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force } }
}
try {
    # Directories and ACLs must be provisioned by ICT before execution.
    foreach ($dir in @($staging,$archive,$StatusDirectory)) {
        if (!(Test-Path -LiteralPath $dir -PathType Container)) { throw 'Required secure directory missing.' }
    }
    $lock = [IO.File]::Open((Join-Path $staging 'backup.lock'),'OpenOrCreate','ReadWrite','None')
    & $DumpExe "--defaults-extra-file=$DefaultsFile" --single-transaction --quick --skip-lock-tables --routines --triggers --events --default-character-set=utf8mb4 "--result-file=$plain" speelhof_lvs
    if ($LASTEXITCODE -ne 0 -or !(Test-Path -LiteralPath $plain) -or (Get-Item -LiteralPath $plain).Length -lt 100) { throw 'Database export failed.' }
    & $GpgExe --homedir (Join-Path $staging 'gnupg') --batch --yes --trust-model always --recipient $Recipient --output $encrypted --encrypt $plain
    if ($LASTEXITCODE -ne 0 -or !(Test-Path -LiteralPath $encrypted) -or (Get-Item -LiteralPath $encrypted).Length -lt 100) { throw 'Encryption failed.' }
    Remove-Item -LiteralPath $plain -Force
    $state.local = 'ok'
    $state.bytes = (Get-Item -LiteralPath $encrypted).Length
    if ($BlobContainer) {
        $state.external = 'failed'
        # AzCopy identity must already be authenticated under the scheduled account.
        # No SAS tokens or credentials in command line, status or source control.
        if ($BlobContainer -notmatch '^https://[a-z0-9]+\.blob\.core\.windows\.net/[a-z0-9-]+/?$') { throw 'Invalid Azure container URL.' }
        $destination = $BlobContainer.TrimEnd('/')+'/'+[IO.Path]::GetFileName($encrypted)
        if ($AzureApplicationId -notmatch '^[0-9a-fA-F-]{36}$' -or $AzureTenantId -notmatch '^[0-9a-fA-F-]{36}$') { throw 'Azure application/tenant ID missing or invalid.' }
        if (!(Test-Path -LiteralPath $AzureCertificatePath -PathType Leaf) -or !(Test-Path -LiteralPath $AzurePasswordFile -PathType Leaf)) { throw 'Azure certificate or protected password file missing.' }
        $secret = (Get-Content -LiteralPath $AzurePasswordFile -Raw).Trim() | ConvertTo-SecureString
        try {
            $env:AZCOPY_AUTO_LOGIN_TYPE = 'SPN'
            $env:AZCOPY_SPA_APPLICATION_ID = $AzureApplicationId
            $env:AZCOPY_TENANT_ID = $AzureTenantId
            $env:AZCOPY_SPA_CERT_PATH = $AzureCertificatePath
            $env:AZCOPY_SPA_CERT_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
            & $AzCopyExe copy $encrypted $destination --overwrite=false
            if ($LASTEXITCODE -ne 0) { throw 'External upload failed.' }
        } finally {
            Remove-Item Env:\AZCOPY_SPA_CERT_PASSWORD -ErrorAction SilentlyContinue
            $secret.Dispose()
        }
        $state.external = 'ok'
    }
    # Keep local encrypted backups for 14 days; only prune after successful external copy.
    if ($state.external -eq 'ok') {
        Get-ChildItem -LiteralPath $archive -File -Filter 'speelhof_lvs-*.sql.gpg' |
            Where-Object { $_.Name -match '^speelhof_lvs-\d{8}T\d{9}Z\.sql\.gpg$' -and $_.LastWriteTimeUtc -lt [DateTime]::UtcNow.AddDays(-14) } |
            Remove-Item -Force
    }
} catch {
    if ($state.local -ne 'ok' -and (Test-Path -LiteralPath $encrypted)) { Remove-Item -LiteralPath $encrypted -Force }
    throw
} finally {
    if ($null -ne $lock) {
        try {
            if (Test-Path -LiteralPath $plain) { Remove-Item -LiteralPath $plain -Force }
            $state.timestamp = [DateTimeOffset]::UtcNow.ToString('o')
            Write-Status
        } finally { $lock.Dispose() }
    }
}
