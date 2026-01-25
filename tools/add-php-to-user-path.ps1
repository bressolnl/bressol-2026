$phpExe = & .\tools\php-path.ps1
if ($LASTEXITCODE -ne 0 -or -not $phpExe) {
    Write-Error 'No se pudo localizar php.exe usando tools/php-path.ps1.'
    exit 1
}

$phpDir = Split-Path $phpExe -Parent
$current = [Environment]::GetEnvironmentVariable("Path", "User")

$parts = @()
if ($current) {
    $parts = $current -split ';' | Where-Object { $_ -ne '' }
}

if ($parts -notcontains $phpDir) {
    $new = ($parts + $phpDir) -join ';'
    [Environment]::SetEnvironmentVariable("Path", $new, "User")
    Write-Output "USER PATH (before): $current"
    Write-Output "USER PATH (after): $new"
} else {
    Write-Output "USER PATH (unchanged): $current"
}

exit 0
