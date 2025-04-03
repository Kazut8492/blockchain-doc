An English translation of this ReadMe follows at the end.

# BlockDoc Verify

ブロックチェーン技術を活用した文書認証システム。

## 概要

BlockDoc Verifyは、ブロックチェーン技術を使用して文書の真正性を安全に管理・検証するシステムです。このシステムでは以下のことが可能です：

- PDF文書をセキュアなストレージシステムにアップロード
- 文書のハッシュ値をイーサリアムブロックチェーン（Sepoliaテストネット）に登録
- アップロードした文書の閲覧と管理
- ブロックチェーン上の記録と照合して文書の真正性を検証

## アーキテクチャ

このアプリケーションは以下の要素で構成されています：

- **フロントエンド**: Reactベースのユーザーインターフェース
- **バックエンド**: Laravel API
- **ブロックチェーン**: イーサリアムSepoliaテストネットにデプロイされたスマートコントラクト
-- 今回使用したコードに関しては、blockdoc-backend/DocumentVerification_example.solをご参照ください。
- **ストレージ**: ローカルファイルストレージ（AWS S3オプションあり）
- **データベース**: PostgreSQL
- **キューシステム**: 非同期ブロックチェーン操作のためのRedis

## 前提条件

- PHP 8.2+
- Composer
- Node.jsとnpm
- PostgreSQL
- Redis
- SepoliaテストネットのETHを持つMetaMaskウォレット
- Alchemyアカウント（イーサリアムノードプロバイダーとして）

## インストール

### バックエンドのセットアップ

1. リポジトリをクローン
   ```bash
   git clone https://github.com/yourusername/blockdoc-verify.git
   cd blockdoc-verify
   ```

2. PHPの依存関係をインストール
   ```bash
   composer install
   ```

3. 環境ファイルをコピーして設定
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. データベースをセットアップ
   ```bash
   php artisan migrate
   ```

5. 文書用のストレージリンクを作成
   ```bash
   php artisan storage:link
   ```

6. スマートコントラクトABI用のディレクトリを作成
   ```bash
   mkdir -p storage/app/contract
   ```

7. スマートコントラクトのABIを`storage/app/contract/DocumentVerification.json`に追加

### フロントエンドのセットアップ

1. JavaScriptの依存関係をインストール
   ```bash
   npm install
   ```

2. フロントエンドアセットをビルド
   ```bash
   npm run build
   ```

## 設定

### 環境変数

`.env`ファイルを以下の値で更新します：

```
# アプリケーション設定
APP_NAME="BlockDoc Verify"
APP_ENV=local
APP_KEY=your-app-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# データベース設定
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=blockdoc
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password

# ブロックチェーン設定
BLOCKCHAIN_PROVIDER_URL=your-alchemy-api-endpoint
BLOCKCHAIN_CONTRACT_ADDRESS=your-deployed-contract-address
BLOCKCHAIN_ACCOUNT_ADDRESS=your-metamask-wallet-address
BLOCKCHAIN_PRIVATE_KEY=your-metamask-private-key
BLOCKCHAIN_NETWORK_NAME="Sepolia Testnet"
BLOCKCHAIN_GAS_LIMIT=100000
BLOCKCHAIN_GAS_PRICE_STRATEGY=medium
BLOCKCHAIN_GAS_PRICE=10

# キュー処理用Redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 重要なセキュリティ注意事項

1. **絶対に**プライベートキーを含む`.env`ファイルをバージョン管理にコミットしないでください
2. デプロイメントには環境変数を使用してください
3. このアプリケーション専用のウォレットの使用を検討してください
4. 本番環境ではプライベートキーを適切に保護してください

## スマートコントラクト

このシステムは、イーサリアムSepoliaテストネットにデプロイされたSolidityスマートコントラクトを使用して、文書のハッシュと検証タイムスタンプを保存します。

### コントラクト関数

- `registerDocument(string documentHash)`: 文書ハッシュをブロックチェーンに登録
- `verifyDocument(string documentHash)`: 文書ハッシュがブロックチェーン上に存在するか検証
- `getDocumentTimestamp(string documentHash)`: 文書が登録された時のタイムスタンプを取得

### コントラクトのデプロイ

コントラクトは既に`.env`ファイルで指定されたアドレスにデプロイされています。新しいインスタンスをデプロイする必要がある場合：

1. Remix IDE (https://remix.ethereum.org/) を使用
2. コントラクトコードで新しいファイルを作成
3. コントラクトをコンパイル
4. MetaMaskを使用してSepoliaテストネットにデプロイ
5. 新しいコントラクトアドレスで`.env`を更新

## アプリケーションの実行

### 開発モード

1. Laravelの開発サーバーを起動
   ```bash
   php artisan serve
   ```

2. ブロックチェーン操作用のキューワーカーを起動
   ```bash
   php artisan queue:work
   ```

3. 別のターミナルでフロントエンド開発サーバーを起動
   ```bash
   npm run dev
   ```

### 本番モード

1. 適切なWebサーバー（Nginx/Apache）をセットアップ
2. WebサーバーがLaravelアプリケーションを提供するよう設定
3. キューワーカー用のプロセスマネージャーをセットアップ（Supervisorが推奨）
4. `.env`で`APP_ENV=production`と`APP_DEBUG=false`を設定

## 使用方法

### 文書アップロード

1. アップロードページに移動
2. PDFファイルを選択（最大100MB）
3. フォームを送信
4. システムは以下を実行します：
   - 文書のSHA512ハッシュを生成
   - 文書が既に存在するかを確認
   - 文書をファイルシステムに保存
   - ハッシュをブロックチェーンに登録
   - トランザクションのステータスを表示

### 文書の検証

1. 検証ページに移動
2. 検証用のPDFファイルをアップロード
3. システムは以下を実行します：
   - アップロードされた文書のSHA512ハッシュを生成
   - 一致するハッシュがブロックチェーンにあるか確認
   - 登録されている場合はタイムスタンプとともに検証結果を表示

## APIエンドポイント

### 文書管理

- `POST /api/documents` - 文書のアップロードと登録
- `GET /api/documents` - 認証済みユーザーの全文書リスト取得
- `GET /api/documents/{id}` - 特定の文書の詳細を取得
- `GET /api/documents/{id}/download` - 文書のダウンロード
- `GET /api/documents/{id}/status` - ブロックチェーン登録ステータスの確認

### 検証

- `POST /api/verify` - 文書をブロックチェーンに対して検証

## セキュリティに関する考慮事項

- 本番環境ではHTTPSを使用
- 適切な認証と認可を実装
- 本番環境では`.env`ファイルからプライベートキーを削除し、安全なキー管理システムを使用
- ファイルアップロードを信頼できるユーザーに制限
- 適切なファイルサイズ制限を設定
- ファイルのMIMEタイプを検証

## 開発ガイドライン

- Laravelのベストプラクティスに従う
- 開発にはフィーチャーブランチを使用
- 新機能にはテストを書く
- API変更を文書化
- ローカル開発には複数サーバーを同時に起動する`composer dev`を使用

## トラブルシューティング

### 一般的な問題

1. **ブロックチェーントランザクションの失敗**
   - ウォレットにガス代用の十分なETHがあることを確認
   - AlchemyダッシュボードでAPIリクエスト制限を確認
   - コントラクトアドレスが正しいことを確認

2. **文書アップロードの問題**
   - ストレージディレクトリのファイルパーミッションを確認
   - `php.ini`でPHPのファイルアップロード制限を確認
   - ストレージのシンボリックリンクが正しく作成されているか確認

3. **検証の問題**
   - 文書が変更されていないことを確認（わずかな変更でもハッシュが変わります）
   - ブロックチェーントランザクションが正常に確認されたか確認
   - Etherscanでコントラクトの状態を確認

## ライセンス

このプロジェクトはMITライセンスの下で提供されています - 詳細はLICENSEファイルを参照してください。


--------------------------------------------------------------------------------------------------------------------------------


# BlockDoc Verify

A document management system with blockchain-based verification capabilities.

## Overview

BlockDoc Verify provides a secure way to manage and verify the authenticity of documents by leveraging blockchain technology. The system allows users to:

- Upload PDF documents to a secure storage system
- Register document hashes on the Ethereum blockchain (Sepolia testnet)
- View and manage uploaded documents
- Verify the authenticity of documents by checking their hash against the blockchain record

## Architecture

The application consists of:

- **Frontend**: React-based user interface
- **Backend**: Laravel API
- **Blockchain**: Smart contract deployed on Ethereum Sepolia testnet
- **Storage**: Local file storage (with option for AWS S3)
- **Database**: PostgreSQL
- **Queue System**: Redis for asynchronous blockchain operations

## Prerequisites

- PHP 8.2+
- Composer
- Node.js and npm
- PostgreSQL
- Redis
- MetaMask wallet with Sepolia testnet ETH
- Alchemy account (for Ethereum node provider)

## Installation

### Backend Setup

1. Clone the repository
   ```bash
   git clone https://github.com/yourusername/blockdoc-verify.git
   cd blockdoc-verify
   ```

2. Install PHP dependencies
   ```bash
   composer install
   ```

3. Copy the environment file and configure it
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Set up the database
   ```bash
   php artisan migrate
   ```

5. Create storage link for documents
   ```bash
   php artisan storage:link
   ```

6. Create the smart contract ABI directory
   ```bash
   mkdir -p storage/app/contract
   ```

7. Add your smart contract ABI to `storage/app/contract/DocumentVerification.json`

### Frontend Setup

1. Install JavaScript dependencies
   ```bash
   npm install
   ```

2. Build the frontend assets
   ```bash
   npm run build
   ```

## Configuration

### Environment Variables

Update your `.env` file with the following values:

```
# App Configuration
APP_NAME="BlockDoc Verify"
APP_ENV=local
APP_KEY=your-app-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=blockdoc
DB_USERNAME=your-db-username
DB_PASSWORD=your-db-password

# Blockchain Configuration
BLOCKCHAIN_PROVIDER_URL=your-alchemy-api-endpoint
BLOCKCHAIN_CONTRACT_ADDRESS=your-deployed-contract-address
BLOCKCHAIN_ACCOUNT_ADDRESS=your-metamask-wallet-address
BLOCKCHAIN_PRIVATE_KEY=your-metamask-private-key
BLOCKCHAIN_NETWORK_NAME="Sepolia Testnet"
BLOCKCHAIN_GAS_LIMIT=100000
BLOCKCHAIN_GAS_PRICE_STRATEGY=medium
BLOCKCHAIN_GAS_PRICE=10

# Redis for queue processing
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Important Security Notes

1. **Never** commit your `.env` file to version control, especially with your private key
2. Use environment variables for deployment
3. Consider using a dedicated wallet for this application
4. Secure your private key properly in production

## Smart Contract

The system uses a Solidity smart contract deployed on the Ethereum Sepolia testnet to store document hashes and verification timestamps.

### Contract Functions

- `registerDocument(string documentHash)`: Registers a document hash on the blockchain
- `verifyDocument(string documentHash)`: Verifies if a document hash exists on the blockchain
- `getDocumentTimestamp(string documentHash)`: Gets the timestamp when a document was registered

### Contract Deployment

The contract is already deployed at the address specified in your `.env` file. If you need to deploy a new instance:

1. Use Remix IDE (https://remix.ethereum.org/)
2. Create a new file with the contract code
3. Compile the contract
4. Deploy to Sepolia testnet using MetaMask
5. Update your `.env` with the new contract address

## Running the Application

### Development Mode

1. Start the Laravel development server
   ```bash
   php artisan serve
   ```

2. Start the queue worker for blockchain operations
   ```bash
   php artisan queue:work
   ```

3. In a separate terminal, start the frontend development server
   ```bash
   npm run dev
   ```

### Production Mode

1. Set up a proper web server (Nginx/Apache)
2. Configure your web server to serve the Laravel application
3. Set up a process manager for the queue worker (Supervisor recommended)
4. Set `APP_ENV=production` and `APP_DEBUG=false` in your `.env`

## Usage

### Document Upload

1. Navigate to the upload page
2. Select a PDF file (100MB max)
3. Submit the form
4. The system will:
   - Generate a SHA512 hash of the document
   - Check if the document already exists
   - Store the document in the filesystem
   - Register the hash on the blockchain
   - Display the transaction status

### Document Verification

1. Navigate to the verification page
2. Upload a PDF file for verification
3. The system will:
   - Generate a SHA512 hash of the uploaded document
   - Check the blockchain for a matching hash
   - Display the verification result with timestamp if registered

## API Endpoints

### Document Management

- `POST /api/documents` - Upload and register a document
- `GET /api/documents` - List all documents for the authenticated user
- `GET /api/documents/{id}` - Get a specific document details
- `GET /api/documents/{id}/download` - Download a document
- `GET /api/documents/{id}/status` - Check blockchain registration status

### Verification

- `POST /api/verify` - Verify a document against the blockchain

## Security Considerations

- Use HTTPS in production
- Implement proper authentication and authorization
- Remove the private key from the `.env` file in production and use a secure key management system
- Limit file uploads to trusted users
- Set appropriate file size limits
- Validate file mime types

## Development Guidelines

- Follow Laravel best practices
- Use feature branches for development
- Write tests for new features
- Document API changes
- Run `composer dev` for local development with concurrent servers

## Troubleshooting

### Common Issues

1. **Blockchain Transaction Failures**
   - Ensure your wallet has sufficient ETH for gas fees
   - Check Alchemy dashboard for API request limits
   - Verify your contract address is correct

2. **Document Upload Issues**
   - Check file permissions on storage directory
   - Verify PHP file upload limits in `php.ini`
   - Ensure storage symlink is created properly

3. **Verification Problems**
   - Confirm the document hasn't been modified (even minor changes affect the hash)
   - Check if the blockchain transaction was successfully confirmed
   - Verify the contract's state with Etherscan

## License

This project is licensed under the MIT License - see the LICENSE file for details.