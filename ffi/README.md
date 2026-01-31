## **概要**

このディレクトリには、**FFI ベースの I/O ドライバ（C 実装）** が含まれています。

- Linux 版：`libio_core_linux.c`
- Windows 版：`io_core_win.c`

これらは PHP の FFI によってロードされ、  
`src/Framework/driver/` に配置された `.so` / `.dll` を  
`AdaptiveIoDriverFactory` が自動的に読み込みます。

ヘッダファイルは不要で、**C ソースがそのまま API / ABI 仕様**となります。

---

## **ディレクトリ構成**

```
ffi/
 ├── linux/
 │    ├── libio_core_linux-epoll.c
 │    └── build.sh
 ├── windows/
 │    ├── io_core_win.c
 │    └── build.bat
```

ビルド後の成果物は自動的に以下へ配置されます：

```
src/Framework/driver/
 ├── libio_core_linux.so
 └── io_core_win.dll
```

---

## **Linux 版ドライバのビルド**

### **1. 必要ツール**

- gcc  
- glibc ベースの Linux（Ubuntu / Debian / Fedora / CentOS / Arch など）

※ Alpine（musl libc）は別途ビルドが必要です。

### **2. ビルド**

```
cd ffi/linux
sh build.sh
```

### **3. 成果物の配置**

スクリプトが自動で以下に配置します：

```
src/Framework/driver/libio_core_linux.so
```

---

## **Windows 版ドライバのビルド**

### **1. 必要ツール**

- gcc（MinGW など）
- Winsock2（標準で付属）

### **2. ビルド**

```
cd ffi/windows
build.bat
```

### **3. 成果物の配置**

スクリプトが自動で以下に配置します：

```
src/Framework/driver/io_core_win.dll
```

---

## **ライセンス**

プロジェクトルートの `LICENSE` に従います。
