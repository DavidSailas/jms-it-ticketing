# setup-test.ps1
# Sets up a TEST copy of JMS One IT Ticketing on this laptop (Laravel Herd + MySQL/DBngin).
# It uses its own database (jms_it_ticketing_test), writes emails to the log instead of sending them,
# and fills the app with demo data. It never touches a live database.
#
# Run in PowerShell from C:\Projects\jms-it-ticketing:
#     powershell -ExecutionPolicy Bypass -File .\setup-test.ps1

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

$TestDb  = 'jms_it_ticketing_test'
$DbHost  = '127.0.0.1'
$DbPort  = '3306'
$DbUser  = 'root'
$DbPass  = ''
$AppUrl  = 'http://jms-it-ticketing.test'

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }

function Set-EnvValue([string]$Text, [string]$Key, [string]$Value) {
    $pattern = "(?m)^[#\s]*$([regex]::Escape($Key))=.*$"
    $line = "$Key=$Value"
    if ($Text -match $pattern) { return [regex]::Replace($Text, $pattern, $line) }
    return $Text.TrimEnd() + "`r`n$line`r`n"
}

foreach ($tool in 'php', 'composer', 'npm') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        throw "'$tool' was not found. Close and reopen PowerShell (Herd adds php/composer), and install Node.js for npm."
    }
}

Step 'Installing PHP packages'
if (-not (Test-Path 'vendor')) { composer install } else { Write-Host 'vendor folder already exists, skipping.' }

Step 'Preparing .env for TEST'
if (Test-Path '.env') {
    Copy-Item '.env' ".env.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Write-Host 'Existing .env backed up.'
} else {
    Copy-Item '.env.example' '.env'
}

$env_ = [System.IO.File]::ReadAllText((Join-Path $PWD '.env'))
$env_ = Set-EnvValue $env_ 'APP_NAME'      '"JMS One IT (TEST)"'
$env_ = Set-EnvValue $env_ 'APP_ENV'       'local'
$env_ = Set-EnvValue $env_ 'APP_DEBUG'     'true'
$env_ = Set-EnvValue $env_ 'APP_URL'       $AppUrl
$env_ = Set-EnvValue $env_ 'DB_CONNECTION' 'mysql'
$env_ = Set-EnvValue $env_ 'DB_HOST'       $DbHost
$env_ = Set-EnvValue $env_ 'DB_PORT'       $DbPort
$env_ = Set-EnvValue $env_ 'DB_DATABASE'   $TestDb
$env_ = Set-EnvValue $env_ 'DB_USERNAME'   $DbUser
$env_ = Set-EnvValue $env_ 'DB_PASSWORD'   $DbPass
$env_ = Set-EnvValue $env_ 'MAIL_MAILER'   'log'      # emails go to storage/logs, nothing is really sent
[System.IO.File]::WriteAllText((Join-Path $PWD '.env'), $env_, (New-Object System.Text.UTF8Encoding($false)))

php artisan key:generate --force

Step "Creating empty database '$TestDb' (MySQL must be running in DBngin)"
$pdoCode = "`$p = new PDO('mysql:host=$DbHost;port=$DbPort', '$DbUser', '$DbPass'); `$p->exec('CREATE DATABASE IF NOT EXISTS ``$TestDb`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); echo 'ok';"
php -r $pdoCode
if ($LASTEXITCODE -ne 0) { throw 'Could not reach MySQL. Start it in DBngin and run this script again.' }

Step 'Building tables and loading demo data (test database only)'
php artisan optimize:clear
php artisan migrate:fresh --force
php artisan db:seed --class=DemoDataSeeder --force

Step 'Building front-end assets'
npm install
npm run build

Write-Host "`nDone. Open $AppUrl" -ForegroundColor Green
Write-Host 'Sign in with  superadmin@jmsoneit.com  /  P@ssw0rd123'
Write-Host 'More demo accounts: grace@demo.test, marco@demo.test, liza@demo.test, carlo@demo.test (same password).'
Write-Host 'Run the automated tests any time with:  php artisan test   (in-memory SQLite, never touches MySQL)'
