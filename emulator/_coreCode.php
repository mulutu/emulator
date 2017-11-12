<?php

date_default_timezone_set("Africa/Nairobi");
$ussdMenuUrl = "http://localhost:8080/mLMSussd/mo-receiver"; //put the menu url here  (the script calls the same way safaricom gateway invokes your ussd gateway
require_once '_include_sections.php';
require_once 'Database.php';

$db = new Database();

//print_r($_SESSION);
// load from session
if (isset($_SESSION["user_profileID"])) {
    $profileID = $_SESSION['user_profileID'];
    $msisdn = $_SESSION['user_MSISDN'];
    $names = $_SESSION['user_names'];
    $network = $_SESSION['user_network'];
}

$DEBUG = null;
$network_list = get_network();


function check_session() {
    if (!isset($_SESSION['user_profileID'])) {
        $_SESSION = null;
        die("<p>Invalid Session</p>");
        return false;
    }
    return true;
}

function create_session() {
    return rand(1000000000, 9999999999);
}

function home_page() {
    return array(
        "html" => render_homePage(),
        "debug" => ''
    );
}

function get_networkxxx($number = null) {
    $network_data = Array(
        'network_id' => 1,
        'networkName' => "Safaricom",
        'networkRule' => 123,
        'dateModified' => 1
    );
    $network_list[$network_data['network_id']] = $network_data;

    return $network_list;
}

function get_network($number = null) {
    global $db;
    $network_list = null;

    $q = "select * from networks";

    $categoryProducts = array();
    foreach ($db->query($q) as $networks_) {
        $categoryProducts[$networks_['network_id']] = $networks_;        
        if ($number != null) {
            $string = "/" . $networks_['networkRule'] . "/";
            if (preg_match($string, $number, $match)) {
                $network_list = $networks_;
            }
        }
    }    
    if ($number == null) {
        return $categoryProducts;
    } else {
        return $network_list;
    }
}

function get_networkOLD($number = null) {
    global $db;
    $network_list = null;
    //$result = selectSQL("select * from networks");
    $q = "select * from networks";
    $network_data = $db->fetch_row_assoc($q);
    //$result = fetch_row_assoc($q);

    $categoryProducts = array();
    foreach ($db->query($q) as $networks_) {
        $categoryProducts[$networks_['network_id']] = $networks_;
        
        if ($number != null) {
            $string = "/" . $network_data['networkRule'] . "/";
            if (preg_match($string, $number, $match)) {
                //print "match found";
                return $networks_;
            }
        }
    }
    
    if ($number == null) {
        return $network_list;
    } else {
        return $network_list[1];
    }
    
    print_r($categoryProducts);
    
    //while ($network_data = mysql_fetch_assoc($q)) {
    while ($network_data = $db->fetch_row_assoc($q)) {
        //print $network_data['networkRule'];
        $network_list[$network_data['network_id']] = $network_data;
//		if ($number != null)
//			echo "\npreg_match('{$network_data['networkRule']}', '$number') =  " . preg_match($network_data['networkRule'], $number);
        //if ($number != null and preg_match($network_data['networkRule'], $number) == 1)
        // Array ( [network_id] => 1 [networkName] => 1 [networkRule] => 123 [dateModified] => 1 ) 
        if ($number != null) {
            $string = "/" . $network_data['networkRule'] . "/";
            if (preg_match($string, $number, $match)) {
                //print "match found";
                return $network_data;
            }
        }
    }

    if ($number == null) {
        return $network_list;
    } else {
        return $network_list[1];
    }
}

function dial_page($dialNumber = "*360#") {
    check_session();
    $_SESSION['ussd'] = null;

    return array(
        'dial_number' => $dialNumber,
        'html' => render_dialPage($dialNumber),
        "debug" => ''
    );
}

function calllog_page() {
    return array(
        'html' => render_callLogPage(),
        "debug" => ''
    );
}

function make_call($dialNumber) {
    global $DEBUG;
    check_session();
    $_SESSION['ussd']['session-id'] = create_session();
    $_SESSION['ussd']['servicecode'] = $dialNumber;
    $_SESSION['ussd']['servicecommand'] = $dialNumber;
    $_SESSION['ussd']['opcode'] = 'BEG';

    $query = "INSERT INTO call_logs (profile_id, dialNumber, numberOfDials, dateCreated) VALUES (" . gSQLv($_SESSION['user_profileID']) . ", " . gSQLv($dialNumber) . ", 1, now())
		ON DUPLICATE KEY UPDATE numberOfDials=numberOfDials+1";
    updateSQL($query);

    $result = process_ussd($_SESSION['ussd']['session-id'], $dialNumber);
    $continue = true;
    if (strtoupper($_SESSION['ussd']['opcode']) == 'END')
        $continue = false;
    $DEBUG .= "\nResult_Len:" . strlen($result) . ";\nText:" . $result;

    return array(
        'dial_number' => $dialNumber,
        'html' => render_ussdSession($result, $dialNumber, $continue),
        "debug" => $_SESSION['ussd']['session-id'] . "\n" . htmlentities($DEBUG)
    );
}

function ussd_reply($response) {
    global $DEBUG;
    check_session();
    $dialNumber = $_SESSION['ussd']['servicecode'];

    $result = process_ussd($_SESSION['ussd']['session-id'], $response);
    $continue = true;
    if (strtoupper($_SESSION['ussd']['opcode']) == 'END')
        $continue = false;
    $DEBUG .= "\nResult_Len:" . strlen($result) . ";\nText:" . htmlentities($result);

    return array(
        'dial_number' => $dialNumber,
        'html' => render_ussdSession($result, $dialNumber, $continue),
        "debug" => $_SESSION['ussd']['session-id'] . "\n" . htmlentities($DEBUG)
    );
}

function ussd_clear() {
    global $DEBUG;
    check_session();
    $dialNumber = $_SESSION['ussd']['servicecode'];
    $_SESSION['ussd'] = null;

    return array(
        'dial_number' => $dialNumber,
        'html' => render_dialPage($dialNumber),
        "debug" => htmlentities($DEBUG)
    );
}

function send_sms($destaddr, $message) {
    $profileID = $_SESSION['user_profileID'];
    $msisdn = $_SESSION['user_MSISDN'];
    $names = $_SESSION['user_names'];
    $network = $_SESSION['user_network'];

    $query = "insert into messages (profile_id, sourceaddr, destaddr, messageContent, dateCreated, messageType, messageRead) values
		($profileID, '$msisdn', " . gSQLv($destaddr) . ", " . gSQLv($message) . ", now(), 0, 1)";
    $id = insertSQL($query);

    $result = process_sms($msisdn, $destaddr, $message, $id);
    return array(
        'html' => render_message_read($id),
        "debug" => $id . " - " . $result
    );
}

function process_sms($sourceaddr, $destaddr, $messageContent, $message_id) {
    global $DEBUG;
    $server_url = "http://localhost/sms.php";
    $url = $server_url . "?ORIGIN=http-in.emulator.sms&ID=$message_id&DLR=1&SOURCEADDR=" . rawurlencode($sourceaddr) . "&DESTADDR=" . rawurlencode($destaddr) . "&MESSAGE=" . rawurlencode($messageContent);
    //echo $url;

    $result = join("", file($url));
    $DEBUG .= $result;

    $query = "update messages set messageRead=1 where message_id = $message_id";
    updateSQL($query);

    return $result . "=" . $url;
}

function process_ussd($sessionID, $input) {
    global $DEBUG;
    global $ussdMenuUrl;
    $session_id = $sessionID;
    $msisdn = $_SESSION['user_MSISDN'];

    $opcode = "BEG";
    if (isset($_SESSION['ussd']['opcode'])) {
        $opcode = $_SESSION['ussd']['opcode'];
    }

    if (isset($_SESSION['ussd']['dialcode']) == "") {
        $_SESSION['ussd']['dialcode'] = $input;
    }
    $shortcode = str_replace('#', '*', $input);
//	if (isset($_SESSION['ussd']['servicecommand'])) {
//		$shortcode = str_replace('#', '', $_SESSION['ussd']['servicecommand']) . "*" . "$input";
//	}

    $shortcode = rawurlencode($shortcode);
//You chanage your url here for      
    $url = $ussdMenuUrl . "?MSISDN=$msisdn&SERVICE_CODE=" . rawurlencode($_SESSION['ussd']['dialcode']) . "&SESSIONID=$session_id&INPUT_STRING=" . rawurlencode($input) . "&netID=" . $_SESSION['user_network'];

    //print $url;
    try {
        ////open connection
        $ch = curl_init();
        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_MUTE,1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        //  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // For Debug mode; shows up any error encountered during the operation
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        //Enable it to return http errors
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        //set the timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLE_OPERATION_TIMEOUTED, 30);

        //new options
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        //curl_setopt($ch, CURLOPT_CAINFO, REQUEST_SSL_CERTIFICATE);
        //execute post


        $decodedresponse = json_decode(curl_exec($ch), true);
        //        $result = json_decode(curl_exec($ch), true);
        //close connection
        curl_close($ch);


        //$returnedString = join('', file($url));
        //$DEBUG .= $url . "\n" . $returnedString . "\n" . print_r($_SESSION, true);
        //we doing JSON
        //print_r($decodedresponse);

        $message = $decodedresponse['PAGE_STRING'];

        //$message = "xfdfdsfds";
        if (isset($decodedresponse['SESSION_ID']))
            $_SESSION['ussd']['session'] = $decodedresponse['SESSION_ID'];
        $_SESSION['ussd']['servicecommand'] = rawurldecode($shortcode);

        if (isset($decodedresponse['MNO_RESPONSE_SESSION_STATE'])) {
            $opcode = $decodedresponse['MNO_RESPONSE_SESSION_STATE'];
            $_SESSION['ussd']['opcode'] = $opcode;
        }

        if ($message == "") {
            $message = "Sorry server returned a blank message for your request\n\nDid not process request for " . htmlentities(rawurldecode($shortcode));
            $_SESSION['ussd']['opcode'] = 'END';
        }

        ///===================================================
        //decode response
    } catch (Exception $exc) {
        $DEBUG .= "EXCEPTION:" . $url . "\n" . $exc->getTraceAsString();
        $message = "Sorry we could not process your request at the moment. Please try again Later\n\nCannot process request for " . htmlentities(rawurldecode($shortcode));
        $_SESSION['ussd']['opcode'] = 'END';
    }
    return $message;
}

function get_time() {
    global $DEBUG, $network_list;
    $dbg = htmlentities($DEBUG);
    $DEBUG = "";
    return array(
        'time' => date("h:i A"),
        'debug' => $dbg,
        'date' => date("Y-M-d H:i:s"),
        'messages' => unread_messages(),
        'msisdn' => $_SESSION['user_MSISDN'] //,
            //'operator' => $network_list[$_SESSION['user_network']]['networkName']
    );
}

function unread_messages() {
    return "<a style='background-image:url(images/Samsung-home-screen.png);color:#F00;' onclick='return go_messaging()' title='Message from " . '254712345678' . "'>" . '1' . "</a>";
}

function isValidMobileNo($mobileNumber) {
    global $DEBUG;
    $number = trim($mobileNumber);
    return $number; //hack to remove tomorrow
// ------ check for nothing
    if ($number == '')
        return 0;
    if (!is_numeric($number))
        return 0;
// ------ check for leading 0
    $prfx = substr($number, 0, 1);
    if ($prfx == '0')
        $number = substr($number, 1);
// ------ check for mising country code and network
    $prfx = substr($number, 0, 2);
    if ($prfx >= 70 and $prfx <= 79) {
        $number = '254' . $number;
    }
// ------ check country prefix
    $prfx = substr($number, 0, 4);
    if ($prfx != '2547' || $prfx != '2601') {
        $DEBUG .= "<!-- #invalid_Number $mobileNumber [$number] wrong prefix -->\n";
        return 0;
    }
// ------ check that number is long enough
    if (strlen($number) != 12) {
        $DEBUG .= "<!-- #invalid_Number $mobileNumber [$number] wrong length -->\n";
        return 0;
    }
    return $number;
// ------ Valid number return it
}

function browser_page($url = '') {
    if ($url == "")
        $url = 'http://lipuka.mobi';

    return array(
        'html' => render_browser($url),
        "debug" => $url
    );
}

function messaging_home_page() {
    return array(
        'html' => render_messaging_home(),
        "debug" => ''
    );
}

function message_new_page() {
    return array(
        'html' => render_message_new(),
        "debug" => ''
    );
}

function message_read_page($messageID) {
    return array(
        'html' => render_message_read($messageID),
        "debug" => ''
    );
}

function profile_page($action) {
    return array(
        'html' => render_profile_page($action),
        "debug" => ''
    );
}

//==============================================================================
//==============================================================================
//==============================================================================
//==============================================================================

function microtime_f() {
    list ($msec, $sec) = explode(" ", microtime());
    return ((float) $msec + (float) $sec);
}

function connectDBx() {
    global $sql_conn;
    if ($sql_conn == null) {
        //$sqlite_connection = new sqlite3('mobile-profiles.sqlite', SQLITE3_OPEN_READWRITE);
        $sql_conn = new mysqli(sql_host, sql_user, sql_password, sql_db);
        //$sql_conn = mysqli_connect(sql_host, sql_user, sql_password) or loqError(fatalLogs, 'connect_DB', mysqli_error());
        //mysql_selectdb(sql_db, $sql_conn) or loqError(fatalLogs, 'select_DB', mysql_error());
        //$sqlite_connection->('mobile-profiles.sqlite', SQLITE3_OPEN_READWRITE);
    }
    return $sql_conn;
}

function connectDB() {
    global $PDO;
    if ($PDO == null) {
        try {
            $PDO = new PDO('mysql:host=' . sql_host . ';dbname=' . sql_db, sql_user, sql_password);
            $PDO->query('SET NAMES utf8');
        } catch (PDOException $exception) {
            die($exception->getMessage());
        }
    }
    return $PDO;
}

function fetch_row_assoc($statement) {
    //if ($dbLink == null)
    $PDO = connectDB();
    try {
        return $PDO->query($statement)->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        die($exception->getMessage());
    }
}

function fetch_all($statement, $fetch_style = PDO::FETCH_ASSOC) {
    if ($dbLink == null)
        $dbLink = connectDB();
    try {
        return $PDO->query($statement)->fetchAll($fetch_style);
    } catch (PDOException $exception) {
        die($exception->getMessage());
    }
}

function query($statement) {
    //if ($dbLink == null)
    $PDO = connectDB();
    try {
        return $PDO->query($statement);
    } catch (PDOException $exception) {
        die($exception->getMessage());
    }
}

function row_count($statement) {
    $PDO = connectDB();
    try {
        return $PDO->query($statement)->rowCount();
    } catch (PDOException $exception) {
        die($exception->getMessage());
    }
}

function selectSQL($query, $dbLink = null) {
    //if ($dbLink == null)
    //$dbLink = connectDB();

    $start = microtime_f();
    //$result = mysql_query("$query", $dbLink) or flog(fatalLogs, "selectSQL dberror | ".mysql_error($dbLink)." | on ".$query);
    //$result = mysql_query("$query", $dbLink) or loqError(fatalLogs, 'selectSQL', mysql_error($dbLink), $query);
    $stop = microtime_f();
    $time = $stop - $start;
    flog(sqlLogs, "selectSQL() |" . sprintf(" %01.4f ", $time) . "| $query");

    //return $result;
    return query($query);
}

function insertSQL($query, $dbLink = null) {
    if ($dbLink == null)
        $dbLink = connectDB();

    $start = microtime_f();
    //mysql_query("$query", $dbLink) or flog(fatalLogs, "insertSQL dberror | ".mysql_error($dbLink)." | on ".$query);
    mysql_query("$query", $dbLink) or loqError(fatalLogs, 'insertSQL', mysql_error($dbLink), $query);
    $stop = microtime_f();
    $time = $stop - $start;
    flog(sqlLogs, "insertSQL() |" . sprintf(" %01.4f ", $time) . "| $query");
    return mysql_insert_id($dbLink);
}

function updateSQL($query, $dbLink = null) {
    //if ($dbLink == null)
    //$dbLink = connectDB();

    $start = microtime_f();
    //mysql_query("$query", $dbLink) or flog(fatalLogs, "updateSQL dberror | ".mysql_error($dbLink)." | on ".$query);
    //mysql_query("$query", $dbLink) or loqError(fatalLogs, 'updateSQL', mysql_error($dbLink), $query);
    $stop = microtime_f();
    $time = $stop - $start;
    flog(sqlLogs, "updateSQL() |" . sprintf(" %01.4f ", $time) . "| $query");
    $result = query($query);

    return row_count($query);
    //return mysql_affected_rows($dbLink);
}

function loqError($log, $function, $error, $query = "") {
    flog($log, "$function | $error on | $query");
    if (($function == 'updateSQL' or $function == 'insertSQL')) { // and (stristr($query, 'outbound') !== FALSE and stristr($query, 'inboxRouter') !== FALSE))
        xflog("queriesToRun.log", $query);
    }
}

function gSQLv($theValue, $theType = 'text', $theDefinedValue = "", $theNotDefinedValue = "") {
    connectDB();

    $theValue = trim($theValue);
    //$theValue = (!get_magic_quotes_gpc()) ? mysql_real_escape_string($theValue) : $theValue;

    switch ($theType) {
        case "cell":
            $theValue = ($theValue != "") ? isValidMobileNo($theValue) : "NULL";
            break;
        case "asis":
            $theValue = ($theValue != "") ? $theValue : "''";
            break;
        case "text":
            $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
            break;
        case "long":
            $theValue = ($theValue != "") ? 0 + $theValue : "NULL";
            break;
        case "int":
            $theValue = ($theValue != "") ? intval($theValue) : "NULL";
            break;
        case "float":
            $theValue = ($theValue != "") ? floatval($theValue) : "NULL";
            break;
        case "double":
            $theValue = ($theValue != "") ? "'" . doubleval($theValue) . "'" : "NULL";
            break;
        case "date":
            $theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
            break;
        case "check":
            $theValue = ($theValue != "") ? "1" : "0";
            break;
        case "defined":
            $theValue = ( $theValue != "") ? $theDefinedValue : $theNotDefinedValue;
            break;
    }
    return $theValue;
}

function getRow($rsData) {
    return mysql_fetch_assoc($rsData);
}

function getRsRow($query, $dbLink = null) {
    if ($rsRec = selectSQL($query, $dbLink)) {
        return getRow($rsRec);
    } else {
        return false;
    }
}

function getValue($query, $collumn, $dbLink = null) {
    if ($rsData = selectSQL($query, $dbLink)) {
        $rwRec = mysql_fetch_assoc($rsData);
        return $rwRec[$collumn];
    } else {
        return false;
    }
}

function xflog($xfile, $string) {
    $file = flogPath($xfile);
    $date = date("Y-m-d H:i:s");
    $fo = fopen($file, 'ab');
    fwrite($fo, $string . " /* $date | " . $_SERVER['PHP_SELF'] . " */\n");
    fclose($fo);
}

function flogPath($file) {
    $log_path = log_path;
    if (strtolower(substr($file, (strlen($file) - 4), 4)) == '.log' or strtolower(substr($file, (strlen($file) - 4), 4)) == '.txt') {
        return $log_path . basename($file);
    } else {
        return $log_path . basename($file) . '.log';
    }
}

function flog($level, $string = '', $lineNo = '', $function = '') {
    global $DEBUG_LEVEL, $logFiles;

    $date = date("Y-m-d H:i:s");
    $logType[0] = 'UNDEFINED';
    $logType[1] = 'UNDEFINED';
    $logType[2] = 'UNDEFINED';
    $logType[3] = 'UNDEFINED';
    $logType[4] = 'INFOLOG';
    $logType[5] = 'SEQUEL';
    $logType[6] = 'TRACELOG';
    $logType[7] = 'DEBUGLOG';
    $logType[8] = 'ERRORLOG';
    $logType[9] = 'FATALLOG';
    $logType[10] = 'UNDEFINED';
    $logTitle = 'UNDEFINED';


    if (!is_int($level)) { // level is a string convert back to int and overide the default file
        if (strtolower(substr($level, (strlen($level) - 4), 4)) == '.log' or strtolower(substr($level, (strlen($level) - 4), 4)) == '.txt') { // overide the current paths {{faster than changing all scripts with custom paths}}
            $file = log_path . basename($level);
        } else { // ensure that the extension is there
            $file = log_path . basename($level) . '.log';
        }
        $level = 3;
        $logTitle = 'CUSTOM';
    } else {
        if (isset($logFiles[$level])) {
            // overide the current paths {{faster than changing all scripts with custom paths}}
            $file = $file = log_path . basename($logFiles[$level]);
            $logTitle = $logType[$level];
        } else {
            $file = $logFiles[3];
            $logTitle = 'UNDEFINED';
        }
    }

    if ($level >= $DEBUG_LEVEL) {
        if ($fo = fopen($file, 'ab')) {
            fwrite($fo, "$date - [ $logTitle ] " . $_SERVER['PHP_SELF'] . ":$lineNo $function | $string\n");
            fclose($fo);
        } else {
            trigger_error("flog Cannot log '$string' to file '$file' ", E_USER_WARNING);
        }
    }
}

/**
 * Load a config file
 */
function config_load($name) {
    $configuration = array();
    if (!file_exists(dirname(__FILE__) . '/config/' . $name . '.php')) {
        die('The file ' . dirname(__FILE__) . '/config/' . $name . '.php does not exist.');
    }
    require(dirname(__FILE__) . '/config/' . $name . '.php');
    if (!isset($config) OR ! is_array($config)) {
        die('The file ' . dirname(__FILE__) . '/config/' . $name . '.php file does not appear to be formatted correctly.');
    }
    if (isset($config) AND is_array($config)) {
        $configuration = array_merge($configuration, $config);
    }
    return $configuration;
}
