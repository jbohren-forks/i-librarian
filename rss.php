<?php
include_once 'data.php';
include_once 'functions.php';
session_write_close();

$librarian_url = $url;

header("Content-Type: application/rss+xml; charset=UTF-8");

$rssfeed = '<?xml version="1.0" encoding="UTF-8"?>';
$rssfeed .= '<rss version="2.0">';
$rssfeed .= '<channel>';
$rssfeed .= '<title>I, Librarian RSS feed</title>';
$rssfeed .= '<link>'.$librarian_url.'</link>';
$rssfeed .= '<description>New articles in I, Librarian database</description>';
$rssfeed .= '<language>en-us</language>'.PHP_EOL;

database_connect($database_path, 'library');
$result = $dbHandle->query("SELECT id,title,abstract,addition_date FROM library ORDER BY id DESC LIMIT 100");
$dbHandle = null;

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    
    extract($row);
    $rssfeed .= '<item>';
    $rssfeed .= '<title>' . $title . '</title>';
    $rssfeed .= '<description>' . $abstract . '</description>';
    $rssfeed .= '<link>'.$librarian_url.'stable.php?id='.$id.'</link>';
    $rssfeed .= '<pubDate>' . date("D, d M Y H:i:s O", strtotime($addition_date)) . '</pubDate>';
    $rssfeed .= '</item>'.PHP_EOL;
}

$rssfeed .= '</channel>';
$rssfeed .= '</rss>';

echo $rssfeed;
?>
