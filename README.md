# Laravelプロジェクトの作成方法

1. **cloneする。**  
   プロジェクトのコピーを自分のコンピュータにダウンロードします。

   ```
   git clone https://github.com/iamtanchan/php85-nginx-mysql
   ```

2. **docker composeで立ち上げる。**  
   ダウンロードしたプロジェクトを使って、必要なプログラム（コンテナと呼ばれる）を自動的に起動します。

   ```
   cd php85-nginx-mysql
   docker compose up -d
   ```

3. **phpコンテナに入る**  
   起動したプログラムの中の一つ、PHPを使う部分にアクセスします。

   ```
   docker exec -it myapp-php bash
   ```

4. **laravelをインストール**  
   PHPを使って、Laravelというツールをセットアップ（インストール）します。

   ```
   composer create-project --prefer-dist laravel/laravel my-app
   ```

5. **phpコンテナから出る**  
   Laravelのセットアップが終わったら、PHPの部分を終了します。

   ```
   exit
   ```

6. **docker-compose.ymlを編集する**  
   設定ファイル（docker-compose.yml）を変更して、プロジェクトの設定を更新します。以下のように`volumes`セクションを編集してください。

   ```
     web:

       volumes:
       - - .:/var/www/
       + - ./my-app:/var/www/

     nginx:

       volumes:
       - - .:/var/www/
       + - ./my-app:/var/www/

   ```

7. **再度docker composeで立ち上げる**  
   更新した設定で、もう一度プログラムを起動します。
   ```
   docker compose up -d
   ```
