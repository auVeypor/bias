<?php

/*
 *
 *  Sentry script designed to be cron'ed. Navigates the DB and makes sure all registrants are still on
 *	the authorised server.
 *  
 *  This script uses the TS3 PHP framework: (https://docs.planetteamspeak.com/ts3/php/framework/)
 *  All credit to ScP for the framework.
 *
 *	This script uses the very sexy GW2Treasure API wrapper, big props to them: (https://github.com/gw2treasures/gw2api)
 *
 *	Finally, this script uses the GW2 API, developed by ArenaNet LLC. (http://www.arena.net/)
 *
 *  Authored by Veypor (http://github.com/auVeypor) (veypor@veypor.net)
 */

	include 'vendor/autoload.php';
	include '../../auth.php';
	require_once("libraries/TeamSpeak3/TeamSpeak3.php");

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	const BGWID = 1019;
	const TSGROUP = 573;
	$today = date("Y-m-d");

	error_log("~~~ Begin BIAS Reverification Log for " . $today . ".\n", 3, "/home/veypor/logs/" . $today . ".log");
	$startTime = microtime(true);

	function deverify($dbindex, $tsdbid, $ts3_server, $tablename, $today) {	

		include '../../auth.php';
		$DBConnection = new mysqli($SQLHost, $SQLUser, $SQLPass, $SQLDBName);
		if ($DBConnection->connect_error) {
			error_log("ERROR: Database connectivity problems when forging connection.\n", 3, "/home/veypor/logs/" . $today . ".log");
			exit();
		}

		$sqlDELETE = "DELETE FROM $tablename WHERE id = '$dbindex';";
		if ($DBConnection->query($sqlDELETE) === TRUE) {
			mysqli_close($DBConnection);
			try {
				$groupList = $ts3_server->clientGetServerGroupsByDbid($tsdbid);
				foreach ($groupList as $group) {
					$i = 0;
					foreach ($group as $key) {
						if ($i % 2 == 1) {
							try {
								$ts3_server->serverGroupClientDel($key, $tsdbid);
								error_log("REMOVED: Servergroup " . $key . " from TSDBID: " . $tsdbid . "\n", 3, "/home/veypor/logs/" . $today . ".log");
								$i++;
							} catch (TeamSpeak3_Exception $e) {
								error_log("ERROR: When removing group " . $key ." from user " . $tsdbid . ". Code: " . $e->getCode() . ": " . $e->getMessage() . "\n", 3, "/home/veypor/logs/" . $today . ".log");
							}
						} else {
							$i++;
						}
					}
				} 
			} catch (TeamSpeak3_Exception $e) {
				error_log("ERROR: TS3 server problems with deleting DBI: " . $dbindex . " TSDBID: " . $tsdbid . ". Code: " . $e->getCode() . ": " . $e->getMessage() . "\n", 3, "/home/veypor/logs/" . $today . ".log");
				exit();
			}
		} else {
			mysqli_close($DBConnection);
			error_log("ERROR: Database connectivity problems when deleting DBI: " . $dbindex . " TSDBID: " . $tsdbid . "\n", 3, "/home/veypor/logs/" . $today . ".log");
		}
	}

	$DBConnection = new mysqli($SQLHost, $SQLUser, $SQLPass, $SQLDBName);
	if ($DBConnection->connect_error) {
		error_log("ERROR: Database connectivity problems when forging connection.\n", 3, "/home/veypor/logs/" . $today . ".log");
		exit();
	}

	$sqlGRAB = "SELECT * FROM $tablename;";

	try {
		$result = $DBConnection->query($sqlGRAB);
		mysqli_close($DBConnection);
	} catch (Exception $e) {
		error_log("ERROR: Database connectivity problems when fetching data.\n", 3, "/home/veypor/logs/" . $today . ".log");
		exit();
	}

	try {
		$api = new \GW2Treasures\GW2Api\GW2Api();
	} catch (Exception $e) {
		error_log("ERROR: Wrapper construction error.\n", 3, "/home/veypor/logs/" . $today . ".log");
		exit();
	}

	try {
		$ts3_server = TeamSpeak3::factory("serverquery://$TSUser:$TSPassword@$TSIP");
	} catch (TeamSpeak3_Exception $e) {
		error_log("ERROR: Forging TS3 connection. Code: " . $e->getCode() . ": " . $e->getMessage() . "\n", 3, "/home/veypor/logs/" . $today . ".log");
		exit();
	}

	try {
		$riverRats = $api->quaggans()->many(['cheer', 'party']);
	} catch(ApiException $e) {
		error_log("WARNING: ApiException thrown after a Quaggan probe. API server may be offline. BIAS is aborting sentry procedures.", 3, "/home/veypor/logs/" . $today . ".log");
		exit();
	}

	$numChecks = 0;
	$numHits = 0;

	while($row = $result->fetch_assoc())
	{
		$valid = true;
		$gw2key = $row['apikey'];
		$tsdbid = $row['tsdbid'];
		$tsdname = $row['tsname'];

		if ($valid == true) {
			try {
				$account = $api->account($gw2key)->get();
			} catch(AuthenticationException $e) {
				error_log("INVALID KEY: User " . $tsdname .  " with TSDBID: " . $tsdbid . " has an invalid API key. Triggering deverification procedures. " . $tsdbid . "\n", 3, "/home/veypor/logs/" . $today . ".log");
				deverify($row['id'],$tsdbid,$ts3_server,$tablename,$today);
				$numHits++;
				$valid = false;
			}
			catch(ApiException $e) {
				error_log("WARNING: ApiException thrown when accessing account data. API server may be offline. BIAS is aborting sentry procedures.", 3, "/home/veypor/logs/" . $today . ".log");
				exit();
			}
		}

		if ($valid == true) {
			$world = $account->world;
			if ($world != BGWID) {
				error_log("INVALID WORLD: User " . $tsdname .  " with TSDBID: " . $tsdbid . " is on unauthorised world " . $world . ". Triggering deverification procedures. " . $tsdbid . "\n", 3, "/home/veypor/logs/" . $today . ".log");
				deverify($row['id'],$tsdbid,$ts3_server,$tablename,$today);
				$numHits++;
				$valid = false;
			}
		}
		$numChecks++;
	}

	$time_elapsed_secs = (microtime(true) - $startTime);
	error_log("~~~ End BIAS Reverification Log for " . $today . ".\nToday's run took " . $time_elapsed_secs . " seconds to complete.\n" . $numChecks . " users were scanned and " . $numHits . " were deverified.\n", 3, "/home/veypor/logs/" . $today . ".log");

?>
