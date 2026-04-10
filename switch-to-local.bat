@echo off
title Switch to LOCAL Development
setlocal EnableExtensions
set "SCRIPT_DIR=%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%switch-environment.ps1" -Mode local
if %ERRORLEVEL% neq 0 (
    echo.
    echo [ERROR] Failed to switch to local mode!
    pause
    exit /b %ERRORLEVEL%
)
pause
exit /b 0