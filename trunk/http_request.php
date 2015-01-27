<?php

//list($head,$body) = httpRequest("http://www.yahoo.co.jp", 8);

function http_request($url, $timeout) {
    $purl = parse_url($url);
    
    if (isset($purl["query"])) {
        $purl["query"] = "?".$purl["query"];
    } else {
        $purl["query"] = "";
    }
    
    if (!isset($purl["port"])) {
        $purl["port"] = 80;
    }
    
    $request  = "GET ".$purl["path"].$purl["query"]." HTTP/1.0\r\n";
    $request .= "Host: ".$purl["host"]."\r\n";
    $request .= "\r\n";
    
    $fp = fsockopen($purl["host"], $purl["port"], $errno, $errstr, $timeout);
    socket_set_timeout($fp, 8);
    
    if (!$fp) {
        return false;
    }
    
    fputs($fp, $request);
    
    $response = "";
    while (!feof($fp)) {
        $response .= fgets($fp, 4096);
    }
    fclose($fp);
    
    $DATA = split("\r\n\r\n", $response, 2);
    
    return $DATA;
}
?>
