$primary = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.NTS.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
if (Test-Path -Path $primary) {
    Write-Output $primary
    exit 0
}

$packagesRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
if (Test-Path -Path $packagesRoot) {
    $found = Get-ChildItem -Path $packagesRoot -Filter php.exe -Recurse -File -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($found) {
        Write-Output $found.FullName
        exit 0
    }
}

if (Get-Command where.exe -ErrorAction SilentlyContinue) {
    $where = & where.exe php 2>$null | Select-Object -First 1
    if ($where) {
        Write-Output $where
        exit 0
    }
}

Write-Error 'php.exe no encontrado en las rutas configuradas.'
exit 1
