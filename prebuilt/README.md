# **インストール方法**

## **Ubuntu / Debian（PHP 8.4 以降）**

1. `prebuilt/linux/ubuntu-debian/socketsfd.so` を PHP の `extension_dir` にコピー  
   例:  
   ```
   /usr/lib/php/20240924/
   ```

2. `/etc/php/8.4/mods-available/socketsfd.ini` を作成し、以下を記述  
   ```
   extension=socketsfd.so
   ```

3. 拡張を有効化  
   ```
   sudo phpenmod socketsfd
   ```

4. 読み込み確認  
   ```
   php -m | grep socketsfd
   ```

---

## **その他の Linux ディストリビューション**

1. ソースディレクトリへ移動  
   ```
   cd ext/socketsfd
   ```

2. ビルド  
   ```
   phpize
   ./configure
   make
   sudo make install
   ```

3. `php.ini` または `mods-available` に以下を追加  
   ```
   extension=socketsfd.so
   ```

4. 読み込み確認  
   ```
   php -m | grep socketsfd
   ```

---

## **Windows（PHP 8.4 以降）**

1. `prebuilt/windows/php_socketsfd.dll` を PHP の `ext` ディレクトリにコピー  
   例:  
   ```
   C:\php\ext\
   ```

2. `php.ini` に以下を追加  
   ```
   extension=php_socketsfd.dll
   ```

3. 読み込み確認  
   ```
   php -m | findstr socketsfd
   ```

---

## **必要条件**

- PHP 8.4 以上  
- `sockets` 拡張が有効であること  
  （Ubuntu/Debian では標準で有効）

---

## **ライセンス**

本拡張はプロジェクトルートの `LICENSE` に従います。
