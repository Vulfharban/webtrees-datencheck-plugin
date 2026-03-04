$version = "v1.6.2"
$zipName = "webtrees-datencheck-$version.zip"
$sourceDir = (Get-Item .).FullName
$tempDir = Join-Path $env:TEMP "webtrees-datencheck-build"
$targetDir = Join-Path $tempDir "webtrees-datencheck-plugin"

# Clean up previous builds
if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }
if (Test-Path (Join-Path $sourceDir $zipName)) { Remove-Item -Force (Join-Path $sourceDir $zipName) }

# Create directory structure
New-Item -ItemType Directory -Force -Path $targetDir | Out-Null

# Files and folders to include
$includes = @(
    "module.php",
    "composer.json",
    "LICENSE",
    "README.md",
    "latest-version.txt",
    "resources",
    "src"
)

Write-Host "Building $zipName..." -ForegroundColor Cyan

foreach ($item in $includes) {
    $path = Join-Path $sourceDir $item
    if (Test-Path $path) {
        Write-Host "Copying $item..."
        if (Test-Path $path -PathType Container) {
            # Folder: ensure we keep the hierarchy
            $destDir = Join-Path $targetDir $item
            New-Item -ItemType Directory -Force -Path $destDir | Out-Null
            Copy-Item -Path "$path\*" -Destination $destDir -Recurse -Force
        }
        else {
            # Single file: copy to build folder
            Copy-Item -Path $path -Destination $targetDir -Force
        }
    }
    else {
        Write-Warning "File not found: $item"
    }
}

# Create Zip using .NET to ensure forward slashes (cross-platform compatibility)
Write-Host "Creating archive with forward slashes..."
Add-Type -AssemblyName "System.IO.Compression"
Add-Type -AssemblyName "System.IO.Compression.FileSystem"

$zipPath = Join-Path $sourceDir $zipName
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

# Recursively get all files but NOT directories
$files = Get-ChildItem -Path $targetDir -Recurse | Where-Object { -not $_.PSIsContainer }

# Normalize the prefix for reliable removal - using tempDir to include the plugin folder in the zip
$tempFull = (Get-Item $tempDir).FullName
$prefix = $tempFull.TrimEnd("\") + "\"

foreach ($file in $files) {
    # Normalize $file path too
    $fullPath = (Get-Item $file.FullName).FullName
    
    # Calculate the relative path within the zip archive
    # Use -replace with case-insensitive regex for maximum safety
    $escapedPrefix = [regex]::Escape($prefix)
    $entryName = ($fullPath -replace "^$escapedPrefix", "").Replace("\", "/")
    
    # Add to zip
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $fullPath, $entryName)
}

$archive.Dispose()

# Cleanup temp build materials
if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }

Write-Host "Done! created $zipName" -ForegroundColor Green
