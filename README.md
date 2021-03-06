opGyoenKintaiPlugin
===================

## 概要
OpenPNE3上で勤怠管理ができるようにする。
勤怠はGoogle SpreadSheet上にて管理する。
メンバーは自分の勤怠を入力して、過去３日に遡り新規登録・編集ができる。それ以上たってしまった場合は、Spreadsheetで直接編集してください。
またメンバーは、他のメンバーの勤怠状況を見ることができる。
自分の勤怠は月毎にCSVでダウンロードすることができる。

## インストール
/plugins/に設置

## 設定
1\. Google SpreadSheetを新規作成・Spreadsheetキーを取得する。

【キーの取得方法】 

該当のSpreadsheetのURLにある、

    https://docs.google.com/a/example.com/spreadsheet/ccc?key=XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX&hl=ja#gid=1

のXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXの部分をコピーしておく。

2\. 本プラグインに付属しているSheetSample.xlsをGoogle Spreadsheetにインポートする。

3\. 続いてSpreadSheetのワークシートの名前を、各メンバーのPCメールアドレスの「@」より前に一致する名前に変更する。

※追加でメンバーが必要な場合は、「Template」という名前のワークシートを必要な分だけコピー作成して、そのコピーしたものをそれぞれの対応する名前に変更してやってください。

4\. OpenPNE3の管理画面に入り、opGyoenKintaiPluginの設定画面にアクセスする。
アクセスしたら、そこに自分のGoogle ID、パスワード、(2. でコピーした)Spreadsheetキーを入力する。

5\. CRONの設定をする。これをすることにより、このプラグインが自動で各メンバーのワークシートに整形出力してくれる。

生データをメンバーのワークシートに整形出力するコマンドは

    ./symfony opKintai:execute

メンバーIDの範囲を指定してタスクを実行することもできます。
 
    ./symfony opKintai:execute --start-member-id=1 --end-member-id=10

とすると、メンバーID１〜１０の間でスキャンが実行されます。

※start-member-idを実行しない場合は１、end-member-idを指定しない場合はSNSメンバーの最大IDまでの範囲で実行されます。

勤怠報告をアクティビティに投稿して促すコマンドは

(朝)

    ./symfony opKintai:notify morning

(夕)

    ./symfony opKintai:notify evening

とそれぞれ実行する。
