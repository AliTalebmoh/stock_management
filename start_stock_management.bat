@echo off
echo Starting Stock Management System...

:: Set variables
set PORT=8000
set PROJECT_PATH=%~dp0
set BROWSER_URL=http://localhost:%PORT%/public/

:: Check if PHP is installed and in PATH
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo PHP not found in PATH. Using PHP from project directory if available...
    set PHP_PATH=%PROJECT_PATH%php\php.exe
    if not exist "%PHP_PATH%" (
        echo Error: PHP not found. Please install PHP or place it in a folder named 'php' in this directory.
        pause
        exit /b 1
    )
) else (
    set PHP_PATH=php
)

:: Start PHP server in background
start /b cmd /c "%PHP_PATH% -S localhost:%PORT% -t %PROJECT_PATH%"
echo Server started at %BROWSER_URL%

:: Wait for server to start
timeout /t 2 > nul

:: Open the default browser
start "" "%BROWSER_URL%"

echo Stock Management System is now running.
echo Please do not close this window while using the application.
echo To stop the server, close this window.
pause > nul 