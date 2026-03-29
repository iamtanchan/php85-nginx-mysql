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

## 変更点

- `Dockerfile` を追加して App Platform からそのままビルドできるようにしました。
- `.do/deploy.template.yaml` を追加して one-click deploy に対応しました。
- 本番デプロイのおすすめを Droplet から App Platform に変更しました。
- `docker-compose.dev.yml` はローカル専用の bind mount と MySQL 公開ポートを追加します。
- MySQL は内部ネットワークのみに公開され、phpMyAdmin は `dev` profile のときだけ起動します。
- ローカル開発用の Compose 構成はそのまま使えます。
