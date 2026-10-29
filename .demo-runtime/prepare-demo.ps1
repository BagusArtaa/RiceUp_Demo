$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$php = 'D:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php -PathType Leaf)) { throw 'XAMPP PHP executable is missing.' }
if (Test-Path -LiteralPath (Join-Path $projectRoot 'config\local.php')) { throw 'config/local.php already exists; refusing to overwrite it.' }

Write-Host 'Create the local app database password (20-200 characters). It will be saved only in ignored config/local.php and will not be echoed.'
$securePassword = Read-Host 'Local database password for rice_up_demo' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
$databasePassword = $null
try {
    $databasePassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    if ($databasePassword.Length -lt 20 -or $databasePassword.Length -gt 200) { throw 'The local database password must be between 20 and 200 characters.' }
    $env:RICEUP_LOCAL_DB_PASSWORD = $databasePassword
    $phpCode = @'
$pdo = new PDO("mysql:host=127.0.0.1;port=3306;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pw = getenv("RICEUP_LOCAL_DB_PASSWORD");
$schema = $pdo->prepare("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?");
$schema->execute(["rice_up"]);
if ((int)$schema->fetchColumn() !== 1) { fwrite(STDERR, "The rice_up schema is missing; refusing to continue.\n"); exit(3); }
if (file_exists("config/local.php")) { fwrite(STDERR, "config/local.php already exists; refusing to overwrite it.\n"); exit(4); }
$account = $pdo->prepare("SELECT COUNT(*) FROM mysql.user WHERE User=? AND Host=?");
$account->execute(["rice_up_demo", "127.0.0.1"]);
if ((int)$account->fetchColumn() !== 0) { fwrite(STDERR, "The rice_up_demo DB account already exists; refusing to reset it.\n"); exit(5); }
try {
    $pdo->exec("CREATE USER 'rice_up_demo'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($pw));
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON rice_up.* TO 'rice_up_demo'@'127.0.0.1'");
    $app = new PDO("mysql:host=127.0.0.1;port=3306;dbname=rice_up;charset=utf8mb4", "rice_up_demo", $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $adminCount = (int)$app->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $config = ["base_url"=>"/rice-up/public", "app_key"=>bin2hex(random_bytes(32)), "db_host"=>"127.0.0.1", "db_name"=>"rice_up", "db_user"=>"rice_up_demo", "db_password"=>$pw, "delivery_fee"=>5000];
    $source = "<?php\n\nreturn ".var_export($config, true).";\n";
    if (file_put_contents("config/local.php", $source, LOCK_EX) === false) { throw new RuntimeException("local config write failed"); }
    echo "local_config_ready=1;admin_count=".$adminCount.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "Local DB setup failed; password and internal exception details are suppressed.\n");
    exit(10);
}
'@
    $phpScript = Join-Path $PSScriptRoot ('prepare-demo-db-' + [guid]::NewGuid().ToString('N') + '.php')
    try {
        [System.IO.File]::WriteAllText($phpScript, "<?php`r`n$phpCode", [System.Text.UTF8Encoding]::new($false))
        $result = & $php $phpScript
        if ($LASTEXITCODE -ne 0) { if ($result) { $result | ForEach-Object { Write-Host $_ } }; throw 'Local DB configuration could not be prepared; no password was echoed.' }
        $result | ForEach-Object { Write-Host $_ }
    } finally {
        if (Test-Path -LiteralPath $phpScript) { Remove-Item -LiteralPath $phpScript -Force -ErrorAction SilentlyContinue }
    }
} finally {
    Remove-Item Env:RICEUP_LOCAL_DB_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $databasePassword = $null
}

if (($result -join [Environment]::NewLine) -match 'admin_count=0') {
    Write-Host 'No admin exists yet. The documented secure CLI setup will now ask for the initial admin username and masked password entries.'
    & (Join-Path $projectRoot 'tools\create-admin.ps1')
    if ($LASTEXITCODE -ne 0) { throw 'Initial admin setup failed; no password was echoed.' }
} else {
    Write-Host 'An existing admin was detected; the setup script did not create or replace one.'
}
Write-Host 'Local demo configuration and admin setup finished.'
