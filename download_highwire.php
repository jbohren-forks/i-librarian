<?php
include_once 'data.php';

if (isset($_SESSION['auth'])) {

    include_once 'functions.php';

    $proxy_name = '';
    $proxy_port = '';
    $proxy_username = '';
    $proxy_password = '';

    if (isset($_SESSION['connection']) && ($_SESSION['connection'] == "autodetect" || $_SESSION['connection'] == "url")) {
        if (!empty($_GET['proxystr'])) {
            $proxy_arr = explode(';', $_GET['proxystr']);
            foreach ($proxy_arr as $proxy_str) {
                if (stripos(trim($proxy_str), 'PROXY') === 0) {
                    $proxy_str = trim(substr($proxy_str, 6));
                    $proxy_name = parse_url($proxy_str, PHP_URL_HOST);
                    $proxy_port = parse_url($proxy_str, PHP_URL_PORT);
                    $proxy_username = parse_url($proxy_str, PHP_URL_USER);
                    $proxy_password = parse_url($proxy_str, PHP_URL_PASS);
                    break;
                }
            }
        }
    } else {
        if (isset($_SESSION['proxy_name']))
            $proxy_name = $_SESSION['proxy_name'];
        if (isset($_SESSION['proxy_port']))
            $proxy_port = $_SESSION['proxy_port'];
        if (isset($_SESSION['proxy_username']))
            $proxy_username = $_SESSION['proxy_username'];
        if (isset($_SESSION['proxy_password']))
            $proxy_password = $_SESSION['proxy_password'];
    }

########## reset button ##############

    if (isset($_GET['newsearch'])) {

        while (list($key, $value) = each($_SESSION)) {

            if (strstr($key, 'session_download_highwire'))
                unset($_SESSION[$key]);
        }
    }

########## save button ##############

    if (isset($_GET['save']) && $_GET['save'] == '1' && !empty($_GET['highwire_searchname'])) {

        database_connect($database_path, 'library');

        $stmt = $dbHandle->prepare("DELETE FROM searches WHERE userID=:user AND searchname=:searchname");
        $stmt->bindParam(':user', $user, PDO::PARAM_STR);
        $stmt->bindParam(':searchname', $searchname, PDO::PARAM_STR);

        $stmt2 = $dbHandle->prepare("INSERT INTO searches (userID,searchname,searchfield,searchvalue) VALUES (:user,:searchname,:searchfield,:searchvalue)");
        $stmt2->bindParam(':user', $user, PDO::PARAM_STR);
        $stmt2->bindParam(':searchname', $searchname, PDO::PARAM_STR);
        $stmt2->bindParam(':searchfield', $searchfield, PDO::PARAM_STR);
        $stmt2->bindParam(':searchvalue', $searchvalue, PDO::PARAM_STR);

        $dbHandle->beginTransaction();

        $user = $_SESSION['user_id'];
        $searchname = "highwire#" . $_GET['highwire_searchname'];

        $stmt->execute();

        reset($_GET);

        while (list($key, $value) = each($_GET)) {

            if (!empty($key) && strstr($key, "highwire_")) {

                $user = $_SESSION['user_id'];
                $searchname = "highwire#" . $_GET['highwire_searchname'];

                if ($key != "highwire_searchname") {

                    $searchfield = $key;
                    $searchvalue = $value;

                    $stmt2->execute();
                }
            }
        }

        $dbHandle->commit();
    }

########## load button ##############

    if (isset($_GET['load']) && $_GET['load'] == '1' && !empty($_GET['saved_search'])) {

        database_connect($database_path, 'library');

        $stmt = $dbHandle->prepare("SELECT searchfield,searchvalue FROM searches WHERE userID=:user AND searchname=:searchname");
        $stmt->bindParam(':user', $user, PDO::PARAM_STR);
        $stmt->bindParam(':searchname', $searchname, PDO::PARAM_STR);

        $user = $_SESSION['user_id'];
        $searchname = "highwire#" . $_GET['saved_search'];

        $stmt->execute();

        reset($_SESSION);

        while (list($key, $value) = each($_SESSION)) {

            if (strstr($key, 'session_download_highwire'))
                unset($_SESSION[$key]);
        }

        $_GET = array();
        $_GET['load'] = 'Load';

        $_GET['highwire_searchname'] = substr($searchname, 9);

        while ($search = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $_GET{$search['searchfield']} = $search['searchvalue'];
        }
    }

########## delete button ##############

    if (isset($_GET['delete']) && $_GET['delete'] == '1' && !empty($_GET['saved_search'])) {

        database_connect($database_path, 'library');

        $stmt = $dbHandle->prepare("DELETE FROM searches WHERE userID=:user AND searchname=:searchname");
        $stmt->bindParam(':user', $user, PDO::PARAM_STR);
        $stmt->bindParam(':searchname', $searchname, PDO::PARAM_STR);

        $user = $_SESSION['user_id'];
        $searchname = "highwire#" . $_GET['saved_search'];

        $stmt->execute();

        while (list($key, $value) = each($_SESSION)) {

            if (strstr($key, 'session_download_highwire'))
                unset($_SESSION[$key]);
        }
        die();
    }

########## main body ##############

    $microtime1 = microtime(true);

    reset($_GET);

    while (list($key, $value) = each($_GET)) {

        if (!empty($_GET[$key]))
            $_SESSION['session_download_' . $key] = $value;
    }

    if (isset($_GET['highwire_searchname']))
        $_SESSION['session_download_highwire_searchname'] = $_GET['highwire_searchname'];

########## register variables ##############

    $parameter_string = '';

    if (!isset($_GET['from'])) {
        $from = '1';
    } else {
        $from = intval($_GET['from']);
    }

    $j = $from;

    $url_array = array();
    reset($_GET);

    while (list($key, $value) = each($_GET)) {

        if ($key != 'from')
            $url_array[] = "$key=" . urlencode($value);
    }

    $url_string = join("&", $url_array);

########## prepare highwire query ##############

    $query_string = '';

    if (!empty($_GET['highwire_query'])) {

        $_GET['highwire_query'] = str_replace("\"", "\\\"", $_GET['highwire_query']);

        if ($_GET['highwire_selection'] == 'all') {

            $query_string = urlencode("\"$_GET[highwire_query]\"");
        } elseif ($_GET['highwire_selection'] == 'au') {

            $query_string = urlencode("dc.creator =\"$_GET[highwire_query]\"");
        } elseif ($_GET['highwire_selection'] == 'ti') {

            $query_string = urlencode("title =\"$_GET[highwire_query]\"");
        } elseif ($_GET['highwire_selection'] == 'abs') {

            $query_string = urlencode("dc.description =\"$_GET[highwire_query]\"");
        }
    }

########## search highwire ##############

    if (!empty($query_string) && empty($_GET['load']) && empty($_GET['save']) && empty($_GET['delete'])) {

        ############# caching ################

        $cache_name = cache_name();
        $cache_name .= '_download';
        $db_change = database_change(array(
            'library'
        ));
        cache_start($db_change);
        
        ########## search highwire ##############

        $request_url = "http://highwire.stanford.edu/cgi/sru?version=1.1&operation=searchRetrieve&query=$query_string&startRecord=" . ($from - 1) . "&sendit=Search";

        $xml = proxy_simplexml_load_file($request_url, $proxy_name, $proxy_port, $proxy_username, $proxy_password);

        if ($xml === false)
            die('Error! I, Librarian could not connect with an external web service. This usually indicates that you access the Web through a proxy server.
            Enter your proxy details in Tools->Settings. Alternatively, the external service may be temporarily down. Try again later.');
    }

########## display search result summaries ##############

    if (isset($xml)) {

        print '<div style="padding:2px;font-weight:bold">Highwire search';

        if (!empty($_SESSION['session_download_highwire_searchname']))
            print ': ' . htmlspecialchars($_SESSION['session_download_highwire_searchname']);

        print '</div>';

        $totalresults = $xml->xpath('//srw:numberOfRecords');
        if (isset($totalresults[0]))
            $count = $totalresults[0];

        if (!empty($count) && $count > 0) {

            $maxfrom = $from + 9;
            if ($maxfrom > $count)
                $maxfrom = $count;

            $microtime2 = microtime(true);
            $microtime = $microtime2 - $microtime1;
            $microtime = sprintf("%01.1f seconds", $microtime);

            print '<table cellspacing="0" class="top"><tr><td class="top" style="width: 20%">';

            print '<div class="ui-state-highlight ui-corner-top' . ($from == 1 ? ' ui-state-disabled' : '') . '" style="float:left;margin-left:2px;width:26px">'
                    . ($from == 1 ? '' : '<a class="navigation" href="' . htmlspecialchars('download_highwire.php?' . $url_string . '&from=1') . '" style="display:block;width:26px">') .
                    '<span class="ui-icon ui-icon-seek-first" style="margin-left:5px"></span>'
                    . ($from == 1 ? '' : '</a>') .
                    '</div>';

            print '<div class="ui-state-highlight ui-corner-top' . ($from == 1 ? ' ui-state-disabled' : '') . '" style="float:left;margin-left:2px">'
                    . ($from == 1 ? '' : '<a class="navigation" href="' . htmlspecialchars('download_highwire.php?' . $url_string . '&from=' . ($from - 10)) . '" style="color:black;display:block;width:100%">') .
                    '<span class="ui-icon ui-icon-triangle-1-w" style="float:left"></span>Back&nbsp;&nbsp;'
                    . ($from == 1 ? '' : '</a>') .
                    '</div>';

            print '</td><td class="top" style="text-align: center">';

            print "Items $from - $maxfrom of $count in $microtime.";

            print '</td><td class="top" style="width: 20%">';

            (($count % 10) == 0) ? $lastpage = 1 + $count - 10 : $lastpage = 1 + $count - ($count % 10);

            print '<div class="ui-state-highlight ui-corner-top' . ($count >= $from + 10 ? '' : ' ui-state-disabled') . '" style="float:right;margin-right:2px;width:26px">'
                    . ($count >= $from + 10 ? '<a class="navigation" href="' . htmlspecialchars('download_highwire.php?' . $url_string . '&from=' . $lastpage) . '" style="display:block;width:26px">' : '') .
                    '<span class="ui-icon ui-icon-seek-end" style="margin-left:5px"></span>'
                    . ($count >= $from + 10 ? '</a>' : '') .
                    '</div>';

            print '<div class="ui-state-highlight ui-corner-top' . ($count >= $from + 10 ? '' : ' ui-state-disabled') . '" style="float:right;margin-right:2px">'
                    . ($count >= $from + 10 ? '<a class="navigation" href="' . htmlspecialchars("download_highwire.php?$url_string&from=" . ($from + 10)) . '" style="color:black;display:block;width:100%">' : '') .
                    '<span class="ui-icon ui-icon-triangle-1-e" style="float:right"></span>&nbsp;&nbsp;Next'
                    . ($count >= $from + 10 ? '</a>' : '') .
                    '</div>';

            print '<div class="ui-state-highlight ui-corner-top pgdown" style="float: right;width: 5em;margin-right:2px">PgDown</div>';

            print '</td></tr></table>';

            print '<div class="alternating_row">';

            $records = $xml->xpath('//srw:searchRetrieveResponse/srw:records/srw:record/srw:recordData');
            $records = array_splice($records, 0, 10);

            foreach ($records as $record) {

                $prism = $record->children('http://prismstandard.org/namespaces/1.2/basic/');

                $add[$j - 1]['secondary_title'] = (string) $prism->publicationName;
                $add[$j - 1]['volume'] = (string) $prism->volume;
                $add[$j - 1]['issue'] = (string) $prism->number;
                $add[$j - 1]['pages'] = (string) $prism->startingPage . '-' . (string) $prism->endingPage;

                $j = $j + 1;
            }

            $j = $from;

            $records = null;
            $record = null;
            $records = $xml->xpath('//srw:searchRetrieveResponse/srw:records/srw:record/srw:recordData/dc:dc');
            $records = array_splice($records, 0, 10);

            database_connect($database_path, 'library');

            foreach ($records as $record) {

                $doi = '';
                $title = '';
                $names = '';
                $name_array = array();
                $secondary_title = '';
                $volume = '';
                $issue = '';
                $pages = '';
                $new_authors = array();
                $array = array();

                $dc = $record->children('http://pulr.org/dc/elements/1.1/');

                $title = $dc->title;
                $year = $dc->date;
                $doi = $dc->identifier;

                $name_array = array();

                foreach ($dc->contributor as $author) {

                    $author_array = explode(' ', $author);
                    $last = array_pop($author_array);
                    $first = join(' ', $author_array);
                    $name_array[] = $last . ', ' . $first;
                }

                if (isset($name_array))
                    $names = join("; ", $name_array);

                $secondary_title = $add[$j - 1]['secondary_title'];
                $volume = $add[$j - 1]['volume'];
                $issue = $add[$j - 1]['issue'];
                $pages = $add[$j - 1]['pages'];

                if (!empty($title)) {

                    ########## gray out existing records ##############

                    $title_query = $dbHandle->quote(substr($title, 0, -1) . "%");
                    $result_query = $dbHandle->query("SELECT id FROM library WHERE title LIKE $title_query AND length(title) <= length($title_query)+2 LIMIT 1");
                    $existing_id = $result_query->fetchColumn();

                    print '<div class="items">';

                    print '<div class="titles" style="margin-right:30px';

                    if ($existing_id['count(*)'] > 0)
                        print ';color: #999';

                    print '">' . $title . '</div>';

                    print '<table class="firstcontainer" style="width:100%"><tr><td class="items">';

                    print htmlspecialchars($secondary_title);

                    if ($year != '')
                        print " ($year)";

                    print '<div class="authors"><span class="author_expander ui-icon ui-icon-circlesmall-plus" style="float:left"></span><div>' . htmlspecialchars($names) . '</div></div>';

                    if (!empty($doi))
                        print '<a href="' . htmlspecialchars('http://dx.doi.org/' . urlencode($doi)) . '" target="_blank">Publisher Website</a>';

                    print '<td></tr></table>';

                    print '<div class="abstract_container" style="display:none">';

                    ##########	print results into table	##########

                    print '<form enctype="application/x-www-form-urlencoded" action="upload.php" method="POST" class="fetch-form">';

                    print '<table cellspacing="0" width="100%"><tr><td class="items">';

                    print '<div>';
                    if (!empty($secondary_title))
                        print htmlspecialchars($secondary_title);
                    if (!empty($year))
                        print " (" . htmlspecialchars($year) . ")";
                    if (!empty($volume))
                        print " " . htmlspecialchars($volume);
                    if (!empty($issue))
                        print " ($issue)";
                    if (!empty($pages))
                        print ": " . htmlspecialchars($pages);
                    print '</div>';

                    print '<div class="authors"><span class="author_expander ui-icon ui-icon-circlesmall-plus" style="float:left"></span><div>' . htmlspecialchars($names) . '</div></div>';

                    $array = explode(';', $names);
                    $array = array_filter($array);
                    if (!empty($array)) {
                        foreach ($array as $author) {
                            $array2 = explode(',', $author);
                            $last = trim($array2[0]);
                            $first = trim($array2[1]);
                            $new_authors[] = 'L:"' . $last . '",F:"' . $first . '"';
                        }
                        $names = join(';', $new_authors);
                    }

                    print '</td></tr>';

                    print '<tr><td><div class="abstract">';

                    !empty($abstract) ? print htmlspecialchars($abstract)  : print 'No abstract available.';

                    print '</div></td></tr><tr><td class="items">';
                    ?>
                    <input type="hidden" name="uid[]" value="">
                    <input type="hidden" name="url[]" value="">
                    <input type="hidden" name="doi" value="<?php if (!empty($doi)) print htmlspecialchars($doi); ?>">
                    <input type="hidden" name="authors" value="<?php if (!empty($names)) print htmlspecialchars($names); ?>">
                    <input type="hidden" name="title" value="<?php if (!empty($title)) print htmlspecialchars($title); ?>">
                    <input type="hidden" name="secondary_title" value="<?php if (!empty($secondary_title)) print htmlspecialchars($secondary_title); ?>">
                    <input type="hidden" name="year" value="<?php if (!empty($year)) print htmlspecialchars($year); ?>">
                    <input type="hidden" name="volume" value="<?php if (!empty($volume)) print htmlspecialchars($volume); ?>">
                    <input type="hidden" name="issue" value="<?php if (!empty($issue)) print htmlspecialchars($issue); ?>">
                    <input type="hidden" name="pages" value="<?php if (!empty($pages)) print htmlspecialchars($pages); ?>">
                    <input type="hidden" name="abstract" value="<?php print !empty($abstract) ? htmlspecialchars($abstract) : "No abstract available."; ?>">

                    <?php
                    ##########	print full text links	##########

                    print '<b>Full text options:</b><br>';

                    if (!empty($doi))
                        print '<a href="' . htmlspecialchars('http://dx.doi.org/' . urlencode($doi)) . '" target="_blank">Publisher Website</a>';

                    print '<br><button class="save-item">Save</button> <button class="quick-save-item">Quick Save</button>';

                    print '</td></tr></table></form>';

                    print '</div>';

                    print '<div class="save_container"></div>';

                    print '</div>';

                    if ($j < $from + 10 && $j < $maxfrom)
                        print '<div class="separator"></div>';

                    $j = $j + 1;
                }
            }

            $dbHandle = null;

            print '</div>';

            print '<table cellspacing="0" class="top"><tr><td class="top" style="width: 50%">';

            print '<div class="ui-state-highlight ui-corner-bottom' . ($from == 1 ? ' ui-state-disabled' : '') . '" style="float:left;margin-left:2px;width:26px">'
                    . ($from == 1 ? '' : '<a class="navigation" href="' . htmlspecialchars('download_highwire.php?' . $url_string . '&from=1') . '" style="display:block;width:26px">') .
                    '<span class="ui-icon ui-icon-seek-first" style="margin-left:5px"></span>'
                    . ($from == 1 ? '' : '</a>') .
                    '</div>';

            print '<div class="ui-state-highlight ui-corner-bottom' . ($from == 1 ? ' ui-state-disabled' : '') . '" style="float:left;margin-left:2px">'
                    . ($from == 1 ? '' : '<a class="navigation" href="' . htmlspecialchars('download_highwire.php?' . $url_string . '&from=' . ($from - 10)) . '" style="color:black;display:block;width:100%">') .
                    '<span class="ui-icon ui-icon-triangle-1-w" style="float:left"></span>Back&nbsp;&nbsp;'
                    . ($from == 1 ? '' : '</a>') .
                    '</div>';

            print '</td><td class="top" style="width: 50%">';

            print '<div class="ui-state-highlight ui-corner-bottom' . ($count >= $from + 10 ? '' : ' ui-state-disabled') . '" style="float:right;margin-right:2px;width:26px">'
                    . ($count >= $from + 10 ? '<a class="navigation" href="' . htmlspecialchars('download_highwire.php?' . $url_string . '&from=' . $lastpage) . '" style="display:block;width:26px">' : '') .
                    '<span class="ui-icon ui-icon-seek-end" style="margin-left:5px"></span>'
                    . ($count >= $from + 10 ? '</a>' : '') .
                    '</div>';

            print '<div class="ui-state-highlight ui-corner-bottom' . ($count >= $from + 10 ? '' : ' ui-state-disabled') . '" style="float:right;margin-right:2px">'
                    . ($count >= $from + 10 ? '<a class="navigation" href="' . htmlspecialchars("download_highwire.php?$url_string&from=" . ($from + 10)) . '" style="color:black;display:block;width:100%">' : '') .
                    '<span class="ui-icon ui-icon-triangle-1-e" style="float:right"></span>&nbsp;&nbsp;Next'
                    . ($count >= $from + 10 ? '</a>' : '') .
                    '</div>';

            print '<div class="ui-state-highlight ui-corner-bottom pgup" style="float:right;width:5em;margin-right:2px">PgUp</div>';

            print '</td></tr></table><br>';
        } else {
            print '<div style="position:relative;top:43%;left:40%;color:#bbbbbb;font-size:28px;width:200px"><b>No Items</b></div>';
        }

        ############# caching #############
        cache_store();
        
    } else {

########## input table ##############
        ?>
        <div style="text-align: left">
            <form enctype="application/x-www-form-urlencoded" action="download_highwire.php" method="GET" id="download-form">
                <table cellspacing="0" class="threed">
                    <tr>
                        <td style="border: 0px; background-color: transparent;width:23em">
                            <button id="download-search">Search</button>
                            <button id="download-reset">Reset</button>
                            <button id="download-clear">Clear</button>
                        </td>
                        <td style="border: 0px; background-color: transparent;text-align:right">
                            <a href="http://highwire.stanford.edu" target="_blank">HighWire Press</a>
                        </td>
                    </tr>
                    <?php
                    print ' <tr>
  <td class="threed">
  <select name="highwire_selection">
	<option value="all" ' . ((isset($_SESSION['session_download_highwire_selection']) && $_SESSION['session_download_highwire_selection'] == 'all') ? 'selected' : '') . '>full record</option>
	<option value="au" ' . ((isset($_SESSION['session_download_highwire_selection']) && $_SESSION['session_download_highwire_selection'] == 'au') ? 'selected' : '') . '>author</option>
	<option value="ti" ' . ((isset($_SESSION['session_download_highwire_selection']) && $_SESSION['session_download_highwire_selection'] == 'ti') ? 'selected' : '') . '>title</option>
	<option value="abs" ' . ((isset($_SESSION['session_download_highwire_selection']) && $_SESSION['session_download_highwire_selection'] == 'abs') ? 'selected' : '') . '>title and abstract</option>
  </select>
  </td>
  <td class="threed">
  <input type="text" name="highwire_query" value="' . htmlspecialchars((isset($_SESSION['session_download_highwire_query'])) ? $_SESSION['session_download_highwire_query'] : '') . '" size="65">
  </td>
 </tr>';
                    ?>
                    <tr>
                        <td class="threed">
                            Save search as:
                        </td>
                        <td class="threed">
                            <input type="text" name="highwire_searchname" size="35" style="float:left;width:50%" value="<?php print isset($_SESSION['session_download_highwire_searchname']) ? htmlspecialchars($_SESSION['session_download_highwire_searchname']) : '' ?>">
                            &nbsp;<button id="download-save">Save</button>
                        </td>
                    </tr>
                </table>
            </form>
            &nbsp;<a href="http://highwire.stanford.edu/about/terms-of-use.dtl" target="_blank">Terms of Use</a>
        </div>
        <?php
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
    }
}
?>
<br>