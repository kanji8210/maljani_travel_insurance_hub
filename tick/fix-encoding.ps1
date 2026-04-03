$cp1252 = [System.Text.Encoding]::GetEncoding(1252)
$utf8   = New-Object System.Text.UTF8Encoding $false

function Garbled([int]$cp) {
    $bytes = $utf8.GetBytes([char]::ConvertFromUtf32($cp))
    return $cp1252.GetString($bytes)
}

$reps = [ordered]@{
    (Garbled(0x2019)) = [char]::ConvertFromUtf32(0x2019)
    (Garbled(0x2018)) = [char]::ConvertFromUtf32(0x2018)
    (Garbled(0x201C)) = [char]::ConvertFromUtf32(0x201C)
    (Garbled(0x201D)) = [char]::ConvertFromUtf32(0x201D)
    (Garbled(0x2014)) = [char]::ConvertFromUtf32(0x2014)
    (Garbled(0x2013)) = [char]::ConvertFromUtf32(0x2013)
    (Garbled(0x2022)) = [char]::ConvertFromUtf32(0x2022)
    (Garbled(0x2026)) = [char]::ConvertFromUtf32(0x2026)
    (Garbled(0x2192)) = [char]::ConvertFromUtf32(0x2192)
    (Garbled(0x2190)) = [char]::ConvertFromUtf32(0x2190)
    (Garbled(0x2713)) = [char]::ConvertFromUtf32(0x2713)
    (Garbled(0x2708)) = [char]::ConvertFromUtf32(0x2708)
    (Garbled(0x00A9)) = [char]0x00A9
    (Garbled(0x00B7)) = [char]0x00B7
    (Garbled(0x00A3)) = [char]0x00A3
    (Garbled(0x00B0)) = [char]0x00B0
    (Garbled(0x00BD)) = [char]0x00BD
}

$dir   = "src"
$files = Get-ChildItem -Recurse -File -LiteralPath $dir -Include "*.jsx","*.js"

foreach ($f in $files) {
    $text = [System.IO.File]::ReadAllText($f.FullName, $utf8)
    $orig = $text
    foreach ($kv in $reps.GetEnumerator()) {
        if ($text.Contains($kv.Key)) { $text = $text.Replace($kv.Key, $kv.Value) }
    }
    if ($text -ne $orig) {
        [System.IO.File]::WriteAllText($f.FullName, $text, $utf8)
        Write-Host "Fixed: $($f.Name)"
    }
}
Write-Host "All done."
# (rest of old file removed)
