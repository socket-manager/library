#!/bin/sh

# このスクリプトは ffi/windows/ 内で実行することを想定しています。
# io_core_win.c をビルドし、プロジェクトの
# src/Framework/driver/io_core_win.so を上書きします。

set -e

SRC="io_core_win.c"
OUT="io_core_win.dll"
TARGET="../../src/FrameWork/driver/${OUT}"

echo "Building Linux FFI driver..."

# gcc が無い環境でもエラー内容が分かりやすいようにチェック
if ! command -v gcc >/dev/null 2>&1; then
    echo "Error: gcc が見つかりません。ビルドツールをインストールしてください。"
    exit 1
fi

gcc -shared -o "${OUT}" "${SRC}" -lws2_32

echo "Replacing driver binary..."
cp -f "${OUT}" "${TARGET}"

echo "Done."
echo "→ ${TARGET} に最新の ${OUT} を配置しました。"
