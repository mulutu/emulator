<?php
/*
   ==============================
   receive SMS message
   ==============================
 */
error_reporting(1);
define("sql_host", "");
define("sql_user", "");
define("sql_password", "");
define("sql_db", "");

define("infoLogs", "info.log");

/**
4 rows in set (0.00 sec)

mysql> desc profiles;
+---------------+---------------------+------+-----+-------------------+-----------------------------+
| Field         | Type                | Null | Key | Default           | Extra                       |
+---------------+---------------------+------+-----+-------------------+-----------------------------+
| profile_id    | int(10) unsigned    | NO   | PRI | NULL              | auto_increment              |
| names         | varchar(127)        | YES  |     | NULL              |                             |
| MSISDN        | bigint(15) unsigned | NO   | UNI | NULL              |                             |
| network_id    | int(10) unsigned    | NO   | MUL | NULL              |                             |
| password      | varchar(127)        | YES  |     | NULL              |                             |
| profileStatus | tinyint(5)          | YES  |     | 0                 |                             |
| dateCreated   | datetime            | YES  |     | NULL              |                             |
| dateModified  | timestamp           | NO   |     | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |
+---------------+---------------------+------+-----+-------------------+-----------------------------+
8 rows in set (0.00 sec)

ysql> desc messages;
+----------------+------------------+------+-----+-------------------+-----------------------------+
| Field          | Type             | Null | Key | Default           | Extra                       |
+----------------+------------------+------+-----+-------------------+-----------------------------+
| message_id     | int(10) unsigned | NO   | PRI | NULL              | auto_increment              |
| profile_id     | int(10) unsigned | YES  | MUL | NULL              |                             |
| sourceaddr     | varchar(32)      | YES  |     | NULL              |                             |
| destaddr       | varchar(32)      | YES  |     | NULL              |                             |
| dateCreated    | datetime         | NO   |     | NULL              |                             |
| messageContent | mediumtext       | YES  |     | NULL              |                             |
| messageType    | tinyint(4)       | NO   |     | 0                 |                             |
| messageRead    | tinyint(8)       | NO   |     | 0                 |                             |
| dateModified   | timestamp        | NO   |     | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |
+----------------+------------------+------+-----+-------------------+-----------------------------+
9 ro
**/

$sql_conn = connectDB();
//echo "\n samsung request:" . print_r($_GET,true);
//echo "\nrequest received.\n";
if(isset($_GET['SOURCEADDR']) and isset($_GET['DESTADDR']) and isset($_GET['MESSAGE']))
{
	$data = $_GET;
	if(stristr($data['MESSAGE'],'%2520')) // remove double encoding
		$data['MESSAGE'] = rawurldecode($data['MESSAGE']);

	$getidquery = "SELECT profile_id from profiles where MSISDN = '".$data['SOURCEADDR']."';" ;
	
        $result = mysql_query("$getidquery",$sql_conn);  
        $nums = mysql_num_rows($result);
	$profileID=0;
	if($nums == 0){
		/** insert into Profiles*/
		$insertQuery= "insert into profile(MSISDN) values ('".$data['SOURCEADDR']."')";
		mysql_query($insertQuery,$sql_conn);
		$profileID=mysql_insert_id();
		/** select*/
		
		
	}else{
		$data2 = mysql_fetch_assoc($result);
		$profileID = $data2['profile_id'];
	}
	$query = "insert into messages (profile_id,sourceaddr, destaddr, messageContent, dateCreated, messageType) values
		($profileID, '".$data['SOURCEADDR']."','".$data['DESTADDR']."','".$data['MESSAGE']."',now(), 1)";
        flog(infoLogs,"Insert query is $query");
	$id = insertSQL($query,$sql_conn);

	echo "$id\n1\nOK";
}
else
{
        
	echo "0\n0\nNULL";
}
function connectDB() {
	//$sqlite_connection = new sqlite3('mobile-profiles.sqlite', SQLITE3_OPEN_READWRITE);
	$sql_conn = mysql_connect(sql_host, sql_user, sql_password) or die('connect_DB'.mysql_error());
	mysql_select_db(sql_db, $sql_conn) or die('select_DB'. mysql_error());
	
	return $sql_conn;
}
function insertSQL($query, $dbLink = null) {
	
	mysql_query("$query", $dbLink) or die(mysql_error($dbLink));
	return mysql_insert_id($dbLink);
}
function flog($flogPath, $string) {

    $type = "INFO";
    $date = date("Y-m-d H:i:s");
    if ($fo = fopen($flogPath, 'ab')) {
        fwrite($fo, "$date - [ $type ] " . $_SERVER['PHP_SELF'] . " | $string\n");
        fclose($fo);
    }
}
?>

