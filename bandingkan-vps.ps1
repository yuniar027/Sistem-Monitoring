param([string]$Manifest = "$env:TEMP\manifest-vps.txt")

function Md5Bytes([byte[]]$bytes) {
    $m = [System.Security.Cryptography.MD5]::Create()
    -join ($m.ComputeHash($bytes) | ForEach-Object { $_.ToString('x2') })
}

if (-not (Test-Path $Manifest)) { Write-Host "Manifest tidak ditemukan: $Manifest"; exit 1 }

$root = (Get-Location).Path
$vps = @{}
Get-Content $Manifest | ForEach-Object {
    $a = $_ -split ' ', 3
    if ($a.Count -eq 3) { $vps[$a[2]] = @{ raw = $a[0]; norm = $a[1] } }
}

$sama = 0; $akhiranBaris = @(); $bedaIsi = @(); $hanyaVps = @()
foreach ($p in $vps.Keys) {
    $f = Join-Path $root ($p -replace '/', '\')
    if (-not (Test-Path $f)) { $hanyaVps += $p; continue }
    $b = [System.IO.File]::ReadAllBytes($f)
    if ((Md5Bytes $b) -eq $vps[$p].raw) { $sama++; continue }
    $n = [byte[]]($b | Where-Object { $_ -ne 13 })
    if ((Md5Bytes $n) -eq $vps[$p].norm) { $akhiranBaris += $p } else { $bedaIsi += $p }
}

$hanyaLokal = @()
foreach ($d in 'app','config','routes','resources','database','tests') {
    if (-not (Test-Path (Join-Path $root $d))) { continue }
    Get-ChildItem (Join-Path $root $d) -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($root.Length + 1) -replace '\\', '/'
        if (-not $vps.ContainsKey($rel)) { $hanyaLokal += $rel }
    }
}

$out = @()
$out += "SAMA PERSIS            : $sama"
$out += "BEDA AKHIRAN BARIS SAJA: $($akhiranBaris.Count)"
$out += "BEDA ISI                : $($bedaIsi.Count)";           $out += ($bedaIsi | Sort-Object | ForEach-Object { "   $_" })
$out += "HANYA DI VPS            : $($hanyaVps.Count)";          $out += ($hanyaVps | Sort-Object | ForEach-Object { "   $_" })
$out += "HANYA DI LOKAL          : $($hanyaLokal.Count)";        $out += ($hanyaLokal | Sort-Object | ForEach-Object { "   $_" })
$out | Tee-Object -FilePath "$root\hasil-banding.txt"
Write-Host "`nHasil juga disimpan di hasil-banding.txt (jangan di-commit)."