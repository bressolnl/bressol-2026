$php = & .\tools\php-path.ps1
if ($LASTEXITCODE -ne 0 -or -not $php) {
    Write-Error 'No se pudo localizar php.exe usando tools/php-path.ps1.'
    exit 1
}

$theme = "wp-content\themes\bressol-theme"
$files = Get-ChildItem -Path $theme -Filter *.php -Recurse -File
$errors = @()

foreach ($f in $files) {
    $out = & $php -l $f.FullName 2>&1
    if ($out -notmatch "No syntax errors detected") {
        $errors += "----`nFILE: $($f.FullName)`n$out`n"
    }
}

if ($errors.Count -eq 0) {
    "OK: No syntax errors detected in theme PHP files."
    exit 0
} else {
    "ERROR: PHP lint failures in theme."
    $errors
    exit 1
}
