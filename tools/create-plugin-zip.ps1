$ProjectRoot = 'C:\Users\nomur\work\wp-plugin-lifegence-aitranslator'
$DistDir = Join-Path $ProjectRoot 'dist'
$TempDir = Join-Path $DistDir 'temp\lifegence-aitranslator'
$OutputZip = Join-Path $DistDir 'lifegence-aitranslator.zip'

# Clean up
if (Test-Path $DistDir) { Remove-Item $DistDir -Recurse -Force }
New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

# Copy directories
Copy-Item (Join-Path $ProjectRoot 'admin') (Join-Path $TempDir 'admin') -Recurse
Copy-Item (Join-Path $ProjectRoot 'assets') (Join-Path $TempDir 'assets') -Recurse
Copy-Item (Join-Path $ProjectRoot 'includes') (Join-Path $TempDir 'includes') -Recurse
Copy-Item (Join-Path $ProjectRoot 'languages') (Join-Path $TempDir 'languages') -Recurse

# Copy files
Copy-Item (Join-Path $ProjectRoot 'lifegence-aitranslator.php') $TempDir
Copy-Item (Join-Path $ProjectRoot 'readme.txt') $TempDir
Copy-Item (Join-Path $ProjectRoot 'LICENSE') $TempDir
Copy-Item (Join-Path $ProjectRoot 'README.md') $TempDir

# Create ZIP
Compress-Archive -Path (Join-Path $DistDir 'temp\lifegence-aitranslator') -DestinationPath $OutputZip -Force

# Clean temp
Remove-Item (Join-Path $DistDir 'temp') -Recurse -Force

# Verify
if (Test-Path $OutputZip) {
    $size = (Get-Item $OutputZip).Length / 1KB
    Write-Host "ZIP created successfully: $([math]::Round($size, 1)) KB"
    Write-Host $OutputZip
} else {
    Write-Host 'ERROR: ZIP creation failed'
    exit 1
}
