@echo off
echo Stock Management System - Database Setup
echo =======================================
echo.

:: Prompt for MySQL credentials
set /p MYSQL_USER=Enter MySQL username (default: root): 
if "%MYSQL_USER%"=="" set MYSQL_USER=root

set /p MYSQL_PASS=Enter MySQL password: 

:: Set paths
set PROJECT_PATH=%~dp0
set SCHEMA_FILE=%PROJECT_PATH%database\schema.sql
set SAMPLE_DATA=%PROJECT_PATH%database\sample_data.sql

:: Check if files exist
if not exist "%SCHEMA_FILE%" (
    echo Error: Schema file not found at %SCHEMA_FILE%
    echo Please ensure the database files are in the correct location.
    pause
    exit /b 1
)

echo.
echo Setting up database...

:: Execute the schema file
mysql -u %MYSQL_USER% -p%MYSQL_PASS% < "%SCHEMA_FILE%"
if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to create database schema.
    echo Please check your MySQL credentials and try again.
    pause
    exit /b 1
)

:: Ask if sample data should be imported
echo.
echo Database schema created successfully.
set /p IMPORT_SAMPLE=Do you want to import sample data? (Y/N): 

if /i "%IMPORT_SAMPLE%"=="Y" (
    if exist "%SAMPLE_DATA%" (
        echo Importing sample data...
        mysql -u %MYSQL_USER% -p%MYSQL_PASS% stock_management < "%SAMPLE_DATA%"
        if %ERRORLEVEL% NEQ 0 (
            echo Error: Failed to import sample data.
            echo Database structure has been created, but without sample data.
        ) else (
            echo Sample data imported successfully.
        )
    ) else (
        echo Sample data file not found at %SAMPLE_DATA%
        echo Database structure has been created, but without sample data.
    )
)

echo.
echo Database setup complete!
echo You can now run the application by double-clicking "start_stock_management.bat"
echo.
pause 