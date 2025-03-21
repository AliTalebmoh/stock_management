@echo off
echo Creating desktop shortcut for Stock Management System...

set SHORTCUT_NAME=Stock Management System.lnk
set SHORTCUT_TARGET=%~dp0start_stock_management.bat
set ICON_LOCATION=%~dp0public\assets\images\logo.png
set DESKTOP_PATH=%USERPROFILE%\Desktop

:: Create VBS script to make the shortcut
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%TEMP%\CreateShortcut.vbs"
echo sLinkFile = "%DESKTOP_PATH%\%SHORTCUT_NAME%" >> "%TEMP%\CreateShortcut.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%TEMP%\CreateShortcut.vbs"
echo oLink.TargetPath = "%SHORTCUT_TARGET%" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.WorkingDirectory = "%~dp0" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.Description = "Start Stock Management System" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.IconLocation = "%ICON_LOCATION%" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.Save >> "%TEMP%\CreateShortcut.vbs"

:: Run the VBS script
cscript //nologo "%TEMP%\CreateShortcut.vbs"
del "%TEMP%\CreateShortcut.vbs"

echo Desktop shortcut created successfully!
echo You can now start the Stock Management System by double-clicking the shortcut on your desktop.
pause 