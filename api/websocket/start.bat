@echo off
echo Starting PixelForge WebSocket Server...
echo.

REM Check if PHP is in PATH
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo PHP is not in your PATH.
    echo Please add PHP to your PATH or run from XAMPP.
    echo.
    echo Example: C:\xampp\php\php.exe server.php
    pause
    exit /b 1
)

REM Start the WebSocket server
php server.php %*

echo.
echo Server stopped.
pause
