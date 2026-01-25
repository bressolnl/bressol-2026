& .\tools\lint-theme.ps1
if ($LASTEXITCODE -ne 0) {
    "EXITCODE: 1"
    exit 1
}

& .\tools\lint-core.ps1
if ($LASTEXITCODE -ne 0) {
    "EXITCODE: 1"
    exit 1
}

"OK: All PHP lint checks passed."
"EXITCODE: 0"
exit 0
