
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet("StartTunnel", "StopTunnel", "ExtractUrl", "ReadUrl", "UpdateUrl", "CheckUrl")]
    [string]$Action,

    [string]$Cloudflared,
    [string]$LogFile,
    [string]$ErrorLog,
    [string]$PidFile,
    [string]$EnvFile,
    [string]$NewUrl,
    [string]$Url
)

$ErrorActionPreference = "Stop"

function Get-ManagedProcess {
    if (-not (Test-Path -LiteralPath $PidFile)) {
        return $null
    }

    $savedPid = 0
    if (-not [int]::TryParse(
        (Get-Content -LiteralPath $PidFile -Raw).Trim(),
        [ref]$savedPid
    )) {
        return $null
    }

    try {
        $proc = Get-Process -Id $savedPid -ErrorAction Stop
        if ($proc.ProcessName -eq "cloudflared") {
            return $proc
        }
    }
    catch {
        return $null
    }

    return $null
}

switch ($Action) {

    "StartTunnel" {
        $existing = Get-ManagedProcess

        if ($null -ne $existing) {
            Write-Output "Tunnel process masih berjalan."
            exit 0
        }

        Remove-Item -LiteralPath $LogFile, $ErrorLog -Force -ErrorAction SilentlyContinue

        $process = Start-Process `
            -FilePath $Cloudflared `
            -ArgumentList @("tunnel", "--url", "http://localhost:8000") `
            -RedirectStandardOutput $LogFile `
            -RedirectStandardError $ErrorLog `
            -PassThru `
            -WindowStyle Hidden

        Set-Content -LiteralPath $PidFile -Value $process.Id -Encoding ASCII

        Write-Output "Cloudflare Tunnel dimulai."
        exit 0
    }

    "StopTunnel" {
        $proc = Get-ManagedProcess

        if ($null -ne $proc) {
            Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
        }

        Remove-Item -LiteralPath $PidFile -Force -ErrorAction SilentlyContinue
        exit 0
    }

    "ExtractUrl" {
        $all = @()

        foreach ($path in @($LogFile, $ErrorLog)) {
            if (Test-Path -LiteralPath $path) {
                $all += Get-Content -LiteralPath $path -Raw -ErrorAction SilentlyContinue
            }
        }

        $text = $all -join "`n"
        $matches = [regex]::Matches(
            $text,
            'https://[a-zA-Z0-9-]+\.trycloudflare\.com'
        )

        if ($matches.Count -gt 0) {
            Write-Output $matches[$matches.Count - 1].Value
            exit 0
        }

        exit 1
    }

    "ReadUrl" {
        $line = Get-Content -LiteralPath $EnvFile |
            Where-Object { $_ -match '^\s*VITE_API_URL\s*=' } |
            Select-Object -Last 1

        if ($line) {
            $value = ($line -split '=', 2)[1].Trim()
            $value = $value.Trim([char]34).Trim([char]39)
            Write-Output $value
        }

        exit 0
    }

    "UpdateUrl" {
        if ($NewUrl -notmatch '^https://[a-zA-Z0-9-]+\.trycloudflare\.com$') {
            throw "Format URL tunnel tidak valid."
        }

        $lines = [System.Collections.Generic.List[string]]::new()
        Get-Content -LiteralPath $EnvFile |
            ForEach-Object { $lines.Add($_) }

        $found = $false

        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match '^\s*VITE_API_URL\s*=') {
                if (-not $found) {
                    $lines[$i] = "VITE_API_URL=$NewUrl"
                    $found = $true
                }
                else {
                    $lines.RemoveAt($i)
                    $i--
                }
            }
        }

        if (-not $found) {
            $lines.Add("VITE_API_URL=$NewUrl")
        }

        $encoding = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllLines($EnvFile, $lines, $encoding)

        exit 0
    }

    "CheckUrl" {
        if ([string]::IsNullOrWhiteSpace($Url)) {
            exit 1
        }

        try {
            Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 8 | Out-Null
            exit 0
        }
        catch {
            if ($null -ne $_.Exception.Response) {
                # Respons HTTP berarti URL masih merespons.
                exit 0
            }

            exit 1
        }
    }
}
