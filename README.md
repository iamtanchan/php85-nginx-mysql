# PHP 8.5 + Nginx + MySQL

このリポジトリは、ローカル開発は Docker Compose、DigitalOcean への本番デプロイは App Platform でシンプルに行える構成です。

アプリの公開ディレクトリは `src` です。

[![Deploy to DO](https://www.deploytodo.com/do-btn-blue.svg)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/iamtanchan/php85-nginx-mysql/tree/main)

## いちばん簡単なデプロイ方法

1. 上の `Deploy to DO` ボタンをクリックします。
2. DigitalOcean にログインして `Create App` を進めます。
3. App Platform がこのリポジトリの `Dockerfile` を使って自動ビルドします。
4. `main` ブランチへの push で自動再デプロイされます。

この方法なら Droplet の作成、Docker のインストール、SSH 設定は不要です。

## DigitalOcean App Platform

- App Platform 用の設定は `.do/deploy.template.yaml` に入っています。
- 本番デプロイはリポジトリ直下の `Dockerfile` を使います。
- カスタムドメインはデプロイ後に DigitalOcean の画面から追加できます。
- MySQL が必要になったら、DigitalOcean Managed MySQL を App Platform に接続するのがいちばん簡単です。
- App Platform の `Settings > Environment Variables` に `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSL_MODE` を追加すると、このアプリはその接続情報を使います。
- `sslmode=REQUIRED` を使う場合は、DigitalOcean から CA certificate をダウンロードして `DB_SSL_CA` または `DB_SSL_CA_PEM` も設定してください。
- App Platform ではファイルを直接置きにくいので、CA certificate の中身を `DB_SSL_CA_PEM` に入れる方法がいちばん簡単です。
- このリポジトリ直下に `ca-certificate.crt` を置くと、自動でその証明書を使います。
- `src` がアプリ本体で、`/` にアクセスすると `login.php` へ移動します。
- すでに App Platform で `DB_HOST is not set` が出ている場合は、`Settings > Environment Variables` で上記の `DB_*` を追加して再デプロイしてください。

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

6. アプリ本体は `src` を直接編集します。Docker のローカル開発構成も `src` をそのままマウントします。

## 変更点

- `Dockerfile` を追加して App Platform からそのままビルドできるようにしました。
- `.do/deploy.template.yaml` を追加して one-click deploy に対応しました。
- `.do/deploy.template.yaml` に App Platform の DB 環境変数プレースホルダを追加しました。
- 本番デプロイのおすすめを Droplet から App Platform に変更しました。
- `src/index.php` を追加して、`/` アクセス時に `login.php` へ入れるようにしました。
- `.env` は本物の接続情報用、`.env.example` は共有用テンプレートとして使う前提にしました。
- `DB_SSL_CA` と `DB_SSL_CA_PEM` に対応し、DigitalOcean Managed MySQL の TLS 接続を確認できるようにしました。
- リポジトリ直下の `ca-certificate.crt` も自動検出するようにしました。
- `src/lib/database.php` を更新して、`src` アプリがリポジトリ直下の `.env` と `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` を使えるようにしました。
- ローカル / Compose の PHP コンテナも `src` をアプリ作業ディレクトリとして使うようにしました。
- `docker-compose.dev.yml` はローカル専用の bind mount と MySQL 公開ポートを追加します。
- MySQL は内部ネットワークのみに公開され、phpMyAdmin は `dev` profile のときだけ起動します。
- ローカル開発用の Compose 構成はそのまま使えます。
