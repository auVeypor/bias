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
	const TSGROUP = 573;

	function deverify($dbindex, $tsdbid, $ts3_server, $DBConnection, $tablename) {	
		
		$sqlDELETE = "DELETE FROM $tablename WHERE id = '$dbindex';";
		if ($DBConnection->query($sqlDELETE) === TRUE) {
			try {
				$groupList = $ts3_server->clientGetServerGroupsByDbid($tsdbid);
				foreach ($groupList as $group) {
					$i = 0;
					foreach ($group as $key) {
						if ($i % 2 == 1) {
							try {
								$ts3_server->serverGroupClientDel($key, $tsdbid);
								$i++;
							} catch (TeamSpeak3_Exception $e) {
								echo "Error " . $e->getCode() . ": " . $e->getMessage();
							}
						} else {
							$i++;
						}
					}
				} 
			} catch (TeamSpeak3_Exception $e) {
				exit();
			}
		}
	}

	$DBConnection = new mysqli($SQLHost, $SQLUser, $SQLPass, $SQLDBName);
	if ($DBConnection->connect_error) {
		exit();
	}

	$sqlGRAB = "SELECT * FROM $tablename;";

	try {
		$result = $DBConnection->query($sqlGRAB);
	} catch (Exception $e) {
		exit();
	}

	try {
		$api = new \GW2Treasures\GW2Api\GW2Api();
	} catch (Exception $e) {
		exit();
	}

	try {
		$ts3_server = TeamSpeak3::factory("serverquery://$TSUser:$TSPassword@$TSIP");
	} catch (TeamSpeak3_Exception $e) {
		exit();
	}

	while($row = $result->fetch_assoc())
	{
		$valid = true;
		$gw2key = $row['apikey'];

		if ($valid == true) {
			try {
				$account = $api->account($gw2key)->get();
			} catch(Exception $e) {
				deverify($row['id'],$row['tsdbid'],$ts3_server,$DBConnection,$tablename);
				$valid = false;
			}
		}

		if ($valid == true) {
			$world = $account->world;
			if ($world != BGWID) {
				deverify($row['id'],$row['tsdbid'],$ts3_server,$DBConnection,$tablename);
				$valid = false;
			}
		}
	}

?>
