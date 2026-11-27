param(
    [string]$StatusDirectory = 'C:\LVS-Status'
)
$ErrorActionPreference = 'Stop'
$cpu = Get-CimInstance Win32_Processor
$os = Get-CimInstance Win32_OperatingSystem
$load = ($cpu | Measure-Object -Property LoadPercentage -Average).Average
$total = [double]$os.TotalVisibleMemorySize
$free = [double]$os.FreePhysicalMemory
if ($total -le 0 -or $null -eq $load) { throw 'No reliable system metrics available.' }
$data = [ordered]@{
    timestamp = [DateTimeOffset]::UtcNow.ToString('o')
    cpu_percent = [math]::Round($load,1)
    ram_percent = [math]::Round(100*($total-$free)/$total,1)
    ram_total_mb = [math]::Round($total/1024,1)
    ram_free_mb = [math]::Round($free/1024,1)
}
$target = Join-Path $StatusDirectory 'server-health.json'
$temp = Join-Path $StatusDirectory ('health-'+[guid]::NewGuid().ToString('N')+'.tmp')
[IO.File]::WriteAllText($temp,($data | ConvertTo-Json -Compress),[Text.UTF8Encoding]::new($false))
try {
    if (Test-Path -LiteralPath $target) { [IO.File]::Replace($temp,$target,$null) }
    else { [IO.File]::Move($temp,$target) }
} finally { if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force } }
