<?php

$cacheFolder = __DIR__ . '/cache/press';
$resultFolder = __DIR__ . '/press';
if (!file_exists($cacheFolder)) {
    mkdir($cacheFolder, 0777, true);
}
if (!file_exists($resultFolder . '/xls_risk')) {
    mkdir($resultFolder . '/xls_risk', 0777, true);
}

if (!file_exists($resultFolder . '/list.json')) {
    $listPageUrl = 'http://www.cbc.gov.tw/lp.asp?CtNode=302&CtUnit=376&BaseDSD=33&mp=1&nowPage=1&pagesize=5000';
    $listPageFile = $cacheFolder . '/list';
    if (!file_exists($listPageFile)) {
        file_put_contents($listPageFile, file_get_contents($listPageUrl));
    }
    $listPage = file_get_contents($listPageFile);
    $pos = strpos($listPage, '<li>', strpos($listPage, '<div class="ollist">'));
    $posEnd = strpos($listPage, '</ul>', $pos);
    $listPage = substr($listPage, $pos, $posEnd - $pos);
    $lines = explode('</li>', $listPage);
    $result = array();
    foreach ($lines AS $line) {
        $data = array();
        $linePos = strpos($line, '[') + 1;
        $linePosEnd = strpos($line, ']', $linePos);
        $data['published'] = substr($line, $linePos, $linePosEnd - $linePos);
        if (!empty($data['published'])) {
            $linePos = strpos($line, 'ct.asp', $linePosEnd);
            $linePosEnd = strpos($line, '"', $linePos);
            $data['url'] = 'http://www.cbc.gov.tw/' . substr($line, $linePos, $linePosEnd - $linePos);
            $linePos = strpos($line, '>', $linePosEnd) + 1;
            $linePosEnd = strpos($line, '<', $linePos);
            $data['title'] = substr($line, $linePos, $linePosEnd - $linePos);
            $result[] = $data;
        }
    }
    file_put_contents($resultFolder . '/list.json', json_encode($result));
}

$pressItems = json_decode(file_get_contents($resultFolder . '/list.json'), true);

$fh = fopen($resultFolder . '/list_risk.csv', 'w');
fputcsv($fh, array('日期', '標題', '新聞稿網址', '檔案網址', '存放位置'));
foreach ($pressItems AS $pressItem) {
    if (false !== strpos($pressItem['title'], '本國銀行國家風險統計')) {
        $cachedItemFile = $cacheFolder . '/' . md5($pressItem['url']);
        if (!file_exists($cachedItemFile)) {
            file_put_contents($cachedItemFile, file_get_contents($pressItem['url']));
        }
        $cachedItem = file_get_contents($cachedItemFile);
        $pressItem['files'] = array();
        $cachedItemLower = strtolower($cachedItem);
        $pos = strpos($cachedItemLower, '.xls');
        while (false !== $pos) {
            while (substr($cachedItem, $pos, 1) !== '"' && substr($cachedItem, $pos, 1) !== '\'') {
                --$pos;
            }
            switch (substr($cachedItem, $pos, 1)) {
                case '\'':
                    ++$pos;
                    $posEnd = strpos($cachedItem, '\'', $pos);
                    break;
                case '"':
                    ++$pos;
                    $posEnd = strpos($cachedItem, '"', $pos);
                    break;
            }
            $xlsFile = substr($cachedItem, $pos, $posEnd - $pos);
            if (substr($xlsFile, 0, 1) !== '/') {
                $xlsFile = '/' . $xlsFile;
            }
            $localFile = $resultFolder . '/xls_risk/' . md5($xlsFile) . '.xls';
            if (!file_exists($localFile)) {
                file_put_contents($localFile, file_get_contents('http://www.cbc.gov.tw' . $xlsFile));
            }
            $xlsNote = '';
            if (filesize($localFile) === 0) {
                unlink($localFile);
                $xlsNote = '[無法下載]';
            }
            fputcsv($fh, array($pressItem['published'], $pressItem['title'], $pressItem['url'], 'http://www.cbc.gov.tw' . $xlsFile . $xlsNote, 'xls_risk/' . md5($xlsFile) . '.xls'));
            $pos = strpos($cachedItemLower, '.xls', $posEnd);
        }
    }
}

fclose($fh);
