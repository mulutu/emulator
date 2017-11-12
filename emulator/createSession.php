<?php
date_default_timezone_set("Africa/Nairobi");

$DEBUG = '';
require_once '_sessions.php';
$msisdn = 0;
$errorMessage = '';

global $db;

if (isset($_SESSION['user_profileID'])) {
    @session_unset();
    @session_destroy();
    session_regenerate_id(true);
}

if (isset($_REQUEST['cmdLogin'])) {
    global $db;

    $msisdn = $_REQUEST['tx_msisdn'];
    $password = $_REQUEST['tx_password'];
    $network = $_REQUEST['sl_network'];
    $valid_msisdn = $msisdn;

    $errorMessage = "Ok: MSISDN provided '$valid_msisdn'";

    $query = "select * from profiles where MSISDN = '$valid_msisdn'";

    $profiles_data = $db->fetch_row_assoc($query);

    if (isset($profiles_data['profile_id'])) {
        print "cccc";
        if ($profiles_data['password'] == $password or $profiles_data['password'] == null) {

            $_SESSION['user_profileID'] = $profiles_data['profile_id'];
            $_SESSION['user_MSISDN'] = $profiles_data['MSISDN'];
            $_SESSION['user_names'] = $profiles_data['names'];
            $_SESSION['user_network'] = $profiles_data['network_id'];
            
            //print_r($_SESSION);

            header("location:index.php");
            exit();
        } else {
            $errorMessage = "Error: Invalid Credentials for $valid_msisdn";
        }
    } else {
        if ($network == 'Default') {
            $network_data = get_network($valid_msisdn);
            $network = $network_data['network_id'];
        } else {
            $network_data = $network_list[$network];
        }
        $values = array(
            'MSISDN' => $valid_msisdn,
            'password' => $password,
            'names' => 'new profile',
            'network_id' => $network,
            'profileStatus' => 1
        );

        $db->insert('profiles', $values);
        //$profile_id = $db->last_insert_id();

        header("location:index.php");
        exit();
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <title>USSD Emulator</title>
        <style type="text/css">
            th {
                text-align: right;
            }
            input, option, select{
                padding:5px;

                font-weight: bold;
            }

        </style>
    </head>
    <body style="background:#fff;">

        <div style="text-align:center; width:500px; background:#AAB; margin:auto; padding: 5px; border: 15px solid #FFFFFF;">
            <p>&nbsp;</p>
            <form name="actionfrm"  method="POST" >
                <pre style="text-align:center;color:#E00"><?php echo $errorMessage . "\n" . $DEBUG; ?></pre>
                <table border='0' align="center">
                    <tr>
                        <td colspan="2">
                            <h3>Please enter your Credentials</h3>
                        </td>
                    </tr>
                    <tr>
                        <th width='50%'>
                            Mobile Number:
                        </th>
                        <td>
                            <input type="text" name="tx_msisdn" value="<?php echo $msisdn; ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <th width='50%'>
                            Network:
                        </th>
                        <td>
                            <select name="sl_network">
                                <option id='Default' selected='selected'>Default</option>
<?php
foreach ($network_list as $network_data) {
    echo " <option value='{$network_data['network_id']}'>{$network_data['network_id']} - {$network_data['networkName']}</option> ";
}
?></select>
                        </td>
                    </tr>
                    <tr>
                        <th width='50%'>
                            Password:
                        </th>
                        <td>
                            <input type="password" name="tx_password" value=""/>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:right;">
                            <input type="submit" name="cmdLogin" value="Login / Register"/>
                        </td>
                    </tr>
                </table>
                <p>NB! Account will be created if it does not already exist</p>
                <p>&nbsp;</p>
            </form>
        </div>
    </body>
</html>


