# goen-HP

結婚相談所 **Goen life partner**（ロゴ表記：Goen.）の公式ホームページ
<https://goen-partner.com>

ビルド不要の静的サイト（HTML/CSS/JS）＋ お問い合わせ用の PHP 1 本で構成。
本番は **Xサーバー** で公開し、このリポジトリへの push で自動デプロイされます。

## 構成

| ファイル | 内容 |
|---|---|
| `index.html` | トップ（ヒーロー／3つのお約束／流れの抜粋／CTA） |
| `about.html` | Goen.について（想い・安心への取り組み・IBJ加盟店） |
| `flow.html` | ご成婚までの流れ（6ステップ） |
| `price.html` | 料金プラン |
| `contact.html` | お問い合わせ（フォーム付き） |
| `contact.php` | お問い合わせフォームの送信処理（通知メール・自動返信・スパム対策） |
| `.htaccess` | Xサーバー用 Apache 設定 |
| `assets/css/style.css` | スタイル一式 |
| `assets/js/main.js` | ナビ開閉・スクロール演出・フォーム検証/送信 |
| `assets/images/` | 写真の配置場所（下記参照） |
| `.github/workflows/deploy-xserver.yml` | Xサーバーへの自動デプロイ |

## デプロイ（Xサーバー）

`main` または `claude/marriage-consultation-website-KFvW6` への push で、
GitHub Actions が FTPS でXサーバーの公開ディレクトリへファイルを同期します（変更分のみ）。

### 初回設定（1回だけ）

1. Xサーバー **サーバーパネル → FTPアカウント設定 → `goen-partner.com` → FTPアカウント追加**
   - FTPユーザーID：任意（例 `deploy` → `deploy@goen-partner.com` になります）
   - パスワード：任意（長くて推測されにくいもの）
   - 接続先ディレクトリ：`/goen-partner.com/public_html`（初期値のまま）
2. GitHub リポジトリ **Settings → Secrets and variables → Actions → New repository secret** で登録

   | Name | Value |
   |---|---|
   | `XSERVER_FTP_USER` | 1 で作った FTP ユーザーID（例 `deploy@goen-partner.com`） |
   | `XSERVER_FTP_PASSWORD` | 1 で決めたパスワード |

3. **Actions → Deploy to Xserver → Run workflow** で手動実行（以後は push のたびに自動）

> サーバー本体のアカウント（`goen5050`）でログインする場合は FTP のルートが `/home/goen5050/` になるため、
> Secret `XSERVER_FTP_DIR` に `/goen-partner.com/public_html/`（末尾スラッシュ必須）を追加してください。

Secrets が未登録のときは、デプロイをスキップして警告だけ出します（失敗にはなりません）。
アップロード対象から `.git*` / `.github` / `.devcontainer` / `README.md` は除外されます。

## お問い合わせフォーム（contact.php）

`contact.html` → `contact.php` に POST → `info@goen-partner.com` へ通知メール ＋ 送信者へ自動返信。
外部サービスは使いません。

- 設定は `contact.php` 冒頭の定数（宛先・送信者名・自動返信の有無・回数制限）だけ
- スパム対策（`contact.php`）
  - 非表示欄（ハニーポット）に入力がある／ページ表示から 3 秒未満の送信 → 受け付けたふりをして破棄
  - JavaScript なしの送信（ページを開かずに直接送る bot）→ 受け付けない
  - 名前・フリガナ・本文の URL、日本語を含まない本文 → 理由を表示して受け付けない
  - 同一 IP は 10 分に 5 回まで
  - 弾いた記録はサーバーパネルの **エラーログ** に `[goen-contact]` で残ります（入力内容は記録しません）
- お問い合わせページに「営業目的のご連絡はご遠慮ください。」を表示
- 自動返信には送信者が書いた本文を含めません（第三者宛のスパム中継に使われないため）
- 動作確認：`https://goen-partner.com/contact.php?ping=1` を開くと `{"ok":true,...}` が返ります（メールは送りません）
- 送れないとき：サーバーパネル → **エラーログ** に `[goen-contact]` で記録されます
- JS 無効のブラウザでも送信でき、結果は `contact.html?sent=1` / `?error=…` で表示されます

## DNS / メール（Xサーバー「DNSレコード設定」）

| 種別 | ホスト | 内容 |
|---|---|---|
| A | `goen-partner.com` | `85.131.221.39`（サーバー `sv17078.xserver.jp` の IP） |
| A | `www.goen-partner.com` | `85.131.221.39`（`.htaccess` で `https://goen-partner.com` へ転送） |
| MX | `goen-partner.com` | `sv17078.xserver.jp`（優先度 0） |

メールは Xサーバーのメールアカウント `info@goen-partner.com` で送受信します。

## 移行メモ（GitHub Pages → Xサーバー。完了後にこの節は削除）

- [x] FTP アカウント（`deploy@goen-partner.com`）作成・Secrets 登録
- [x] 初回デプロイ成功（Actions → Deploy to Xserver）
- [x] SSL設定で失効していた無料独自SSLを「更新」
- [x] A レコードを GitHub Pages の 4 つから `85.131.221.39` へ切替（`www` も追加）
- [x] `contact.html` の送信先を `contact.php` に切替、`.htaccess` に HTTPS / www 転送を追加
- [x] `deploy-pages.yml` と `CNAME` を削除
- [ ] ブラウザで `https://goen-partner.com` の証明書が有効か確認（警告が出たら SSL設定で「更新」）
- [ ] 本番フォームからテスト送信し、通知メールと自動返信の受信を確認
- [ ] GitHub の Settings → Pages で公開を停止（Unpublish site）
- [ ] Formspree のフォーム（`mnjyeqvb`）を削除

## 開発・確認

### GitHub Codespaces

`.devcontainer/devcontainer.json` を同梱しています。
リポジトリ → **`Code` ▾ → `Codespaces` → `Create codespace`** で起動し、ターミナルで下記を実行してください。

### ローカル

```bash
cd goen-HP
php -S localhost:8000     # PHP 同梱の簡易サーバー（contact.php も動く）
# ブラウザで http://localhost:8000 を開く
```

`python3 -m http.server 8000` でも静的ページは確認できますが、フォーム送信は動きません。
ローカルではメールは実際には送られず、`sendmail` が無い環境では送信エラーになります。

## 画像の差し替え

`assets/images/` に以下のファイル名で写真を置くと自動的に反映されます。
未配置の場合は上品な背景色で表示されます（壊れません）。

- `logo.png` … ロゴ（ヘッダー・フッター・トップ中央。**背景透過PNG・余白を詰めたもの**推奨）
- `hero.jpg` … トップのメインビジュアル（微笑み合うお二人）
- `about-1.jpg` … Goen.について「寄り添う」（ブーケと本）
- `about-2.jpg` … Goen.について「安心」（手をつなぐ）
- `rings.jpg` … 料金プラン上部バナー（結婚指輪）
- `forever.jpg` … トップ／各ページ下部の帯（バージンロード）
- `qr-line.png` / `qr-instagram.jpg` … お問い合わせページの QR コード

CSS / JS を変更したときは、各 HTML の `style.css?v=…` / `main.js?v=…` の値を更新すると
ブラウザのキャッシュに残った古い版が使われなくなります。
