# ホスト監視モニター

4台のホスト（VPS ×3＋Nextcloud用ARMインスタンス ×1）の状態を、エヴァンゲリオン風UIで表示する監視ダッシュボード。
単体PHPページ＋Pythonコレクタ＋簡易エンドポイントで構成される。

## 構成

| ファイル | 役割 |
|---|---|
| `index.php` | 表示ページ。5秒毎に `?api=1` を取得し、リロードなしで自動更新。ゲージはバー表示／円形ニードルメーター表示を切替可能（選択はブラウザに保存） |
| `collect.py` | 収集デーモン。30秒毎に自機＋対象ホストをSSHで収集し、状態JSONを生成してエンドポイントへPOSTする |
| `magi_endpoint.py` | 状態JSONの配信・受信エンドポイント（トークン付きURLでのみ応答） |
| `collect-config.json.example` | コレクタ用設定の雛形（実ファイル `collect-config.json` はGit管理外） |
| `magi-config.php.example` | ページ用設定の雛形（実ファイル `magi-config.php` はGit管理外） |

## セットアップ

1. コレクタを置くホストで `collect-config.json.example` を `collect-config.json` にコピーし、対象ホストのIP・アドレス・SSH鍵ファイル名を設定する。
2. 同ホストに `token.txt`（エンドポイントと共有するランダムなトークン）と、収集用SSH鍵を配置する。対象ホスト側の `authorized_keys` に収集用公開鍵を登録しておく。
3. エンドポイントを置くホストで `magi_endpoint.py` の `__TOKEN__` をトークンに置換して起動する（systemd推奨）。
4. Webサーバ側に `index.php` を配置し、`magi-config.php.example` を `magi-config.php` にコピーして状態JSONのURLを設定する。

## セキュリティ上の設計

- 状態JSONに含めるのはホスト名とメトリクス（負荷・メモリ・ディスク等）のみ。IPアドレス・ポート・接続先URLはコードにもJSONにも含めない。
- トークン・IP等の実値は設定ファイルに分離し、Git管理外としている（`.gitignore` 参照）。
- フォントファイル `magi-font.woff2` は原フォントのライセンスのためリポジトリに含めない。無くても代替フォントで表示される。
