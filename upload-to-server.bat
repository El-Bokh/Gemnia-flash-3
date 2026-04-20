@echo off
title Upload to Production Server
setlocal EnableExtensions
set "SCRIPT_DIR=%~dp0"
echo.
echo  This will BUILD + UPLOAD + DEPLOY to the production server.
echo  Press Ctrl+C to cancel.
echo.
pause
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%upload-to-server.ps1"
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Deployment failed! Check the output above.
    pause
    exit /b %ERRORLEVEL%
)
echo.
echo Done! Press any key to close.
pause >nul
exit /b 0
