& .\tools\lint-theme.ps1
if ($LASTEXITCODE -ne 0) {
    exit 1
}

& .\tools\lint-core.ps1
if ($LASTEXITCODE -ne 0) {
    exit 1
}

$php = & .\tools\php-path.ps1
if ($LASTEXITCODE -ne 0 -or -not $php) {
    Write-Error 'No se pudo localizar php.exe usando tools/php-path.ps1.'
    exit 1
}

$plugin = "wp-content\plugins\bressol-core"
$files = Get-ChildItem -Path $plugin -Filter *.php -Recurse -File
$errors = @()

foreach ($f in $files) {
    $out = & $php -l $f.FullName 2>&1
    if ($out -notmatch "No syntax errors detected") {
        $errors += "----`nFILE: $($f.FullName)`n$out`n"
    }
}

if ($errors.Count -eq 0) {
    "OK: No syntax errors detected in bressol-core PHP files."
    exit 0
} else {
    "ERROR: PHP lint failures in bressol-core."
    $errors
    exit 1
}
