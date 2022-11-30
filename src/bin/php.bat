@REM ***********************************************************
@REM *
@REM * Helper to invoke PHP
@REM * 
@REM ***********************************************************

@echo off

set ERROR_CODE=0

:init
@REM Decide how to startup depending on the version of windows

@REM -- Win98ME
if NOT "%OS%"=="Windows_NT" goto Win9xArg

@REM set local scope for the variables with windows NT shell
if "%OS%"=="Windows_NT" @setlocal

@REM -- 4NT shell
if "%eval[2+2]" == "4" goto 4NTArgs

@REM -- Regular WinNT shell
set CMD_LINE_ARGS=%*
goto WinNTGetScriptDir

@REM The 4NT Shell from jp software
:4NTArgs
set CMD_LINE_ARGS=%$
goto WinNTGetScriptDir

:Win9xArg
@REM Slurp the command line arguments.  This loop allows for an unlimited number
@REM of arguments (up to the command line limit, anyway).
set CMD_LINE_ARGS=
:Win9xApp
if %1a==a goto Win9xGetScriptDir
set CMD_LINE_ARGS=%CMD_LINE_ARGS% %1
shift
goto Win9xApp

:Win9xGetScriptDir
%0\
cd %0
set BASEDIR=%CD%
goto runphp

:WinNTGetScriptDir
set BASEDIR=%~dp0
IF %BASEDIR:~-1%==\ SET BASEDIR=%BASEDIR:~0,-1%
set BASEDIR=%BASEDIR%

:runphp

@REM find the php executable...

set php=Z:\web_clients\precision_efi\xampp\php\php.exe
set cfg=Z:\web_clients\precision_efi\xampp\php\php.ini
set ext=Z:\web_clients\precision_efi\xampp\php\ext

if "%php%" == "" (
  echo "Can not find PHP executable."
  goto :error
)

@REM run the command...

:runcmd
set PATHEXT=%PATHEXT%;.PHP

cmd.exe /C %php% -c %cfg% -d extension_dir=%ext% %CMD_LINE_ARGS%
if ERRORLEVEL 1 goto :error

@REM everything is ok
goto :EOF

@REM our die() function

:error
echo Failed with error #%errorlevel%.
