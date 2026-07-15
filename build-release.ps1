<#
.SYNOPSIS
    Empacota uma nova versão do plugin Rokais Carrossel WP para publicar no GitHub Releases.

.DESCRIPTION
    - Atualiza a versão no cabeçalho do plugin, na constante SKPC_VERSION e no readme.txt.
    - Gera dist\rokais-carrossel-wp.zip (com a pasta rokais-carrossel-wp/ no topo e barras normais),
      pronto para ser anexado como asset da release no GitHub.

.EXAMPLE
    .\build-release.ps1 -Version 1.0.1
#>
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d+\.\d+\.\d+$')]
    [string]$Version
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Update-File($path, $pattern, $replacement) {
    $full = Join-Path $root $path
    $text = [System.IO.File]::ReadAllText($full)
    $new = [regex]::Replace($text, $pattern, $replacement)
    [System.IO.File]::WriteAllText($full, $new, $utf8NoBom)
    Write-Host ("  atualizado: {0}" -f $path)
}

Write-Host "1) Atualizando a versão para $Version ..."
Update-File 'sk-price-carousel.php' '(?m)^(\s*\*\s*Version:\s*).+$'          ('${1}' + $Version)
Update-File 'sk-price-carousel.php' "(define\(\s*'SKPC_VERSION',\s*')[^']*(')" ('${1}' + $Version + '${2}')
Update-File 'readme.txt'            '(?m)^(Stable tag:\s*).+$'                ('${1}' + $Version)

Write-Host "2) Empacotando dist\rokais-carrossel-wp.zip ..."
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$rid = Get-Random
$stage = Join-Path (Join-Path $env:TEMP ("skpc_rel_" + $rid)) "rokais-carrossel-wp"
New-Item -ItemType Directory -Force $stage | Out-Null

$excludeNames = @("ARQUIVOS EXTRAS", ".git", "dist", ".claude")
$excludeExt = @(".zip", ".ps1", ".md")
Get-ChildItem -Path $root -Force | Where-Object {
    $excludeNames -notcontains $_.Name -and $excludeExt -notcontains $_.Extension
} | ForEach-Object { Copy-Item -Path $_.FullName -Destination $stage -Recurse -Force }

$distDir = Join-Path $root "dist"
New-Item -ItemType Directory -Force $distDir | Out-Null
$zip = Join-Path $distDir "rokais-carrossel-wp.zip"

$fs = [System.IO.File]::Open($zip, [System.IO.FileMode]::Create)
$archive = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
$stageRoot = Split-Path $stage -Parent
Get-ChildItem -Path $stage -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($stageRoot.Length + 1) -replace '\\', '/'
    $entry = $archive.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
    $es = $entry.Open()
    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    $es.Write($bytes, 0, $bytes.Length)
    $es.Close()
}
$archive.Dispose(); $fs.Close()

$kb = [math]::Round((Get-Item $zip).Length / 1KB, 0)
Write-Host ""
Write-Host ("PRONTO! dist\rokais-carrossel-wp.zip ({0} KB), versao {1}." -f $kb, $Version) -ForegroundColor Green
Write-Host ""
Write-Host "Agora publique no GitHub:" -ForegroundColor Cyan
Write-Host "  git add -A"
Write-Host "  git commit -m `"Versao $Version`""
Write-Host "  git tag v$Version"
Write-Host "  git push origin main --tags"
Write-Host "  gh release create v$Version dist\rokais-carrossel-wp.zip --title `"v$Version`" --notes `"Novidades da versao $Version`""
Write-Host ""
Write-Host "Os sites dos clientes detectam a atualizacao em ate ~12h (ou na hora, em Painel > Atualizacoes > Verificar novamente)."
