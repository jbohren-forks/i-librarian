<?php
include_once 'data.php';
include_once 'functions.php';

if (!empty($_GET['database'])) {
    $database = $_GET['database'];
    $allowed_databases = array ('pubmed', 'pmc', 'nasaads', 'arxiv', 'jstor');
    if (!in_array($database, $allowed_databases)) die();
} else {
    die();
}

if (!empty($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
} else {
    die();
}

// CLEAN DOWNLOAD CACHE
$isapc = ini_get('apc.enabled');
if (!empty($isapc)) {
    foreach (new APCIterator('user', '/^'.$_SESSION['user_id'].'_file_.*_download$/', APC_ITER_KEY, 1000) as $item) {
        apc_delete($item['key']);
    }
} else {
    $clean_files = glob($temp_dir . DIRECTORY_SEPARATOR . 'lib_' . session_id(). DIRECTORY_SEPARATOR . $_SESSION['user_id'].'_file_*_download', GLOB_NOSORT);
    foreach ($clean_files as $clean_file) {
        if (is_file($clean_file) && is_writable($clean_file))
            @unlink($clean_file);
    }
}

database_connect($database_path, 'library');
$dbHandle->exec("CREATE TABLE IF NOT EXISTS flagged (id INTEGER PRIMARY KEY, userID INTEGER NOT NULL, database TEXT NOT NULL, uid TEXT NOT NULL, UNIQUE (userID,database,uid))");

if (!empty($_GET['empty'])) {
    $dbHandle->exec("DELETE FROM flagged WHERE userID=".$user_id." AND database='".$database."'");
    die();
}

if (!empty($_GET['uid'])) {

    $uid_query = $dbHandle->quote($_GET['uid']);

    $result = $dbHandle->query("SELECT id FROM flagged WHERE userID=".$user_id." AND database='".$database."' AND uid=".$uid_query." LIMIT 1");
    $relation = $result->fetchColumn();
    $result = null;

    if (!$relation) {
        //HOW MANY FLAGGED?
        $result = $dbHandle->query("SELECT count(*) FROM flagged WHERE userID=".$user_id." AND database='".$database."'");
        $flagged_count = $result->fetchColumn();
        $result = null;
        //FLAG IF < 100
        if($flagged_count > 99) die();
        $update = $dbHandle->exec("INSERT OR IGNORE INTO flagged (userID,database,uid) VALUES ($user_id,'".$database."',$uid_query)");
        if($update) echo 'added';
    } else {
        //UNFLAG
        $update = $dbHandle->exec("DELETE FROM flagged WHERE id=$relation");
        if($update) echo 'removed';
    }
} else {

    //RETURN FLAGGED AS JSON
    $result = $dbHandle->query("SELECT uid FROM flagged WHERE userID=".$user_id." AND database='".$database."'");
    $uid_list = $result->fetchAll(PDO::FETCH_COLUMN);
    $result = null;

    print json_encode($uid_list);
}

$dbHandle = null;
?>