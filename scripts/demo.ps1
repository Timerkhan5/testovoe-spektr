#requires -Version 5.1

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidateSet('off', 'on', 'submit', 'replay')]
    [string]$Action,

    [ValidateRange(1, 65535)]
    [int]$Port = 8080
)

$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$utf8 = [Text.UTF8Encoding]::new($false)

if ($Action -in 'off', 'on') {
    $configPath = Join-Path $project '.env'
    $config = [IO.File]::ReadAllText($configPath)
    if ($config -notmatch '(?m)^[ \t]*APP_ENV[ \t]*=[ \t]*(?:local|"local"|''local'')[ \t]*\r?$') {
        throw 'The demo switch requires APP_ENV=local in .env.'
    }

    $marker = '; BEGIN SPEKTR DEMO OUTAGE'
    $newline = if ($config.Contains("`r`n")) { "`r`n" } else { "`n" }
    $block = @(
        '',
        $marker,
        'AI_API_KEY=demo-offline',
        'AI_BASE_URL=http://127.0.0.1:1',
        'BITRIX_WEBHOOK_URL=http://127.0.0.1:1/rest/1/demo/',
        '; END SPEKTR DEMO OUTAGE',
        ''
    ) -join $newline
    $isOff = $config.Contains($block)
    if ($config.Contains($marker) -and -not $isOff) {
        throw 'The demo block was edited. Restore the block or remove it from .env before retrying.'
    }
    if ($Action -eq 'off' -and -not $isOff) {
        $config += $block
    } elseif ($Action -eq 'on' -and $isOff) {
        $config = $config.Replace($block, '')
    } else {
        $state = if ($isOff) { 'active' } else { 'inactive' }
        Write-Output "Demo outage is already $state."
        return
    }

    $temp = "$configPath.demo.$([guid]::NewGuid().ToString('N')).tmp"
    try {
        [IO.File]::WriteAllText($temp, $config, $utf8)
        [IO.File]::Replace($temp, $configPath, [NullString]::Value)
    } finally {
        if ([IO.File]::Exists($temp)) {
            [IO.File]::Delete($temp)
        }
    }
    if ($Action -eq 'off') {
        Write-Output 'Demo outage active. AI and Bitrix requests now go to an unavailable local port.'
    } else {
        Write-Output 'Demo outage removed. Original AI and Bitrix settings are active again.'
    }
    return
}

$stateDirectory = Join-Path $project 'var'
$requestPath = Join-Path $stateDirectory 'demo-request.json'
if ($Action -eq 'submit') {
    $payload = [ordered]@{
        request_uuid = [guid]::NewGuid().ToString()
        name = 'Spektr Demo'
        phone = '+7 (900) 000-00-04'
        email = 'demo@example.invalid'
        comment = 'Urgent demo request. We need to sign the contract today.'
    }
    $json = $payload | ConvertTo-Json -Compress
    $null = [IO.Directory]::CreateDirectory($stateDirectory)
    [IO.File]::WriteAllText($requestPath, $json, $utf8)
} else {
    if (-not [IO.File]::Exists($requestPath)) {
        throw 'No saved demo request. Run submit first.'
    }
    $json = [IO.File]::ReadAllText($requestPath)
    $payload = $json | ConvertFrom-Json
}

$origin = "http://127.0.0.1:$Port"
Write-Output ("Request UUID: " + $payload.request_uuid)
$post = @{
    Uri = "$origin/api/requests"
    Method = 'Post'
    ContentType = 'application/json; charset=utf-8'
    Body = $utf8.GetBytes($json)
    TimeoutSec = 40
}
$response = Invoke-WebRequest -UseBasicParsing @post
$result = $response.Content | ConvertFrom-Json
if ($result.request_uuid -ne $payload.request_uuid) {
    throw 'The API returned a different request UUID.'
}
Write-Output ("HTTP " + [int]$response.StatusCode)
Write-Output "Admin panel: $origin/admin/requests"
