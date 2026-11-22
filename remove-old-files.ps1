# Deletes the files this update replaces. Run once, from C:\Projects\jms-it-ticketing:
#   powershell -ExecutionPolicy Bypass -File .\remove-old-files.ps1
$old = @(
  'resources\views\branding\edit.blade.php',
  'tests\Feature\JmsAdminDispatchTest.php',
  'tests\Feature\JmsRequestTest.php',
  'tests\Feature\JmsSupportTeamTest.php',
  'tests\Feature\JmsTeamTest.php'
)
foreach ($f in $old) {
  if (Test-Path $f) { Remove-Item $f -Force; Write-Host "Removed $f" } else { Write-Host "Already gone: $f" }
}
if (Test-Path 'resources\views\branding' -PathType Container) {
  if (-not (Get-ChildItem 'resources\views\branding')) { Remove-Item 'resources\views\branding' }
}
php artisan optimize:clear
Write-Host "Done."
