<?php

// ------------------------------------
session_name('MOBILE_EMULATOR');
session_start();

#define("sql_host", "127.0.0.1");
define("sql_host", "localhost");
define("sql_user", "root");
define("sql_password", "r00t");
define("sql_db", "emulator");
define("log_path",'emulatorlogs');
define("fatalLogs","fatal.log");
define("sqlLogs", "sequel.log");

require_once '_coreCode.php';
require_once 'sajax/sajax.php';
//require_once($_SERVER['DOCUMENT_ROOT'] . "/phoneEmulator/encryption_classes/dynamicConfigs.php");
//require_once($_SERVER['DOCUMENT_ROOT'] . "/ussdApps/encryptClasses/encryptPin.php");



  /*$sajax_request_type = "POST";
  sajax_init();

  sajax_export("home_page", "open_browser", "start_call", "make_call", "list_messages", "view_message", "ussd_reply", "ussd_clear", "log_out");
  sajax_handle_client_request();
*/
 

$sajax_debug_mode = true;
$sajax_failure_redirect = "error.html";
sajax_export(
	array( "name" => "profile_page", "method" => "POST" ),
	array( "name" => "message_new_page", "method" => "POST" ),
	array( "name" => "message_read_page", "method" => "POST" ),
	array( "name" => "messaging_home_page", "method" => "POST" ),
	array( "name" => "browser_page", "method" => "POST" ),
	array( "name" => "get_time", "method" => "POST" ),
	array( "name" => "ussd_reply", "method" => "POST" ),
	array( "name" => "ussd_clear", "method" => "POST" ),
	array( "name" => "dial_page", "method" => "POST" ),
	array( "name" => "send_sms", "method" => "POST" ),
	array( "name" => "calllog_page", "method" => "POST" ),
	array( "name" => "make_call", "method" => "POST" ),
	array( "name" => "home_page", "method" => "POST" )
);

sajax_handle_client_request();
/*
 * sajax now supports returning an array and using js to access the objects and manipulate them.
 */
?>

