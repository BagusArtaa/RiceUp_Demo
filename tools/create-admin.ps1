$ErrorActionPreference = 'Stop'

$username = Read-Host 'Username admin (3-50 karakter)'
if ($username -notmatch '^[A-Za-z0-9_.-]{3,50}$') {
    throw 'Username harus 3-50 karakter berupa huruf, angka, titik, garis bawah, atau tanda hubung.'
}

$first = Read-Host 'Password admin (12 karakter minimum)' -AsSecureString
$second = Read-Host 'Ulangi password admin' -AsSecureString
$firstPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($first)
$secondPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($second)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($firstPtr)
    $confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secondPtr)
    if ($plainPassword.Length -lt 12 -or $plainPassword.Length -gt 200) {
        throw 'Password harus 12-200 karakter.'
    }
    if ($plainPassword -cne $confirmation) {
        throw 'Password tidak cocok.'
    }

    if ($env:RICEUP_PHP) {
        if (-not (Test-Path -LiteralPath $env:RICEUP_PHP -PathType Leaf)) {
            throw 'RICEUP_PHP does not point to an existing PHP executable.'
        }
        $php = $env:RICEUP_PHP
    } else {
        $projectRoot = Split-Path -Parent $PSScriptRoot
        $xamppRoot = Split-Path -Parent (Split-Path -Parent $projectRoot)
        $xamppPhpCandidates = @(
            (Join-Path $xamppRoot 'php\php.exe'),
            'C:\xampp\php\php.exe',
            'D:\xampp\php\php.exe'
        )
        $php = $xamppPhpCandidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
        if (-not $php) {
            $php = (Get-Command php -ErrorAction Stop).Source
        }
    }

    $phpCapabilitiesJson = & $php -r "echo json_encode(['version' => PHP_VERSION, 'pdo_mysql' => in_array('mysql', PDO::getAvailableDrivers(), true), 'mbstring' => extension_loaded('mbstring'), 'fileinfo' => extension_loaded('fileinfo')]);"
    if ($LASTEXITCODE -ne 0 -or -not $phpCapabilitiesJson) {
        throw "Could not inspect PHP executable at '$php'. Set RICEUP_PHP to the PHP bundled with XAMPP."
    }
    $phpCapabilities = $phpCapabilitiesJson | ConvertFrom-Json
    $missingExtensions = @()
    if ($phpCapabilities.pdo_mysql -ne $true) { $missingExtensions += 'pdo_mysql' }
    if ($phpCapabilities.mbstring -ne $true) { $missingExtensions += 'mbstring' }
    if ($phpCapabilities.fileinfo -ne $true) { $missingExtensions += 'fileinfo' }
    if ([version] $phpCapabilities.version -lt [version] '8.1' -or $missingExtensions.Count -gt 0) {
        $missing = if ($missingExtensions.Count -gt 0) { $missingExtensions -join ', ' } else { 'PHP 8.1 or newer' }
        throw "The selected PHP '$php' does not meet requirements. Missing: $missing. Set RICEUP_PHP to the PHP bundled with XAMPP."
    }

    $scriptPath = Join-Path $PSScriptRoot 'create-admin.php'
    $processInfo = New-Object System.Diagnostics.ProcessStartInfo
    $processInfo.FileName = $php
    $processInfo.Arguments = '"' + $scriptPath.Replace('"', '\"') + '" --username=' + $username
    $processInfo.WorkingDirectory = Split-Path -Parent $PSScriptRoot
    $processInfo.RedirectStandardInput = $true
    $processInfo.RedirectStandardOutput = $true
    $processInfo.RedirectStandardError = $true
    $processInfo.UseShellExecute = $false
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $processInfo
    [void] $process.Start()
    $process.StandardInput.WriteLine($plainPassword)
    $process.StandardInput.Close()
    $output = $process.StandardOutput.ReadToEnd()
    $errorOutput = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($output) { Write-Host $output.TrimEnd() }
    if ($errorOutput) { Write-Error $errorOutput.TrimEnd() }
    if ($process.ExitCode -ne 0) { throw 'Setup admin gagal. Periksa konfigurasi MySQL dan pesan di atas.' }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($firstPtr)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secondPtr)
    $plainPassword = $null
    $confirmation = $null
    if ($process) { $process.Dispose() }
}
