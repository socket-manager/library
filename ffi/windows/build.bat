@echo off
REM このスクリプトは ffi/windows/ 内で実行することを想定しています。
REM io_core_win.c をビルドし、プロジェクトの
REM src/Framework/driver/io_core_win.dll を上書きします。

set SRC=io_core_win.c
set OUT=io_core_win.dll
set TARGET=..\..\src\Framework\driver\%OUT%

echo Building Windows FFI driver...
gcc -shared -o %OUT% %SRC% -lws2_32

echo Replacing driver binary...
copy /Y %OUT% %TARGET%

echo Done.
echo => %TARGET% に最新の %OUT% を配置しました。
