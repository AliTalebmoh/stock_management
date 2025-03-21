@echo off
color 0A
title Stock Management System Installation

echo ==========================================
echo    STOCK MANAGEMENT SYSTEM INSTALLATION
echo ==========================================
echo.
echo This script will help you set up the Stock Management System.
echo.
echo Steps:
echo 1. Verify or install required software
echo 2. Set up the database
echo 3. Create a desktop shortcut
echo.
pause

cls
echo ==========================================
echo    STEP 1: VERIFY REQUIRED SOFTWARE
echo ==========================================
echo.

:: Check if PHP is installed
echo Checking for PHP...
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo PHP is not found in your system PATH.
    echo.
    echo You have two options:
    echo 1. Download and install PHP from https://windows.php.net/download/
    echo 2. Place PHP files in a folder named "php" in this directory
    echo.
    echo After completing one of these steps, run this installation again.
    echo.
    set /p PHP_CHOICE=Do you want to continue without PHP for now? (Y/N): 
    if /i not "%PHP_CHOICE%"=="Y" exit /b 1
) else (
    echo PHP is installed. Great!
    php -v
)

echo.
:: Check if MySQL is installed
echo Checking for MySQL...
where mysql >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo MySQL is not found in your system PATH.
    echo.
    echo Please download and install MySQL from https://dev.mysql.com/downloads/
    echo After installation, add MySQL to your system PATH or run the MySQL installer again and select "Reconfigure".
    echo.
    set /p MYSQL_CHOICE=Do you want to continue without MySQL for now? (Y/N): 
    if /i not "%MYSQL_CHOICE%"=="Y" exit /b 1
) else (
    echo MySQL is installed. Great!
    mysql --version
)

echo.
echo Press any key to continue to database setup...
pause >nul

cls
echo ==========================================
echo    STEP 2: DATABASE SETUP
echo ==========================================
echo.

set /p DB_SETUP=Do you want to set up the database now? (Y/N): 
if /i "%DB_SETUP%"=="Y" (
    call setup_database.bat
)

cls
echo ==========================================
echo    STEP 3: CREATE DESKTOP SHORTCUT
echo ==========================================
echo.

set /p CREATE_SHORTCUT=Do you want to create a desktop shortcut? (Y/N): 
if /i "%CREATE_SHORTCUT%"=="Y" (
    call create_desktop_shortcut.bat
)

cls
echo ==========================================
echo    INSTALLATION COMPLETE
echo ==========================================
echo.
echo The Stock Management System has been installed successfully!
echo.
echo To start the application:
echo - Use the desktop shortcut (if created)
echo - OR double-click "start_stock_management.bat" in this folder
echo.
echo For more information, please read the README.txt file.
echo.
echo Thank you for installing the Stock Management System.
echo.
set /p START_NOW=Do you want to start the application now? (Y/N): 
if /i "%START_NOW%"=="Y" (
    start start_stock_management.bat
)

echo.
echo Goodbye!
timeout /t 3 > nul 