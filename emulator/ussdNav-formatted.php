<?php
/*
require_once $_SERVER['DOCUMENT_ROOT'] . "/_configs/bossConfigs.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/_configs/coreUtils.php";
include $_SERVER['DOCUMENT_ROOT'] . "/ussdApps/IOUtils.php";
$dbname = "ussd";


$db_link = mysql_connect($dbhost, $dbuser, $dbpass) or die("cannot connect " . mysql_error($db_link));
mysql_select_db($dbname, $db_link) or die("cannot connect " . mysql_error($db_link));

$date = date("Y_M_D_His");
$abort = 0 + (isset($_GET['abort']) ? $_GET['abort'] : '');
$sessionID = $_GET["sessionID"];
$mNumber = $_GET["MSISDN"];
$impliedAbort = 0;

$networkID = isset($_GET["NETWORKID"]) ? $_GET["NETWORKID"] : "0";
$data = str_replace("#", "", rawurldecode($_GET["data"]));
$shortCode = $data;
$filePathDir = "/var/www/ussdSessions/";
$ussdArray = array( );
$menuType = "";
$currentLevel = -5;
$flogPath = "ussdNavigator";
$message = "null";
$extra = "null";
$opCode = $_GET["opCode"];
$filePath = "";

$myRand2 = rand(1000, 9999);
flog($flogPath, "$mNumber | SESSIONID given: $sessionID MSISDN = $mNumber");
//return null if invalid input
//update  old sessionFiles to status 0
//$gfSql="select * from sessionFiles where MSISDN = $mNumber and status =1 order by fileID desc limit 1";
//        $gfQuery=selectSQL($gfSql, $db_link);
//              if (mysql_num_rows($gfQuery)>0)


if ($opCode != "BEG")
{//we have an existing session
	$gfSql = "select * from sessionFiles where fileID>26349653 and MSISDN = $mNumber and status =1 order by fileID desc limit 1";
	$gfQuery = selectSQL($gfSql, $db_link);
	if (mysql_num_rows($gfQuery) > 0)
	{
		$grow = mysql_fetch_assoc($gfQuery);
		$currentLevel = $grow["currentLevel"];
		$nextLevel = $grow["nextLevel"];
		$tempLevel = $grow["tempLevel"];
		$extra = $grow["extra"];
		$type = $grow["menuType"];
		$sessionFileName = $grow["fileName"];
		$sessionFileID = $grow["fileID"];
		$filePath = $filePathDir . $sessionFileName;
	}
}
else
{ //beginTran
	//formulate array
	$i = 0;
	while ($i < 20) {
		$ussdArray[$i] = "null";
		$i++;
	}


	$insertSQL = "insert into sessionFiles (MSISDN, sessionID, dateCreated, status) values($mNumber, '$sessionID', now(),1)";
	$inserted = insertSQL($insertSQL, $db_link);

	//$inserted = last_insert_id; this is our new sessionID;
	$fileName = $inserted . "_" . $mNumber . "_" . $date;
	//if(   sessionID not provided by the operator
	flog($flogPath, "$mNumber | Insert into sessions: $insertSQL");
	if ($sessionID == "0" or strlen($sessionID) == 1)
	{
		flog($flogPath, "$mNumber RESETING SESSIONID from $sessionID to $inserted");
		$sessionID = $inserted;
	}
	//update the thingy.
	//update session files
	$updateSQL = "update sessionFiles set fileName = " . gSQLv($fileName) . ", sessionID = '$sessionID' where fileID>26349653 and MSISDN= $mNumber and status=1 and fileID=$inserted";
	$updated = updateSQL($updateSQL, $db_link);
	//update global vars//

	$ussdArray[0] = $sessionID;
	$ussdArray[1] = $mNumber;
	$ussdArray[3] = $shortCode . "#";
	$ussdArrayString = implode("|", $ussdArray);
	$ussdArrayString = $ussdArrayString . "|";

	$filePath = $filePathDir . $fileName;

	writeToFile($filePath, $ussdArrayString);
	//chown($filePath, "apache.appDev");
	chmod($filePath, 0776);

	flog($flogPath, "$mNumber |  Created a new Session for sessionID = $sessionID, MSISDN = $mNumber and fileName= $fileName ");

	//initializae variables
	$currentLevel = 1;
	$nextLevel = 1;
	$tempLevel = 1;
	$type = "null";
	$extra = "null";
	$sessionFileName = $fileName;
	$sessionFileID = $inserted;
	$sessionFileCreated = 1;
}

if ($opCode == "ABORT" or $data == "invokeAck")
{

	$abort = 1; //opCode not INVA or BEG
}


if ($mNumber == "" or $data == "" or $sessionID == "")
	$abort = 1;
if ($abort == 1)
{
	flog($flogPath, "$mNumber | ABORT=1 : sessionID=$sessionID, mNumber: $mNumber");
	abort($mNumber);
}

//log hop

logHops($sessionFileID, $data);
//we now have our current level, nextlevel, templevel and sessionFileID
staticProcessing();


function staticProcessing() {
	global $sessionID, $mNumber, $data, $shortCode, $abort, $message, $filePathDir, $fileNamePart, $fileName, $ussdArray, $type, $currentLevel, $tempLevel, $filePath, $sessionFileName, $sessionFileID, $flogPath, $db_link, $myRand2, $networkID, $impliedAbort;

	$shortCode = $data . "#";
	flog($flogPath, "$mNumber | I-AM- $myRand2 - we begin");
	$sql = "select shortcodes.levelid, levels.message, levels.leveltypeid, levels.resultindex, leveltypes.type from shortcodes  left join levels on shortcodes.levelid=levels.id left join leveltypes on levels.leveltypeid=leveltypes.id  where shortcodes.value='$shortCode'";
	flog($flogPath, "$mNumber | levelSQL | $sql ");
	$result = selectSQL($sql, $db_link);
	$numRows = mysql_num_rows($result);
	if ($numRows > 0)
	{
		//first Level
		while ($row = mysql_fetch_assoc($result)) {
			$message = $row["message"];
			$type = $row["type"];
			$currentLevel = $row["levelid"];
			$levelTypeID = $row["leveltypeid"];
			$resultIndex = $row["resultindex"];


			//route dynamic processing
			if ($levelTypeID > 49)
			{
				flog($flogPath, "$mNumber | Leveltype: $levelTypeID>=50 : Calling DynamicProcessing");
				$extra = "null";

				$tempLevel = 1;
				dynamicProcessing($levelTypeID);
				flog($flogPath, "$mNumber | I-AM- $myRand2 - Exiting Dynamic processing and dying");

				die();
			}

			if ($type == "menu" or $type == "input")
			{

				$query = "select optionid, value, realvalue, serviceid, input, nextlevel,currentlevel from options
                                left join levelmappings on optionid=input where levelid =$currentLevel and currentlevel=$currentLevel group by optionid order by optionid";

				$newResult = selectSQL($query, $db_link);
				while ($newRow = mysql_fetch_assoc($newResult)) {
					$optionMsg = "^" . $newRow["optionid"] . ": " . $newRow["value"];
					$message.=$optionMsg;
				}
			}
		}
		//update sessions file table with new current level
		$updateSessionFile = "update sessionFiles set currentLevel=$currentLevel, menuType='$type', resultIndex='$resultIndex' where fileID>26349653 and MSISDN=$mNumber and status = 1";
		$updated = updateSQL($updateSessionFile, $db_link);
		if ($type == "end")
		{
			$mode = "end";
			echo "$message|$sessionID|$mode|$mNumber";
			flog($flogPath, "$mNumber | Stage 1 -End Mode ---Response: $message, MSISDN: $mNumber , Mode: $mode, SessionID: $sessionID");
			abort($mNumber);
		}
		else
		{
			$mode = "continue";
			echo "$message|$sessionID|$mode|$mNumber";
			flog($flogPath, "$mNumber | Stage 1  Response: $message, MSISDN: $mNumber , Mode: $mode, SessionID: $sessionID");
		}
	}
	else
	{ // not first level
		$sql = "select * from sessionFiles where fileID>26349653 and MSISDN = $mNumber  and status =1 order by fileID desc limit 1";
		$fileResult = selectSQL($sql, $db_link);
		$numRows = mysql_num_rows($fileResult);
		if ($numRows > 0)
		{ // we have a winner
			$fromSessionFile = mysql_fetch_assoc($fileResult);
			$currentLevel = $fromSessionFile["currentLevel"];
			$nextLevel = $fromSessionFile["nextLevel"];
			$tempLevel = $fromSessionFile["tempLevel"];
			$sessionFileID = $fromSessionFile["fileID"];
			$sessionFileName = $fromSessionFile["fileName"];
			$type = $fromSessionFile["menuType"];
			$resultIndex = $fromSessionFile["resultIndex"];
			$inputArray = explode("*", $data);
			$thisUserInput = $inputArray[sizeof($inputArray) - 1];
			$userInput = $thisUserInput;

			if ($type == "input")
			{
				$userInput = -1;

				$appendQuery = "";
			}
			elseif ($nextLevel > 0 and $currentLevel > 49)
			{
				$appendQuery = " and nextLevel=$nextLevel";
			}
			else
			{
				$appendQuery = " and options.optionid='$userInput'";
			}


			$levelSQL = "select levelmappings.nextLevel, levelmappings.currentLevel, levels.message,levels.leveltypeID, levels.resultindex, options.realvalue,options.serviceid, leveltypes.type from levelmappings left join levels on levelmappings.nextlevel=levels.id left join leveltypes on leveltypes.id=levels.leveltypeid left join options on options.levelid=levelmappings.currentlevel  where levelmappings.currentLevel=$currentLevel and levelmappings.input='$userInput' $appendQuery;";

			$levelResult = selectSQL($levelSQL, $db_link);
			$levelNumRows = mysql_num_rows($levelResult);
			if ($levelNumRows == 0)
			{
				$extra = "";
				$levelTypeID = -1;
				$initial = 1;
				dynamicProcessing($levelTypeID, $initial);
				die();
			}

			$levelArray = mysql_fetch_assoc($levelResult);
			$levelTypeID = $levelArray["leveltypeID"];

			$nextLevel = $levelArray["nextLevel"];
			$message = $levelArray["message"];
			$type = $levelArray["type"];
			$nextResultIndex = $levelArray["resultindex"];
			$currentLevel = $nextLevel;
			$serviceID = 0 + $levelArray["serviceid"];
			$oldSessionArray = getArrayFromFile($filePath);
			$oldSessionArray = explode("|", $oldSessionArray);
			if ($resultIndex != -1)
			{
				if (isset($levelArray["realvalue"]))
				{
					$oldSessionArray[$resultIndex] = $levelArray["realvalue"];
				}
				else
				{
					$oldSessionArray[$resultIndex] = $thisUserInput;
				}
			}

			if ($serviceID != 0)
			{
				$oldSessionArray[2] = $serviceID;
			}

			$ussdArrayString = implode("|", $oldSessionArray);
			$ussdArrayString = $ussdArrayString;

			writeToFile($filePath, $ussdArrayString);



			if ($levelTypeID > 49)
			{
				$extra = "";
				dynamicProcessing($levelTypeID);

				die();
			}




			if ($type == "menu" or $type == "input")
			{

				$query = "select optionid, value, realvalue, serviceid, input, nextlevel,currentlevel from options
                                left join levelmappings on optionid=input where levelid =$currentLevel and currentlevel=$currentLevel  group by optionid order by optionid";

				$newResult = selectSQL($query, $db_link);
				while ($newRow = mysql_fetch_assoc($newResult)) {
					$optionMsg = "^" . $newRow["optionid"] . ": " . $newRow["value"];
					$message.=$optionMsg;
				}
			}
			//update sessions file table with new current level
			$updateSessionFile = "update sessionFiles set currentLevel=$currentLevel, nextLevel=$nextLevel, menuType='$type', resultIndex='$nextResultIndex' where fileID>26349653 and MSISDN=$mNumber";

			$updated = updateSQL($updateSessionFile, $db_link);


			if ($type == "end")
			{
				$mode = "end";
				echo "$message|$sessionID|$mode|$mNumber";

				abort($sessionID, $mNumber, $shortCode);
			}
			else
			{
				$mode = "continue";
				echo "$message|$sessionID|$mode|$mNumber";
			}
		}
		else
		{
			$message = "Sorry we could not process your request at the moment. Please try again Later.";
			$mode = "end";
			flog($flogPath, "$mNumber | NOO MENU IN dynamic processing with abort = $abort and impliedAbort = $impliedAbort");
			echo "$message|$sessionID|$mode|$mNumber";
			abort($mNumber);
		}
	}

}


// end staticProcessing

function dynamicProcessing($levelTypeID, $initial = 0, $impliedAbort = 0) {
	global $sessionID, $mNumber, $data, $shortCode, $message, $filePathDir, $abort, $fileNamePart, $fileName, $ussdArray, $type, $currentLevel, $tempLevel, $filePath, $sessionFileName, $sessionFileID, $flogPath, $db_link, $opCode, $networkID;

	flog($flogPath, "$mNumber | IN dynamic processing with abort = $abort and impliedAbort = $impliedAbort");
	if ($impliedAbort == 1)
	{
		$abort = 1;
	}




	$inputArray = explode("*", $data);
	$thisUserInput = $inputArray[sizeof($inputArray) - 1];
	$userInput = $thisUserInput;

	if ($levelTypeID != -1)
	{

		if ($initial > 0)
		{
			$appendQuery = "and levelmappings.input=$userInput";
		}
		else
		{
			$appendQuery = "";
		}

		$dpSQL = "select dynamicrouting.url,levels.id, dynamicrouting.leveltypeid,levelmappings.nextlevel from dynamicrouting left join levels on levels.leveltypeid=dynamicrouting.leveltypeid left join levelmappings on levels.id=levelmappings.currentlevel  where dynamicrouting.leveltypeid =$levelTypeID";
		$dpResult = selectSQL($dpSQL, $db_link);
		$dpNumRows = mysql_num_rows($dpResult);
		if ($dpNumRows > 0)
		{
			$dpRow = mysql_fetch_assoc($dpResult);
			//$tempLevel=$dpRow["tempLevel"];
			$extra = "null";
			$dpUrl = $dpRow["url"];
			$nextLevel = $dpRow["nextlevel"];
			$currentLevel = $dpRow["id"];
			$tempLevel = 1;

			if ($impliedAbort == 1)
			{
				$abort = 1;
			}

			$dpUrl = $dpUrl . "?MSISDN=$mNumber&CURRENTLEVEL=$currentLevel&TEMPLEVEL=$tempLevel&INPUT=" . rawurlencode($userInput) . "&EXTRA=$extra&SESSIONFILE=$filePath&ABORT=$abort&sessionID=$sessionID&opCode=$opCode&networkID=$networkID";

			flog($flogPath, "$mNumber | Dynamic URL: $dpUrl");

			$returnedString = join('', file($dpUrl));
			flog($flogPath, "$mNumber | Returned String isis $returnedString");
			if ($impliedAbort == 1)
			{
				flog($flogPath, "$mNumber | Got a null response with abort =1 no need to respond to proxy, this is an abort");
				$updateStatus = "update sessionFiles set status = 0 where fileID>26349653 and MSISDN = $mNumber and status =1";
				$updated = updateSQL($updateStatus, $db_link);

				die();
			}

			$dpArray = explode("|", $returnedString);
			if ($nextLevel == "")
			{
				$nextLevel = $currentLevel;
			}
			$tempLevel = $dpArray[0];
			$mode = $dpArray[sizeof($dpArray) - 2];
			$extra = $dpArray[sizeof($dpArray) - 1];
			$returnedString = str_replace("\n", "^", $dpArray[1]);

			$rand = rand(100, 1000);
			
			$updateTempLevel = "update sessionFiles set tempLevel=$tempLevel, currentLevel=$currentLevel, nextLevel=$nextLevel, extra=" . gSQLv($extra) . "  where fileID>26349653 and MSISDN=$mNumber and status =1";
			$updateResult = insertSQL($updateTempLevel, $db_link);
			flog($flogPath, "$mNumber | mode = $mode");
			if ($mode == "continue")
			{

				
				flog($flogPath, "$mNumber | invoking navigator again - runID = $rand");
				staticProcessing();
				die();
			}
			elseif ($mode == "null")
			{
				$mode = "continue";
				flog($flogPath, "$mNumber | invoking navigator again - mode = $mode = $rand");
			}
			flog($flogPath, "$mNumber | Echo is $returnedString|$sessionID|$mode|$mNumber");
			echo "$returnedString|$sessionID|$mode|$mNumber";
			if ($mode == "end")
			{
				abort($mNumber);
			}
		}
		else
		{

			$message = "Sorry we could not process your request at the moment. Please try again Later.";
			$mode = "end";
			echo "$message|$sessionID|$mode|$mNumber";
			abort($mNumber);
		}
	}
	else
	{

		if ($impliedAbort == 1)
		{

			$abort == 1;
		}

		$tempLevelSQL = "select sessionFiles.*, dynamicrouting.url from sessionFiles left join leveltypes on sessionFiles.menuType=leveltypes.type left join dynamicrouting on dynamicrouting.leveltypeid=leveltypes.id where sessionFiles.fileID>26349653 and sessionFiles.MSISDN=$mNumber and sessionFiles.status =1 order by sessionFiles.fileID desc limit 1";
		$tempLevelSQL = "select sessionFiles.*, dynamicrouting.url from sessionFiles left join levels on sessionFiles.currentLevel=levels.id left join dynamicrouting on dynamicrouting.leveltypeid=levels.leveltypeid where sessionFiles.fileID>26349653 and  sessionFiles.MSISDN=$mNumber and sessionFiles.status =1";
		flog($flogPath, "$mNumber | tempLevelSQL = $tempLevelSQL");

		$tempLevelResult = selectSQL($tempLevelSQL, $db_link);
		$tempLevelRows = mysql_num_rows($tempLevelResult);
		if ($tempLevelRows > 0)
		{
			$levelRow = mysql_fetch_assoc($tempLevelResult);
			$dpUrl = $levelRow["url"];

			if ($dpUrl == "")
			{
				flog($flogPath, "$mNumber | no url to route to with dialled  = $data");
				$codeArray = explode("*", $data);

				if ($codeArray[1] == 369)
				{
					$thisUserInput = $codeArray[sizeof($codeArray) - 1];
					$dpUrl = "http://hammer/ussdApps/ussdArtists.php";
				}

				if ($dpUrl == "")
					$dpUrl = "http://hammer/ussdApps/ussdArtists.php";
			}

			$tempLevel = $levelRow["tempLevel"];
			$nextLevel = 0 + $levelRow["nextlevel"];
			$extra = urlencode($levelRow["extra"]);
			//$currentLevel=$levelRow["id"];
			$dpUrl = $dpUrl . "?MSISDN=$mNumber&CURRENTLEVEL=$currentLevel&TEMPLEVEL=$tempLevel&INPUT=" . rawurlencode($thisUserInput) . "&EXTRA=$extra&SESSIONFILE=$filePath&ABORT=$abort&sessionID=$sessionID&opCode=$opCode&networkID=$networkID";
			flog($flogPath, "URL to dynamic is $dpUrl");
			$returnedString = join('', file($dpUrl));
			if ($impliedAbort == 1)
			{
				flog($flogPath, "implied abort isset dying");
				$updateStatus = "update sessionFiles set status = 0 where fileID>26349653 and MSISDN = $mNumber and status =1";
				$updated = updateSQL($updateStatus, $db_link);

				die();
			}

			$dpArray = explode("|", $returnedString);
			$tempLevel = 0 + $dpArray[0];
			if ($tempLevel == 0)
				$tempLevel = 1;
			$extra = $dpArray[sizeof($dpArray) - 1];
			$mode = $dpArray[sizeof($dpArray) - 2];
			$updateTempLevel = "update sessionFiles set tempLevel=$tempLevel,currentLevel=$currentLevel, nextLevel=$nextLevel, extra=" . gSQLv($extra) . " where fileID>26349653 and MSISDN=$mNumber and status=1";
			$updateResult = insertSQL($updateTempLevel, $db_link);
			$returnedString = str_replace("\n", "^", $dpArray[1]);

			if ($mode == "continue")
			{

				$rand = rand(100, 1000);
				flog($flogPath, "$mNumber | invoking navigator again - runID = $rand");

				staticProcessing();
				die();
			}
			elseif ($mode == "null")
			{
				$mode = "continue";
			}

			echo "$returnedString|$sessionID|$mode|$mNumber";

			if ($mode == "end")
			{
				abort($mNumber);
			}
		}
		else
		{

			$message = "Sorry we could not process your request at the moment. Please try again Later.";
			$mode = "end";

			echo "$message|$sessionID|$mode|$mNumber";

			abort($mNumber);
		}
	}

}


function abort($mobileNo) {
	global $db_link, $filePath, $abort, $flogPath, $sessionID;

	if ($filePath == "")
	{
		$qry = "select fileName from sessionFiles where fileID>26349653 and MSISDN = $mobileNo and sessionID = '$sessionID'";
		flog($flogPath, "grabbing filepath from DB - $qry");
		$result = selectSQL($qry, $db_link);
		if ($row = mysql_fetch_assoc($result))
		{
			$filePath = "/var/www/ussdSession/" . $row["fileName"];
		}
	}



	flog($flogPath, "$mobileNo | invoking  abort for MSISDN: $mobileNo and filePath = $filePath and sessionID = '$sessionID'");



	$updateStatus = '';
	$oldSessionArray = getArrayFromFile($filePath);
	$SessionArray = explode("|", $oldSessionArray);


	$serviceID = $SessionArray[2];

	//      $SessionArray[4]=$data;
	$SessionArray[1] = $mobileNo;
	$shortCode = $SessionArray[3];
	$sstring = "|" . implode("|", $SessionArray);
	//$data = str_replace("*", "#", $shortCode);
	$data = $shortCode;
	$today = date("Y-m-d");
	flog($flogPath, "Inside Abort shortCode=$shortCode  --- 3 = " . $SessionArray[3] . ' ---- 4 = ' . $SessionArray[4] . " MSISDN = $mobileNo");

	flog($flogPath, "In Abort, set status = 0 SQL : $updateStatus");
//flog($flogPath, "TEMP-- shortCode = $shortCode | data = $data --- outside condition for aborting -  $mobileNo --- raw data = ".$_GET['data']);
//                                      if($shortCode=="*169*225#" or $shortCode=="*167*4*225#" or $shortCode=="*167*4*224#" or $shortCode=="*224#" or ($shortCode=="*167*4#" && $data=="4#224") )
	if ($shortCode == "*169*225#" or $shortCode == "*167*4*225#" or $shortCode == "*167*4*224#" or $shortCode == "*224#")
	{

//flog($flogPath, "TEMP-- shortCode = $shortCode | data = $data --- inside condition for aborting -  $mobileNo");
		flog($flogPath, "invoking dynamic processing for barclays with abort=1  - MSISDN = $mobileNo");

		$leveltypeID = -1;
		dynamicProcessing($leveltypeID, 0, 1);

		die();
	}

	$updateStatus = "update sessionFiles set status = 0 where fileID>26349653 and MSISDN = $mobileNo and status =1";
	$updated = updateSQL($updateStatus, $db_link);

//temp added by faz (31 Aug 2011) - to throttle M-Pesa trx going in for KCBConnect - to reduce 103s which happen as a result of MC being much busier (faster) than DWN
	$splitArray = explode("|", $sstring);
	$split_serviceID = $splitArray[3];
	$split_channelName = $splitArray[7];

	$ussdInStatus = 0;

//if this request is for MPesa at KCB, set status to 7 (throttled) - a cron process will unthrottle these requests every X minutes (3 to start with)
	if ($split_serviceID == '2119' && $split_channelName == 'mpesaTransfer')
		$ussdInStatus = 7;


	flog($flogPath, "ussdinmessages logging: ussdInStatus = $ussdInStatus, string = $sstring");


	$insertSQL = "insert into ussdinmessages (MSISDN,message,serviceId,path,sessionID,today,timein,status) values($mobileNo,'$sstring','$serviceID','$data','$sessionID','$today',now(), $ussdInStatus);";
	flog($flogPath, "inserting into ussdinmessages $insertSQL");

	$result = insertSQL($insertSQL, $db_link);

	//set status = 0;
	//if($abort!=0)
	//{
	//      echo "null";
	//}
	//invoke barclays even after abort

	die();

}


function cleanOldSessions() {
	global $db_link, $flogPath;
	$query = "update sessionFiles set status = 0 where fileID>26349653 and status = 1 and (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(dateCreated))>=500"; //sessionFiles older than five minutes;
	$execute = updateSQL($query, $db_link);
	flog($flogPath, "$mNumber | SQL - $query");

}


function logHops($fileID, $hopData) {
	global $db_link, $abort;

	if ($abort == 1)
		$hops = 1;
	else
		$hops = 2;

	//check if sessionHop is already written to
	$sql = "select * from sessionHops where  sessionFileID=$fileID";
	$execute = selectSQL($sql, $db_link);
	$rows = mysql_num_rows($execute);
	if ($rows == 0)
	{

		$sql = "insert into sessionHops (sessionFileID,hops,path,dateCreated) values($fileID, $hops, " . gSQLv($hopData) . ", now())";
		$query = insertSQL($sql, $db_link);
	}
	else
	{
		$data = mysql_fetch_assoc($execute);
		$hopID = $data["hopID"];
		$hopData = "," . $hopData;
		$updateHops = "update sessionHops set hops=hops+$hops, path=concat(path," . gSQLv($hopData) . ") where hopID=$hopID";
		$updated = updateSQL($updateHops, $db_link);
	}

}

?>
