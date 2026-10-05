$ErrorActionPreference = "Stop"
$projectRoot = Split-Path $PSScriptRoot -Parent
$envPath = Join-Path $projectRoot ".env"
if (-not (Test-Path $envPath)) {
    Copy-Item (Join-Path $projectRoot ".env.example") $envPath
}
$content = [System.IO.File]::ReadAllText($envPath)
$settings = @{
    DB_CONNECTION = "mysql"
    DB_DATABASE = "fsp_test2"
    SESSION_DRIVER = "file"
    CACHE_STORE = "file"
    QUEUE_CONNECTION = "sync"
}
foreach ($key in $settings.Keys) {
    $line = "$key=$($settings[$key])"
    $pattern = "(?m)^" + [regex]::Escape($key) + "=.*$"
    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, $line)
    } else {
        $content += [Environment]::NewLine + $line
    }
}
# Keep existing host, port, username and password.
$defaults = @{ DB_HOST = "127.0.0.1"; DB_PORT = "3306"; DB_USERNAME = "root"; DB_PASSWORD = "" }
foreach ($key in $defaults.Keys) {
    if (-not [regex]::IsMatch($content, "(?m)^" + $key + "=")) {
        $content += [Environment]::NewLine + "$key=$($defaults[$key])"
    }
}
[System.IO.File]::WriteAllText($envPath, $content, [System.Text.UTF8Encoding]::new($false))
Push-Location $projectRoot
try {
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw "Laravel config:clear failed." }
} finally {
    Pop-Location
}
Write-Host "Configured MySQL database fsp_test2. Check DB credentials in .env."
