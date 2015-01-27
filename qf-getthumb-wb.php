<?php
/*
Plugin Name: QF-GetThumb-wb
Plugin URI: http://takeai.silverpigeon.jp/
Description: QF-GetThumb-wb is a plug-in that extracts the image data from the content and the argument, and makes the thumbnail.
Version: 1.2.8
Author: AI.Takeuchi
Author URI: http://takeai.silverpigeon.jp/

This plugin branched from version 1.1.3 of QF-GetThumb.

Original QF-GetThumb plugin for WordPress Copyright 2009 Q.F. (email : info@la-passeggiata.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

// Debug option
$Qfgtwb_debug = 0;

umask(0);

// 多言語ファイル読込
if (version_compare($wp_version, '2.6', '<')) {
    load_plugin_textdomain('wpqfgtwb', 'wp-content/plugins/qf-getthumb-wb/languages');
}else{
    load_plugin_textdomain('wpqfgtwb', 'wp-content/plugins/qf-getthumb-wb/languages', 'qf-getthumb-wb/languages');
}

// イメージ編集用ライブラリ
require_once('fnc_image.php');

// オプション設定
$data = qf_load_default();

// 初期値をデータベースへ登録
add_option('qf_get_thumb_wb_settings',$data,'qf_get_thumb_wb Options');

// 管理者メニューに設定画面を呼び出し
add_action('admin_menu', 'qf_get_thumb_wb_options');

// 記事ソース内の指定された画像を1つ抽出する関数
//
// num=0	  : 何番目の画像を取り出すかの指定
// width=0	  : 画像の幅指定
// height=0	  : 画像の高さ指定
// tag=1	  : イメージタグを返すか / 返さないか(返さない場合、画像のURLを返す)
// global=0	  : 同一サーバ内のデータに限定するかどうか
// crop_w=0	  : クロップ時の横幅
// crop_h=0	  : クロップ時の縦幅
// find=string	  : 検索文字列(全文検索一致データの画像指定)
// $default_image : 画像が無い場合は、ここに指定された値を返す(無ければfalse を返す)
// $source	  : ソース取得先の指定(無ければ the_content を参照する)
//
function the_qf_get_thumb_one($gt_settings = "", $default_image = "", $source = Null) {
    // 記事内容をグローバル変数で定義
    global $post;
    global $Qfgtwb_debug;

    // 参照先指定が無ければ、content をベースにする
    if (is_null($source)) { $source = $post->post_content; }

    // フォーマット変数初期化
    $format = NULL;

    // WP設定データを取得
    $settings = get_option('qf_get_thumb_wb_settings');
    if ($default_image == "") { $default_image = $settings['default_image']; }

    // 設定取得
    $gt_settings = qf_get_parameter($gt_settings, $default_image, $settings['random_image'], $settings['not_use_default_image'], $settings['use_attachment_image']);

    //echo 'source = [' . $source . ']';

    // link external site
    $is_external_site_img = false;
    $update_external_site_img = false;
    if ($gt_settings['global'] && preg_match('/^https?:\/\//', $source)) {
        //echo 'external link';
        $is_external_site_img = true;
        $save = qf_get_savepath_external_link($source, $settings['uploads_path'], $settings['append_text'], $settings['basic_auth'], $settings['folder_name'], $gt_settings['width'], $gt_settings['height'], $gt_settings['crop_w'], $gt_settings['crop_h']);
        //echo 'save = ' . $save;
        if (file_exists($save)) {
            $it = filemtime($save);
            $now = time();
            //echo $now;
            $days = ($now - $it) / 60 / 60 / 24;
            //echo 'days = [' . $days . ']';
            if ($days > 14) $update_external_site_img = true;
        } else {
            $update_external_site_img = true;
        }
    }
    if ($update_external_site_img) {
        //echo 'update_external_site_img';
        $source = qf_get_site($source);
        if (file_exists($save)) unlink($save);
    }

    if (!$is_external_site_img || $update_external_site_img) {
        // 指定箇所のイメージタグを取得
        //echo 'source = ' . $source;
        $imgtag = qf_get_imagetag($source, $gt_settings['num'], $gt_settings['find'], $settings['domain_name'], $gt_settings['global'], $gt_settings['default_image'], $gt_settings['random_image'], $gt_settings['use_attachment_image']);
        //echo 'imgtag = ' . $imgtag;
    
        // イメージタグからイメージパスを取得
        $url = qf_get_imagepath($imgtag);
        //echo 'url = ' . $url;

        // 引数に問題が無いかチェックの上、修正を実行
        if (!$gt_settings['width'] && !$gt_settings['height']) {
            list($gt_settings['width'], $gt_settings['height'], $format) = @getimagesize($url);
        }

        // デフォルトイメージ出力の場合、ここで処理を終える
        if ($url == $gt_settings['default_image'] && $gt_settings['not_use_default_image']) {
            return;
        } else if ($url == $gt_settings['default_image'] && $gt_settings['tag'] == 1) {
            return $imgtag;
        } else if ($url == $gt_settings['default_image'] && $gt_settings['tag'] == 0) {
            return $gt_settings['default_image'];
        }
    }

    if (!$is_external_site_img) {
        // 保存先を設定
      $save = qf_get_savepath($settings['domain_name'], $url, $settings['uploads_path'], $settings['append_text'], $settings['basic_auth'], $settings['folder_name'], $gt_settings['width'], $gt_settings['height'], $gt_settings['crop_w'], $gt_settings['crop_h']);
    }

    //echo 'save = ' . $save;
    
    // キャッシュファイルが存在しなければ、サムネイル生成・保存
    if (!file_exists($save)) {
        //echo $save;
        // サムネイルデータ生成
        //$url = 'http://hotta-glass.com/parts/wp-content/uploads/2011/01/syouji1-254x227.jpg';
        $image = qf_make_thumbnail($url, $gt_settings['width'], $gt_settings['height'], $settings['basic_auth']);
        if ($Qfgtwb_debug) {
            echo "<p>qf-getthumb-wb: the_qf_get_thumb_one: url = $url, image size = " . strlen($image) . '</p>';
        }

        // イメージデータクロップ実行
        $image = qf_make_cropimage($image, $gt_settings['crop_w'], $gt_settings['crop_h']);

        // 保存先ディレクトリ確認・生成
        if (!qf_check_savedir($save)) {
            echo "<p>qf-getthumb-wb: イメージ保存先が作成できません: " . dirname($save) . "</p>";
            return;
        }

        // 対象ファイルのフォーマット取得
        if (is_null($format)) {
            $format = getformat_image($url);
        }
        if ($is_external_site_img) $format = 3; // png
        
        // サムネイル保存
        if (!qf_save_thumbnail($save, $image, $format)) {
            echo "<p>qf-getthumb-wb: the_qf_get_thumb_one: サムネイル保存失敗: url = $url, save = $save, format = $format</p>";
            return;
        }
        chmod($save, 0777);
    }
    
    // サムネイルファイルのフルパスを返す
    if ($gt_settings['tag'] == 1) {
        if ($is_external_site_img) {
            return qf_get_new_imagetag_external_img($save, $settings['uploads_path'], $settings['uploads_url']);
        } else {
            // イメージタグを整形して出力
            return qf_get_new_imagetag($imgtag, $url, $save, $settings['uploads_path'], $settings['uploads_url']);
        }
    } else {
        if ($is_external_site_img) {
            return qf_get_thumburl_external_img($save, $settings['uploads_path'], $settings['uploads_url']);
        } else {
            return qf_get_thumburl($save, $settings['uploads_path'], $settings['uploads_url']);
        }
    }
}
// イメージタグを整形
function qf_get_new_imagetag_external_img($save, $uploads_path, $uploads_url) {
    $save = str_replace($uploads_path, $uploads_url, $save);
    return '<img src="' . $save . '" />';
}
function qf_get_thumburl_external_img($save, $uploads_path, $uploads_url) {
    return str_replace($uploads_path, $uploads_url, $save);
}

// イメージタグを整形
function qf_get_new_imagetag($imgtag, $url, $save, $uploads_path, $uploads_url) {
    $save = str_replace($uploads_path, $uploads_url, $save);
    $imgtag = str_replace($url, $save, $imgtag);
    //echo 'imgtag = ' . $imgtag;
    
    // イメージタグ内から width、height 要素を削除
    $tmp_imgtag = split(' ', $imgtag);
    foreach ($tmp_imgtag as $tmp_imgurl) {
        if(preg_match("/([Ww][Ii][Dd][Tt][Hh])([\s\t\n\r]*)=([\s\t\n\r]*)/" , $tmp_imgurl)) {
            $imgtag = str_replace($tmp_imgurl, "", $imgtag);
        } else if (preg_match("/([Hh][Ee][Ii][Gg][Hh][Tt])([\s\t\n\r]*)=([\s\t\n\r]*)/" , $tmp_imgurl)){
            $imgtag = str_replace($tmp_imgurl, "", $imgtag);
        }
    }
    $imgtag = preg_replace('/(alignleft|aligncenter|alignright)/i', '', $imgtag);
    //echo 'imgtag = ' . $imgtag;
    return $imgtag;
}


// 設定取得
function qf_get_parameter($gt_settings, $default_image, $random_image, $not_use_default_image, $use_attachment_image) {
    // WP設定データを取得
    // 設定文字列のデコード処理
    $pairs = split("&", $gt_settings);
    $gt_settings = "";
    
    // 受け取った引数「$gt_settings」をデコード
    foreach ($pairs as $data) {
        $data = split("=", $data);
        $gt_settings[$data[0]] = $data[1];
    }
    
    // 設定値型変換・初期設定
    if (is_null($gt_settings['tag'])) { $gt_settings['tag'] = 1;}
    $gt_settings['tag'] = (int)$gt_settings['tag'];
    $gt_settings['global'] = (int)$gt_settings['global'];
    $gt_settings['num'] = (int)$gt_settings['num'];
    $gt_settings['width'] = (int)$gt_settings['width'];
    $gt_settings['height'] = (int)$gt_settings['height'];
    $gt_settings['crop_w'] = (int)$gt_settings['crop_w'];
    $gt_settings['crop_h'] = (int)$gt_settings['crop_h'];
    $gt_settings['find'] = (string)$gt_settings['find'];
    //$gt_settings['default_image'] = (string)$default_image;
    if (!array_key_exists('default_image', $gt_settings)) {
        $gt_settings['default_image'] = $default_image;
    }
    if (!array_key_exists('random_image', $gt_settings)) {
        $gt_settings['random_image'] = $random_image;
    }
    if (!array_key_exists('not_use_default_image', $gt_settings)) {
        $gt_settings['not_use_default_image'] = $not_use_default_image;
    }
    if (!array_key_exists('use_attachment_image', $gt_settings)) {
        $gt_settings['use_attachment_image'] = $use_attachment_image;
    }
    if ($gt_settings['tag'] != 0 && gettype($gt_settings['tag']) != integer) { $gt_settings['tag'] = 1; }
    if (gettype($gt_settings['num']) != integer) { $gt_settings['num'] = 0; }
    if (gettype($gt_settings['global']) != integer) { $gt_settings['global'] = 0; }
    if (gettype($gt_settings['width']) != integer) { $gt_settings['width'] = 0; }
    if (gettype($gt_settings['height']) != integer) { $gt_settings['height'] = 0; }
    if (gettype($gt_settings['crop_w']) != integer) { $gt_settings['crop_w'] = 0; }
    if (gettype($gt_settings['crop_h']) != integer) { $gt_settings['crop_h'] = 0; }
    
    return $gt_settings;
}

// 保存先を設定 external link
function qf_get_savepath_external_link($link_url, $uploads_path, $append_text, $basic_auth, $folder_name, $width, $height, $crop_w, $crop_h) {
    
    // URLを保存先パスに変換
    $bname = str_replace('http://', '', $link_url);
    $bname = str_replace('https://', '', $bname);
    $bname = preg_replace('/[\/\~\:\$\&]/', '', $bname);
    $bname .= '.png';
    //echo 'bname = '. $bname;
    
    // リモートファイルのサイズ取得
    //$size = qf_get_remotefilesize($link_url, $basic_auth);//遅くなるので中止
    $size = 1;

    // 保存ファイル名定義
    $file = $uploads_path .  '/' . $folder_name."/".$width."-".$height."x".$crop_w."-".$crop_h."/".$append_text."_".$size."_".$bname;

    return $file;
}

// 他のサイトからコンテンツをダウンロード
function qf_get_site($url) {
    $timeout = 1;
    // 全てのエラー出力をオフにする
    $old_err_level = error_reporting(0);
    require_once('http_request.php');
    list($head, $c) = http_request($url, $timeout);
    // エラー出力を元に戻す
    error_reporting($old_err_level);

    if (preg_match('/charset=([a-zA-Z\-_0-9]*)/', $c, $cset)) {
        $code = $cset[1];
    } else {
        $code = 'auto';
    }
    $c = mb_convert_encoding($c, 'UTF-8', $code);
    
    // 記事ソースからイメージタグを抽出
    if (!preg_match_all('/<img[\s\t][^>]+>/i' , $c, $imgList)) {
        // イメージ要素が無い
        return;
    }

    // イメージソースをURLに変換
    $url = rtrim($url, '/');
    foreach ($imgList[0] as $value) {
        if (preg_match('/src=[\'\"](.*?)[\'\"]/i', $value, $src)) {
            if (preg_match('/^https?:\/\//', $src[1])) {
                $ret .= $value;
            } else {
                $img = $url . '/' . preg_replace('/^.\//', '', $src[1]);
                $ret .= str_replace($src[1], $img, $value);
            }
        }
    }
    return $ret;
}

/* 絵文字プラグインの画像を除外する */
function remove_emoji($imgList) {
    //print_r($imgList);
    $imga = array();
    $i = 0;
    foreach ($imgList[0] as $val) {
        if (!strstr($val, "emoji_plugin")) {
            //echo('val = ' . $val);
            $imga[0][$i] =  $val;
            $i++;
        }
    }
    return $imga;
}

// 添付画像を取得
function qf_get_attachment_image() {
    global $post;
    global $Qfgtwb_debug;

    // カスタムフィールドの全データー取得
    $cf = get_post_custom($post->ID);
    //print_r($cf);
    foreach ($cf as $k => $val) {
        // カスタムフィールドが添付なら url を取得
        $attid = get_post_meta($post->ID,$k,true);
        $url = wp_get_attachment_url($attid, 'large');
        if (!$url) { continue; }
        if (!preg_match('/\.(jpg|jpeg|gif|png)$/i', $url)) { continue; }
        $imga[0][] = '<img src="' . $url . '" />';
        if ($Qfgtwb_debug) {
            echo "<p>attid = $attid, url = $url</p>";
        }
    }
    return $imga;
}

// 指定箇所のイメージタグを取得
function qf_get_imagetag($content, $num, $find, $uploads_path, $global, $default_image, $random_image, $use_attachment_image) {
    
    // 記事ソースからイメージタグを抽出
    //if (!preg_match_all("/<([Ii][Mm][Gg])[\s\t][\"']*([^>]*)*>/" , $content, $imgList)) {
    preg_match_all("/<([Ii][Mm][Gg])[\s\t][\"']*([^>]*)*>/" , $content, $imga);

    $imgList = remove_emoji($imga);
    //print_r($imgList);
    if (!count($imgList) && $use_attachment_image) {
        // イメージ要素が無い場合、添付画像を探す
        $imgList = qf_get_attachment_image();
        //print_r($imgList);
    }
    if (!count($imgList)) {
        // イメージ要素が無い場合、デフォルト画像を出力
        return "<img src=\"".$default_image."\" />";
    }
    //}
    
    // 配列整形
    $new_imgList = array();
    foreach ($imgList[0] as $value) {
        //echo '[' . $content . ',' . $find . ',' . $value . ',' . $gloabl . ']';
        // 検索文字列指定がある場合、検索実行及び判定
        if (!$find == "" && !strstr($value, $find)) {
            next;
        } else if (!$global && !strstr($value, $uploads_path)) {
            // 外部リンク不許可の場合、スキップする
            next;
        } else {
            array_push($new_imgList, $value);
        }
    }

    // イメージタグ配列要素数を確認
    $count = count($new_imgList) - 1;
    
    if ($count < 0) {
        // イメージ要素が無い場合、デフォルト画像を出力
        return "<img src=\"".$default_image."\" />";
    }
    
    // 配列要素の範囲外の値は調整する
    if ($count < $num || $num < 0) {
        $num = $count;
    }

    // ランダムに画像を選ぶ
    //echo 'random_image = ' .$random_image;
    if ($random_image) {
        $num = rand(0, $count);
    }
    
    // 指定箇所のIMGタグを取り出す
    $imgtag = $new_imgList[$num];
    
    return $imgtag;
}


// 指定箇所のイメージパスを取得
function qf_get_imagepath($imgtag) {
    // イメージタグ内からパスのみを取り出す
    $tmp_imgtag = split(' ', $imgtag);
    foreach ($tmp_imgtag as $tmp_imgurl) {
        if(preg_match("/([Ss][Rr][Cc])([\s\t\n\r]*)=([\s\t\n\r]*)/" , $tmp_imgurl)) {
            $tmp_imgurl = preg_replace("/[\s\t\n\r\'\"]/", "", $tmp_imgurl);
            $imgurl = preg_replace("/([Ss][Rr][Cc])=/", "", $tmp_imgurl);
        }
    }
    
    return $imgurl;
}


// サムネイルデータ生成
function qf_make_thumbnail($url, $width, $height, $basic_auth) {
    // イメージリソース取得
    $image = makerc_image($url, $basic_auth);
    
    // イメージのリサイズ処理
    $image = imageresize($image, $width, $height, true);
    
    return $image;
}


// 保存先を設定
function qf_get_savepath($domain_name, $url, $uploads_path, $append_text, $basic_auth, $folder_name, $width, $height, $crop_w, $crop_h) {
    $bname = basename(str_replace($domain_name, '', $url));
    $bname = preg_replace('/\.[^\.]+$/', '.png', $bname);
    // リモートファイルのサイズ取得
    $size = qf_get_remotefilesize($url, $basic_auth);

    // 保存ファイル名定義
    $file = $uploads_path .  '/' . $folder_name."/".$width."-".$height."x".$crop_w."-".$crop_h."/".$append_text."_".$size."_".$bname;

    return $file;
}

// イメージデータクロップ実行
function qf_make_cropimage($image, $crop_w, $crop_h) {
    // クロップ指定が無ければ処理を終える
    if ($crop_w == 0 && $crop_h == 0) {
        return $image;
    }

    // イメージの縦幅・横幅取得
    $width = @imagesx($image);
    $height = @imagesy($image);

    // クロップサイズ計算
    $left = ($width - $crop_w) / 2;
    $right = $left;
    $top = ($height - $crop_h) / 2;
    $bottom = $top;
    
    if ($crop_w == 0) {
        $left = 0;
        $right = 0;
    }
    
    if ($crop_h == 0) {
        $top = 0;
        $bottom = 0;
    }

    // イメージのクロップ処理
    $image = qf_imagecrop($image, $left, $top, $right, $bottom);
    
    return $image;
}


// 保存先ディレクトリ確認・生成
function qf_check_savedir($save) {
    $save_dir = dirname($save);

    if (is_dir($save_dir)) {
        return true;
    } else {
        if (!@mkdir($save_dir, 0777)) {
            qf_check_savedir($save_dir);
            @mkdir($save_dir, 0777);
        }
    }
    
    return true;
}


// 対象ファイルとキャッシュを比較
function qf_check_samefile($save, $url, $append_text, $basic_auth) {
    //キャッシュファイルサイズ取得
    $c_size = str_replace($append_text, "", str_replace("_".basename($url), "", basename($save)));

    // リモートファイルのサイズ取得
    $r_size = qf_get_remotefilesize($url, $basic_auth);

    // ファイルが同一でなければ偽を返す
    if (!$c_size == $r_size) {
        return false;
    } else {
        return true;
    }
}

// サムネイル保存
// ie6 の jpg バグ回避の為必ず png で保存する
function qf_save_thumbnail($save, $image, $format) {
    global $Qfgtwb_debug;
    
    $format = 3; // png
    
    // イメージ保存
    switch ($format) {
      case 1:
        // GIFを出力
        return imagegif($image, $save);
        break;
      case 2:
        // JPEGを出力
        return imagejpeg($image, $save);
        break;
      case 3:
        // PNGを出力
        return imagepng($image, $save);
        break;
      default:
        // 念の為...
        echo '<p>qf-getthumb-wb: qf_save_thumbnail: image format not found.</p>';
        return false;
    }
    
    if ($Qfgtwb_debug) {
        echo '<p>qf-getthumb-wb: qf_save_thumbnail: output image failed: format = ' . $format . ', and return false.</p>';
    }
    return false;
}


// サムネイルファイルのフルパスを返す
function qf_get_thumburl($save, $uploads_path, $uploads_url) {
    $save = str_replace($uploads_path, $uploads_url, $save);
    return $save;
}


// リモートファイルのサイズ取得
// Basic認証
// file_get_contents('http://(ユーザーID):(パスワード)@www.example.com/example.html');
function qf_get_remotefilesize($url, $basic_auth) {
    // support basic auth
    if ($basic_auth && (strpos($url, 'http://') == 0 || strpos($url, 'https://') == 0)) {
      $f = explode('//', $url, 2);
      $url = $f[0].'//'.$basic_auth.'@'.$f[1];
    }

    $fileData = file_get_contents($url);
    $fileSize = strlen($fileData);   // byte単位
    return $fileSize;
    /*
    $sch = parse_url($url, PHP_URL_SCHEME);

    $headers = get_headers($url, 1);
    
    if ((!array_key_exists("Content-Length", $headers)))
      return false;
    
    return $headers["Content-Length"];
    */
}


function qf_get_thumb_wb_options() {
    if (function_exists('add_options_page')) {
        add_options_page('qf_get_thumb_wb', 'QF-GetThumb-wb', 8, basename(__FILE__), 'qf_get_thumb_wb_options_subpanel');
    }
}


// 初期設定ロード
function qf_load_default() {
    $settings['folder_name'] = 'qfgtwb';
    $settings['append_text'] = 'qfgtwb';

    $settings['basic_auth'] = '';

    $cwd = getcwd();
    $cwd = str_replace('wp-admin', 'wp-content/uploads', $cwd);
    $url = get_settings('siteurl') . '/wp-content/uploads';
    $defimg = get_settings('siteurl') . '/wp-content/plugins/qf-getthumb-wb/default_image.png';
    $settings['domain_name'] = get_settings('siteurl');//$_SERVER{'SERVER_NAME'};
    $settings['uploads_url'] = $url;//$_SERVER{'SERVER_NAME'};
    $settings['uploads_path'] = $cwd;//'http://'.$_SERVER{'SERVER_NAME'}.'/';
    $settings['default_image'] = $defimg; //str_replace($_SERVER{'DOCUMENT_ROOT'}
    $settings['random_image'] = '';
    $settings['not_use_default_image'] = '';
    $settings['use_attachment_image'] = '';
    return $settings;
}


// 設定画面
function qf_get_thumb_wb_options_subpanel() {
    if (isset($_POST['info_update'])) {
        $new_options = array(
            'uploads_url' => $_POST['uploads_url'],
            'uploads_path' => $_POST['uploads_path'],
            'domain_name' => $_POST['domain_name'],
            'default_image' => $_POST['default_image'],
            'folder_name' => $_POST['folder_name'],
            'append_text' => $_POST['append_text'],
	    'basic_auth' => $_POST['basic_auth'],
            'random_image' => $_POST['random_image'],
            'not_use_default_image' => $_POST['not_use_default_image'],
            'use_attachment_image' => $_POST['use_attachment_image']
            );

        // 設定を更新
        update_option('qf_get_thumb_wb_settings', $new_options);

        // 設定保存メッセージ出力
        echo "<div class=\"updated\">\n";
        if (!empty($update_error)) {
            echo "<strong>Update error:</strong>".$update_error;
        } else {
            echo "<strong>設定が保存されました。</strong>\n";
        }
        echo "</div>\n";
        
    } else if (isset($_POST['load_default'])) {
        // 設定初期化
        $new_options = qf_load_default();
        update_option('qf_get_thumb_wb_settings',$new_options);
        
        // 初期化完了メッセージ出力
        echo "<div class=\"updated\">\n";
        echo "<strong>設定を初期化しました。</strong>\n";
        echo "</div>\n";
    }
    
    $qf_get_thumb_wb_settings = get_option('qf_get_thumb_wb_settings');
    
    ?>
<div class=wrap>
  <form method="post">
    <h2><?php _e('QF-GetThumb-wb Options', 'wpqfgtwb'); ?></h2>
    <p>※ QF-GetThumb と同時に有効にしないでください.</p>
    <fieldset name="options">
      <table cellpadding="2" cellspacing="0" width="100%">
       <tr>
         <td><strong><?php _e('Domain name', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="domain_name" value="<?php echo $qf_get_thumb_wb_settings['domain_name']; ?>" size="80%" /></td>
       </tr>
       <tr>
         <td><strong><?php _e('Uploads URL', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="uploads_url" value="<?php echo $qf_get_thumb_wb_settings['uploads_url']; ?>" size="80%" /></td>
       </tr>
       <tr>
         <td><strong><?php _e('Uploads Path', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="uploads_path" value="<?php echo $qf_get_thumb_wb_settings['uploads_path']; ?>" size="80%" /></td>
       </tr>
       <tr>
         <td><strong><?php _e('Default image', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="default_image" value="<?php echo $qf_get_thumb_wb_settings['default_image']; ?>" size="80%" /></td>
       </tr>
       <tr>
         <td><strong><?php _e('Select random image', 'wpqfgtwb'); ?></strong></td>
         <td><input type="checkbox" name="random_image" value="checked" <?php echo $qf_get_thumb_wb_settings['random_image']; ?> /> random_image=1 or 0</td>
       </tr>
       <tr>
         <td><strong><?php _e('Not use default image', 'wpqfgtwb'); ?></strong></td>
         <td><input type="checkbox" name="not_use_default_image" value="checked" <?php echo $qf_get_thumb_wb_settings['not_use_default_image']; ?> /> not_use_default_image=1 or 0</td>
       </tr>
       <tr>
         <td><strong><?php _e('Use attachment image', 'wpqfgtwb'); ?></strong></td>
         <td><input type="checkbox" name="use_attachment_image" value="checked" <?php echo $qf_get_thumb_wb_settings['use_attachment_image']; ?> /> use_attachment_image=1 or 0</td>
       </tr>
       <tr>
         <td><strong><?php _e('Save folder', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="folder_name" value="<?php echo $qf_get_thumb_wb_settings['folder_name']; ?>"  size="10" /></td>
       </tr>
       <tr>
         <td><strong><?php _e('Apend text', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="append_text" value="<?php echo $qf_get_thumb_wb_settings['append_text']; ?>"  size="10" /></td>
       </tr>
       <tr>
         <td><strong><?php _e('Basic Authentication', 'wpqfgtwb'); ?></strong></td>
         <td><input type="text" name="basic_auth" value="<?php echo $qf_get_thumb_wb_settings['basic_auth']; ?>"  size="20%" /><?php _e('Input format: ID:PASSWORD (PHP function file_get_contents use this ID and Password)', 'wpqfgtwb'); ?></td>
       </tr>
     </table>
   </fieldset>
   <div class="submit">
    <input type="submit" name="info_update" value="<?php _e('Save settings', 'wpqfgtwb'); ?>" />
    <input type="submit" name="load_default" value="<?php _e('Load default settings', 'wpqfgtwb'); ?>" />
    </div>
  </form>
</div>
<?php
}
?>
