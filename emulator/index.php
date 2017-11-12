<?php
require_once '_sessions.php';
/**
 * login / creating profiles (networks)
 * messaging display and creating
 * messaging Api receiving messages
 * profile manager
 *
 */
if (!isset($_SESSION['user_profileID'])) {
    header("location:createSession.php");
    exit();
}
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <title>Mobile Emulator</title>
        <script type="text/javascript" language="javascript" src="actionscripts.js" ></script>
        <script type="text/javascript" language="javascript" src="sajax/sajax.js" ></script>
        <script type="text/javascript"><!-- 
            <?php sajax_show_javascript(); ?>--></script>
        <link href="phone-layout.css" rel="stylesheet" type="text/css" media="all" />
    </head>
    <body onload="return go_previouse()">
        <div id="parent-page" >
            <div id="main-phone" >
                <div id='main-screen' >
                    <div id="notification-bar" >
                        <div id="notice_operator" style="float:left; width:145px; text-align: left;"><a href='#' title='254712345678'>Operator</a></div>
                        <div id="notice_message" style="float:left; text-align:right; width:20px;"></div>
                        <div id="notice_time" style="float:right; text-align:right; width:90px;">00:00 PM</div>
                    </div>
                    <div id="display-screen">
                        &nbsp;
                    </div>
                </div>
                <div id='phones-buttons' style="">
                    <div class="phones-button">
                        <input name='btn_options' type="image" src="images/blank.gif" style="height: 30px; width: 80px;" onclick="return go_home();" />
                    </div>
                    <div class="phones-button">
                        <input name='btn_home' type="image" src="images/blank.gif" style="height: 30px; width: 90px;" onclick="return go_home();" />
                    </div>
                    <div class="phones-button">
                        <input name='btn_back' type="image" src="images/blank.gif" style="height: 30px; width: 80px;" onclick="return go_previouse();" />
                    </div>
                </div>

                <div style="width:165px;height:30px;margin-top:22px;margin-left:110px;padding:0px;overflow:hidden;">
                    <input name='btn_clear' type="image" src="images/blank.gif" style="height: 30px; width: 80px;" onclick="document.getElementById('debug-div').innerHTML = ''" />
                    <input name='btn_logout' type="image" src="images/blank.gif" style="height: 30px; width: 80px;" onclick="document.location = 'logout.php'" />
                </div>

            </div>
            <div id="debug-div"></div>
        </div>
    </body>
</html>