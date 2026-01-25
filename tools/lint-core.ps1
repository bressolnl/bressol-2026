$php = & .\tools\php-path.ps1
if ($LASTEXITCODE -ne 0 -or -not $php) {
    Write-Error 'No se pudo localizar php.exe usando tools/php-path.ps1.'
    exit 1
}

$src = "wp-content\plugins\bressol-core\src"
$files = Get-ChildItem -Path $src -Filter *.php -Recurse -File
$errors = @()

foreach ($f in $files) {
    $out = & $php -l $f.FullName 2>&1
    if ($out -notmatch "No syntax errors detected") {
        $errors += "----`nFILE: $($f.FullName)`n$out`n"
    }
}

if ($errors.Count -eq 0) {
    "OK: No syntax errors detected in bressol-core/src PHP files."
    exit 0
} else {
    "ERROR: PHP lint failures in core."
    $errors
    exit 1
}
