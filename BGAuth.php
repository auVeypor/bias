<?php

/*
 *
 *  Autoverification script for Teamspeak 3.
 *	Takes an account API key and uses GW2 API to verify server loyalty.
 *  
 *  This script uses the TS3 PHP framework: (https://docs.planetteamspeak.com/ts3/php/framework/)
 *  All credit to ScP for the framework.
 *
 *	This script uses the very sexy GW2Treasure API wrapper, big props to them: (https://github.com/gw2treasures/gw2api)
 *
 *	Finally, this script uses the GW2 API, developed by ArenaNet LLC. (http://www.arena.net/)
 *
 *	Many thanks to the Blackgate server and in particular my friends from Knîghtmare [KnM] that made this possible.
 *
 *  Authored by Veypor (http://github.com/auVeypor) (veypor@veypor.net)
 */

	include 'vendor/autoload.php';
	require_once("../../auth.php");
	require_once("libraries/TeamSpeak3/TeamSpeak3.php");

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	const BGWID = 1019;
	const ETWID = 1024;
	const TSGROUP = 484;

	$tsuid = filter_var($_POST["ts3uid"], FILTER_SANITIZE_STRING);
	$gw2key = filter_var($_POST["apikey"], FILTER_SANITIZE_STRING);

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
		echo "TS3 Server Connection Error: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
	}

	try {
		$api = new \GW2Treasures\GW2Api\GW2Api();
	} catch (Exception $e) {
		echo "Wrapper Error: " . $e.getMessage();
	}

	try {
		$account = $api->account($gw2key)->get();
	} catch(Exception $e) {
		echo "Error with the API key. Ensure it is correct and has the account permissions: " . $e->getMessage() . "<br>";
	}

	try {
		$client = $ts3_server->clientGetByUid($tsuid);
		$accName = $account->name;
		$accUID = $account->id;
		$tsName = $client->__toString();
		$world = $account->world;
		$wvwRank = $account->wvw_rank;
		//Notice: Undefined property: stdClass::$wvw_rank in /home/veyp/public_html/BGProj/BGAuth.php on line 69

		$ipaddr = $_SERVER['REMOTE_ADDR'];
	} catch (Exception $e) {
		echo "Variable Integrity Error: " . $e->getMessage() . "<br>";
	}

	if (($world == BGWID) || ($world == ETWID)) {
		if ($wvwRank < 35) {
			$client = $ts3_server->clientGetByUid($tsuid);
			$client->poke("Your account must be WvW rank 35 or higher to be verified!");
			echo '<script type="text/javascript">'
			   , 'Redirect();'
			   , '</script>'
			;
			exit();
		}
		try {
			$DBConnection = new mysqli($SQLHost, $SQLUser, $SQLPass, $SQLDBName);
			if ($DBConnection->connect_error) {
				die("Database Uplink Failed" . $DBConnection->connect_error);
				exit("<br>Database Uplink Failed" . $DBConnection->connect_error);
			}

			$sqlINS = "INSERT INTO $tablename(accountname,accountuid,tsname,tsuid,serverid,worldrank,ip)VALUES('$accName','$accUID','$tsName','$tsuid','$world','$wvwRank','$ipaddr');";
			$sql1 = "SELECT * FROM $tablename WHERE accountuid = '$accUID';";
			$sql2 = "SELECT * FROM $tablename WHERE tsuid = '$tsuid';";

			$result2 = $DBConnection->query($sql2);
			if ($result2->num_rows > 0) {
				echo "This TS3 identity has already been verified.<br>";
				echo "Please contact a staff member on <a href=\"http://gw2blackgate.com/\">our forums</a> if you are unable to access Teamspeak.<br>";
				echo "This verification attempt has been logged.<br>";
				exit();
			}
			
			$result1 = $DBConnection->query($sql1);
			if ($result1->num_rows > 0) {
				echo "This account has already verified a TS3 identity with the UID:<br>";
				$row = $result1->fetch_assoc();
				$oldUID = $row["tsuid"];
				$oldRowID = $row["id"];
				$sg = $ts3_server->serverGroupGetById(TSGROUP); // WORKING HERE, This script is broken, needs to remove clients by DBID from server, not group from client!
				echo $row["tsuid"];
				echo "<br>This identity will be deverified, and your new one verified.<br>";
				$delSQL = "DELETE FROM $tablename WHERE id = '$oldRowID';";
				if ($DBConnection->query($delSQL) === TRUE) {
				    try {
						$oldClient->remServerGroup(TSGROUP);
						//$sg->clientDel($cldbid);
					} catch (TeamSpeak3_Exception $e) {
						echo "Error removing the servergroup: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
					}
				} else {
				    echo "Error deleting record from database: " . $DBConnection->error;
				    exit();
				}
				
			}

			if ($DBConnection->query($sqlINS) === TRUE) {
				try {
					$client->addServerGroup(TSGROUP);
					echo '<script type="text/javascript">'
					   , 'Redirect();'
					   , '</script>'
					;
					exit();
				} catch (TeamSpeak3_Exception $e) {
					echo "Error assigning servergroup: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
				}
			} else {
				echo "Error: " . $sqlINS . "<br>" . $DBConnection->error;
			}
			$DBConnection->close();
		} catch(Exception $e) {
			echo "Error: " . $e->getMessage();
		}
	} else {
		$client = $ts3_server->clientGetByUid($tsuid);
		$client->poke("The account you just attempted to verify with is not a Blackgate account.");
		echo '<script type="text/javascript">'
		   , 'Redirect();'
		   , '</script>'
		;
		exit();
	}

	echo "</html>";
?>