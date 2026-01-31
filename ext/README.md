# **socketsfd 拡張モジュール — ビルド手順（Linux / Windows）**


# **■ 依存関係**

- PHP 8.4 以上  
- `sockets` 拡張が有効であること  
- C コンパイラ（Linux: gcc / Windows: MSVC）

---

# **■ Linux でのビルド（phpize 使用）**

Ubuntu / Debian / その他 Linux ディストリビューションで共通です。

### **1. 必要パッケージのインストール**

Ubuntu / Debian の例：

```
sudo apt install php-dev build-essential
```

### **2. ソースディレクトリへ移動**

```
cd ext/socketsfd
```

### **3. ビルド**

```
phpize
./configure
make
sudo make install
```

### **4. 拡張の有効化**

`php.ini` または mods-available に以下を追加：

```
extension=socketsfd.so
```

### **5. 読み込み確認**

```
php -m | grep socketsfd
```

---

# **■ Windows でのビルド（PHP SDK + MSVC）**

Windows では phpize が存在しないため、  
**PHP SDK と Visual Studio Build Tools** を使用します。

### **1. 必要ツールの準備**

- Visual Studio Build Tools（C++ Build Tools + Windows SDK）
- PHP SDK（php-sdk-binary-tools）

PHP SDK は以下から取得できます：  
https://github.com/php/php-sdk-binary-tools/releases

### **2. PHP SDK のビルド環境を起動**

```
phpsdk-vs17-x64.bat
```

### **3. PHP ソースコードを展開し、ext/socketsfd を配置**

例：

```
php-8.4.x-src/
    └── ext/
         └── socketsfd/
             ├── config.w32
             ├── socketsfd.c
             └── php_socketsfd.h
```

### **4. configure.js を実行**

```
buildconf
configure --enable-socketsfd
```

### **5. ビルド**

```
nmake
nmake install
```

成功すると `php_socketsfd.dll` が生成されます。

### **6. DLL の配置**

```
C:\php\ext\php_socketsfd.dll
```

### **7. php.ini に追加**

```
extension=php_socketsfd.dll
```

---

# **■ 注意事項**

- この拡張は `sockets` 拡張に依存します。  
  Linux / Windows ともに `sockets` が先にロードされている必要があります。
- Ubuntu/Debian では `sockets` は標準で有効です。
- Windows では `php.ini` に `extension=sockets` が必要な場合があります。

---

# **■ ライセンス**

本拡張はプロジェクトルートの `LICENSE` に従います。
