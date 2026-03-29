# PHP 8.5 + Nginx + MySQL

このリポジトリは、ローカル開発と DigitalOcean Droplet へのデプロイの両方に対応した Docker Compose 構成です。

アプリの公開ディレクトリは `src` です。

## ローカル開発

1. clone します。

   ```bash
   git clone https://github.com/iamtanchan/php85-nginx-mysql
   cd php85-nginx-mysql
   ```

2. 必要なら `.env` を更新します。

   ```bash
   cp .env.example .env
   ```

3. 開発用 override を使って起動します。

   ```bash
   docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile dev up -d --build
   ```

4. PHP コンテナに入るときは service 名で実行します。

   ```bash
   docker compose exec web bash
   ```

5. ブラウザ確認先:

   ```text
   App:        http://localhost
   phpMyAdmin: http://localhost:8081
   ```

## DigitalOcean Droplet へデプロイ

1. Ubuntu Droplet を作成して Docker / Compose Plugin を入れます。

   ```bash
   sudo apt-get update
   sudo apt-get install -y docker.io docker-compose-plugin
   sudo systemctl enable --now docker
   ```

2. サーバーへコードを配置して `.env` を本番向けに更新します。

   ```dotenv
   APP_PORT=80
   NGINX_SERVER_NAME=example.com
   MYSQL_ROOT_PASSWORD=strong-root-password
   MYSQL_USER=app
   MYSQL_PASSWORD=strong-app-password
   ```

3. 本番用のデフォルト構成で起動します。

   ```bash
   docker compose up -d --build
   ```

4. 必要ならファイアウォールで `80/tcp` だけ公開します。

   ```bash
   sudo ufw allow 80/tcp
   sudo ufw allow OpenSSH
   sudo ufw enable
   ```

## 変更点

- `docker-compose.yml` は本番向けの安全なデフォルトです。
- `docker-compose.dev.yml` はローカル専用の bind mount と MySQL 公開ポートを追加します。
- MySQL は内部ネットワークのみに公開され、phpMyAdmin は `dev` profile のときだけ起動します。
- Nginx / PHP は bind mount 前提ではなく、イメージにアプリを含めてデプロイできます。
