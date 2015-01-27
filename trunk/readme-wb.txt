== readme-wb.txt ==

ver.1.2.2
getimagesize について
WordPress がサブドメインで動作している場合
元画像ファイルの指定が URL の場合動作しない為
1回目: URL 指定で失敗したら
2回目: URL を Path に置換
するにようにした。

ver.1.2.1
デフォルトイメージ処理の変更

ver.1.2.0
オプション：デフォルトイメージを使用しないを追加
画像が無い場合は、サムネイルを出力しない。

ver.1.1.9
ランダムイメージ機能

ver.1.1.8
php4 対応

var.1.1.6
プラグイン emoji に対応

ver.1.1.4

他のサイトの画像をサムネイルする機能を追加
他のサイトの画像は2週間毎に更新されます。
更新のときはすごく遅くなります。
オプション global=1 を指定して下さい。

<?php echo the_qf_get_thumb_one('num=0&width=160&tag=1&global=1&crop_w=160&crop_h=120&find=logo&global=1', './images/defaultimg.png', 'http://www.yahoo.co.jp'); ?>

--
