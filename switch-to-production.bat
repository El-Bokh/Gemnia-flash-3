@echo off
title Switch to PRODUCTION
setlocal EnableExtensions
set "SCRIPT_DIR=%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%switch-environment.ps1" -Mode production
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Failed to switch to production mode!
    pause
    exit /b %ERRORLEVEL%
)
pause
exit /b 0