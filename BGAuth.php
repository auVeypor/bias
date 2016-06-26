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
	const TSGROUP = 573;

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
		$api = new \GW2Treasures\GW2Api\GW2Api();
	} catch (Exception $e) {
		echo "Session Error. <br>Please contact a Gatekeeper. <br>Code: " . $e.getMessage();
		exit();
	}

	try {
		$tokenPermissions = $api->tokeninfo($gw2key)->get();
	} catch (Exception $e) {
		echo "Invalid API key. <br>Please ensure your API key is created and correct and try again.";
		exit();
	}

	$pvpEndpoint = false;
	$accountEndpoint = false;
	$progressionEndpoint = false;
	$guildsEndpoint = false;
	$permError = 15;

	foreach ($tokenPermissions->permissions as $key => $value) {
		if ($value == "account") {
			$accountEndpoint = true;
			$permError = $permError - 1;
		}
		if ($value == "pvp") {
			$pvpEndpoint = true;
			$permError = $permError - 2;
		}
		if ($value == "progression") {
			$progressionEndpoint = true;
			$permError = $permError - 4;
		}
		if ($value == "guilds") {
			$guildEndpoint = true;
			$permError = $permError - 8;
		}

	}
	
	if (($pvpEndpoint == false) || ($accountEndpoint == false) || ($progressionEndpoint == false) || ($guildEndpoint == false)) {
		echo "Permission Error. <br>Your API key must give permission to access 'PvP', 'Account', 'Progression' and 'Guilds'. <br>Please grant your API key these permissions and try again. <br>Code: " . $permError;
		exit();
	}

	try {
		$ts3_server = TeamSpeak3::factory("serverquery://$TSUser:$TSPassword@$TSIP");
	} catch (TeamSpeak3_Exception $e) {
		echo "Connection Error. <br>There are problems connecting with the Blackgate TS3 server. <br>Please contact a Gatekeeper. <br>Code: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
		exit();
	}

	try {
		$account = $api->account($gw2key)->get();
	} catch(Exception $e) {
		echo "API service error. <br>Please ensure your API key is correct and try again. If this problem persists, contact a gatekeeper.";
		exit();
	}

	try {
			$client = $ts3_server->clientGetByUid($tsuid);
		} catch (Exception $e) {
			echo "Invalid TS3 UID. <br>Please ensure you are currently connected to the Blackgate TS3 and that your inputted UID is correct. <br>Error: " . $e->getMessage() . "<br>";
			exit();
		}	
		
	try {
		$accName = $account->name;
		$accUID = $account->id;
		$tsName = $client->__toString();
		$tsDbid = $client->client_database_id;
		$world = $account->world;
		$wvwRank = $account->wvw_rank;
		$ipaddr = $_SERVER['REMOTE_ADDR'];
	} catch (Exception $e) {
		echo "Integrity error: " . $e->getMessage() . "<br>";
		exit();
	}

	if ($world == BGWID) {
		if ($wvwRank < 35) {
			$client = $ts3_server->clientGetByUid($tsuid);
			echo "Apologies, but BIAS does not allow verification to accounts that have a WvW rank of 35 or less. Please reattempt verification with BIAS when you are higher level.<br>";
			exit();
		}
		try {
			$DBConnection = new mysqli($SQLHost, $SQLUser, $SQLPass, $SQLDBName);
			if ($DBConnection->connect_error) {
				echo "Uplink to BIAS database failed. Please contact a Gatekeeper." . $DBConnection->connect_error;
				exit();
			}

			$sqlINSERT = "INSERT INTO $tablename(accountname,accountuid,tsname,tsuid,tsdbid,serverid,worldrank,ip)VALUES('$accName','$accUID','$tsName','$tsuid','$tsDbid','$world','$wvwRank','$ipaddr');";
			$sqlSEARCHACCOUNT = "SELECT * FROM $tablename WHERE accountuid = '$accUID';";
			$sqlSEARCHTSUID = "SELECT * FROM $tablename WHERE tsuid = '$tsuid';";

			$result2 = $DBConnection->query($sqlSEARCHTSUID);
			if ($result2->num_rows > 0) {
				echo "This TS3 identity has already been verified.<br>";
				echo "Please contact a staff member on <a href=\"http://gw2blackgate.com/\">our forums</a> if you are unable to access Teamspeak.<br>";
				exit();
			}
			
			$result1 = $DBConnection->query($sqlSEARCHACCOUNT);
			if ($result1->num_rows > 0) {
				echo "This account has already verified a TS3 identity with the UID:<br>";
				$row = $result1->fetch_assoc();
				$oldDBID = $row["tsdbid"];
				$oldRowID = $row["id"];
				$sg = $ts3_server->serverGroupGetById(TSGROUP); // WORKING HERE, This script is broken, needs to remove clients by DBID from server, not group from client!
				echo $row["tsuid"];
				echo "<br>This identity will be deverified, and your new one verified.<br>";
				$sqlDELETE = "DELETE FROM $tablename WHERE id = '$oldRowID';";
				if ($DBConnection->query($sqlDELETE) === TRUE) {
				    try {
				    	echo "Removing client " . $oldDBID . " from " . $sg->__toString();
						$sg->clientDel($oldDBID);
					} catch (TeamSpeak3_Exception $e) {
						echo "TS3 error. <br>Servergroup could not be removed from old identity. Contact a Gatekeeper. <br>Error: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
						exit();
					}
				} else {
				    echo "BIAS database error. <br>Security data could not be resolved. Contact a Gatekeeper. <br>Error: " . $DBConnection->error;
				    exit();
				}
				
			}

			if ($DBConnection->query($sqlINSERT) === TRUE) {
				try {
					$client->addServerGroup(TSGROUP);
					echo '<script type="text/javascript">'
					   , 'Redirect();'
					   , '</script>'
					;
					exit();
				} catch (TeamSpeak3_Exception $e) {
					echo "Assignment error <br>Contact a Gatekeeper. <br>Please report this code: " . $e->getCode() . ": " . $e->getMessage() . "<br>";
				}
			} else {
				echo "Connection to BIAS terminated. <br>Error: " . $DBConnection->error;
			}
			$DBConnection->close();
		} catch(Exception $e) {
			echo "Security check failure. Contact a Gatekeeper. <br>Error: " . $e->getMessage();
		}
	} else {
		$client = $ts3_server->clientGetByUid($tsuid);
		echo "The account you just attempted to verify with is not a Blackgate account.<br>";
		echo '<script type="text/javascript">'
		   , 'Redirect();'
		   , '</script>'
		;
		exit();
	}

	echo "</html>";
?>