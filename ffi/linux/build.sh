#!/bin/sh

# このスクリプトは ffi/linux/ 内で実行することを想定しています。
# libio_core_linux.c をビルドし、プロジェクトの
# src/Framework/driver/libio_core_linux.so を上書きします。

set -e

SRC="libio_core_linux.c"
OUT="libio_core_linux.so"
TARGET="../../src/FrameWork/driver/${OUT}"

echo "Building Linux FFI driver..."

# gcc が無い環境でもエラー内容が分かりやすいようにチェック
if ! command -v gcc >/dev/null 2>&1; then
    echo "Error: gcc が見つかりません。ビルドツールをインストールしてください。"
    exit 1
fi

gcc -shared -fPIC -o "${OUT}" "${SRC}"

echo "Replacing driver binary..."
cp -f "${OUT}" "${TARGET}"

echo "Done."
echo "→ ${TARGET} に最新の ${OUT} を配置しました。"
