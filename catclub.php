<?php

/*
 *
 *  Script to add users to a specific Teamspeak 3 group automatically.
 *	Takes TS3UID and adds group.
 *  
 *  This script uses the TS3 PHP framework: (https://docs.planetteamspeak.com/ts3/php/framework/)
 *  All credit to ScP for the framework.
 *
 *  Authored by Veypor (http://github.com/auVeypor) (veypor@veypor.net)
 */

	include 'vendor/autoload.php';
	require_once("../../auth.php");
	require_once("libraries/TeamSpeak3/TeamSpeak3.php");

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	$tsuid = filter_var($_POST["ts3uid"], FILTER_SANITIZE_STRING);

	echo "<html>";

echo <<< EOT
<script type="text/javascript">
	<!--
		function Redirect() {
			window.location="http://www.gw2blackgate.com";
		}
	//-->
</script>			
EOT;
	
	try {
		$ts3_server = TeamSpeak3::factory("serverquery://$TSUser:$TSPassword@$TSIP");
	} catch (TeamSpeak3_Exception $e) {
		echo "Connection Error. <br>There are problems connecting with the Blackgate TS3 server. <br>Mreow. <br>Code: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
		exit();
	}

	try {
			$client = $ts3_server->clientGetByUid($tsuid);
		} catch (Exception $e) {
			echo "Invalid TS3 UID. <br>Please ensure you are currently connected to the Blackgate TS3 and that your inputted UID is correct. <br>Error: " . $e->getMessage() . "<br>";
			exit();
		}	
		
		$client = $ts3_server->clientGetByUid($tsuid);
		try {
			$client->addServerGroup(587);
		} catch (TeamSpeak3_Exception $e) {
			echo "Aren't you already a member of the Cat Club? " . $e->getMessage() . "<br>";
			exit();
		}
		echo '<script type="text/javascript">'
					   , 'Redirect();'
					   , '</script>'
					;

	echo "</html>";
?>