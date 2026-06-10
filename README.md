# goen-HP

結婚相談所 **Goen life partner**（ロゴ表記：Goen.） の公式ホームページ（静的サイト）。

## 構成

| ファイル | 内容 |
|---|---|
| `index.html` | トップ（ヒーロー／3つのお約束／流れの抜粋／CTA） |
| `about.html` | Goen.について（想い・安心への取り組み・IBJ加盟店） |
| `flow.html` | ご成婚までの流れ（6ステップ） |
| `price.html` | 料金プラン |
| `contact.html` | お問い合わせ（フォーム付き） |
| `assets/css/style.css` | スタイル一式 |
| `assets/js/main.js` | ナビ開閉・スクロール演出・フォーム検証/送信 |
| `assets/images/` | 写真の配置場所（下記参照） |

ビルド不要。HTML/CSS/JS のみで動作します。

## GitHub Codespaces で開発する（推奨）

`.devcontainer/devcontainer.json` を同梱しています。

1. GitHub のリポジトリ → **`Code` ▾ → `Codespaces` → `Create codespace`**
2. 起動後、`index.html` を開き、エディタ右下の **「Go Live」** をクリック
   （Live Server 拡張が自動で入り、保存すると自動リロードされます）
3. 転送ポートの通知から **「ブラウザで開く」** でプレビュー

Live Server を使わない場合は、ターミナルで `python3 -m http.server 8000`
を実行し、ポート 8000 をブラウザで開いてください。

## ローカルで確認する

```bash
cd goen-HP
python3 -m http.server 8000
# ブラウザで http://localhost:8000 を開く
```

## 画像の差し替え

`assets/images/` に以下のファイル名で写真を置くと自動的に反映されます。
未配置の場合は上品な背景色で表示されます（壊れません）。

- `logo.png` … ロゴ（ヘッダー・フッター・トップ中央。**背景透過PNG・余白を詰めたもの**推奨）
- `hero.jpg` … トップのメインビジュアル（微笑み合うお二人）
- `about-1.jpg` … Goen.について「寄り添う」（ブーケと本）
- `about-2.jpg` … Goen.について「安心」（手をつなぐ）
- `rings.jpg` … 料金プラン上部バナー（結婚指輪）
- `forever.jpg` … トップ／各ページ下部の帯（バージンロード）

## お問い合わせフォームの有効化

静的サイトのため、フォーム送信には外部サービスが必要です。
[Formspree](https://formspree.io/) などで作成したフォームIDを、
`contact.html` の以下の箇所に設定してください。

```html
<form ... action="https://formspree.io/f/your-form-id" method="POST" ...>
```

`your-form-id` を実際のIDに置き換えると、送信が有効になります。
未設定の間は、入力検証のみ動作し、送信時に設定を促すメッセージを表示します。

## デプロイ（GitHub Pages）

`.github/workflows/deploy-pages.yml` を同梱しています。
リポジトリの **Settings → Pages → Build and deployment → Source** を
**「GitHub Actions」** に設定すると、`main` または
`claude/marriage-consultation-website-KFvW6` への push で自動公開されます。

公開URL（例）: `https://sho0628.github.io/goen-HP/`

> 初回のみ、リポジトリ管理者による上記 Settings の設定が必要です。
> ワークフロー手動実行（Actions → Deploy to GitHub Pages → Run workflow）も可能です。

Netlify / Vercel / レンタルサーバー等にもそのまま配置できます。
Netlify を使う場合は、`contact.html` の form を Netlify Forms 用に
`data-netlify="true"` 形式へ変更すると外部サービス不要で送信できます。

## 連絡先プレースホルダー

電話番号・メール・URL・SNS リンク・料金は仮の値です。
開業情報が確定したら各 HTML / フッターを更新してください。
