$version = "v1.2.2"
$zipName = "webtrees-datencheck-$version.zip"
$sourceDir = Get-Location
$tempDir = Join-Path $env:TEMP "webtrees-datencheck-build"
$targetDir = Join-Path $tempDir "webtrees-datencheck-plugin"

# Clean up previous builds
if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }
if (Test-Path $zipName) { Remove-Item -Force $zipName }

# Create directory structure
New-Item -ItemType Directory -Force -Path $targetDir | Out-Null

# Files and folders to include
$includes = @(
    "module.php",
    "composer.json",
    "LICENSE",
    "README.md",
    "resources",
    "src"
)

Write-Host "Building $zipName..." -ForegroundColor Cyan

foreach ($item in $includes) {
    $path = Join-Path $sourceDir $item
    if (Test-Path $path) {
        Write-Host "Copying $item..."
        Copy-Item -Recurse -Path $path -Destination $targetDir
    } else {
        Write-Warning "File not found: $item"
    }
}

# Create Zip
Write-Host "Creating archive..."
Compress-Archive -Path "$targetDir" -DestinationPath "$sourceDir\$zipName"

# Cleanup
Remove-Item -Recurse -Force $tempDir

Write-Host "Done! created $zipName" -ForegroundColor Green
