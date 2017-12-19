<?php
# vim:ts=2:set noai:
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 sodium                                               |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | name    : ss_65xx_sfp.php                                               |
 | version : 0.2.2                                                         |
 | date    : 20080429                                                      |
 +-------------------------------------------------------------------------+
*/
$no_http_headers = true;
error_reporting(E_ERROR);

include_once(dirname(__FILE__) . "/../lib/snmp.php");

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . "/../include/config.php");
	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_sfp", $_SERVER["argv"]);

}

# PortIndex 			1.3.6.1.2.1.47.1.1.1.1.2
# entSensorValue 	1.3.6.1.4.1.9.9.91.1.1.1.1.4 (RX = .4.[index] TX =.4.[index] + 1)
# entSensorStatus 1.3.6.1.4.1.9.9.91.1.1.1.1.5 (1 = ok, 2 = unavailable, 3 = nonoperational)
# entSensorType 	1.3.6.1.4.1.9.9.91.1.1.1.1.1 (output type 14 = in dBm)
#
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 index
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 query index|status|descr
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 get rx|tx TenGigabitEthernet1/1
# 
function ss_sfp($hostname, $snmp_community, $snmp_version, $cmd, $direction = "", $interface = "") {

	$result = "";
	$oid_name = "";
  $sensor_name = "";
  $sensor_status = "";
	$sensor_string = "";
	$status_string = "";
	$tx_status = 0;
	$int = "";
	$snmp_retries = read_config_option("snmp_retries");
	$var = (cacti_snmp_walk($hostname, $snmp_community, ".1.3.6.1.4.1.9.9.91.1.1.1.1.1", $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER));

	if ($cmd == "index" || $cmd == "query") {
		for ($i=0;$i<(count($var));$i++) {
			if ($var[$i]["value"] == "14") {                // found a dBm entry
				$sensor_name = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER));
				$sensor_status = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER));
      	preg_match("/[^\ ]+/", $sensor_name, $oid_name); // don't care about the rest of the string
				if ($cmd == "index") {
      		print $oid_name[0]."\n";
				} elseif ($cmd == "query") {
					switch ($direction) {
						case "index":
							print $oid_name[0].":".$oid_name[0]."\n";
							$i=$i+1;	// skip rx
						break;
						case "status":
							if ($tx_status == 0) { 
								if ($sensor_status == 1) { 
									$status_string = "Online"; 
								} else { 
									$status_string = "Tx failure"; 
								}
								$tx_status = 1; 
							} else { 
								if ($sensor_status != 1) { 
									if ($status_string == "Online") {
										$status_string = "Rx failure";	
									} else {
										$status_string = $status_string . " Rx failure";
									}
								}
								$tx_status = 0; 
								print $oid_name[0].":".$status_string."\n"; 
							}
						break;
						case "descr":
							$host_id = db_fetch_cell("select id from host where hostname = '$hostname'");
							$snmp_index = db_fetch_cell("select snmp_index from host_snmp_cache where host_id = '$host_id' and field_value = '$oid_name[0]' and field_name = 'ifDescr'");
							$alias = db_fetch_cell("select field_value from host_snmp_cache where snmp_index='$snmp_index' and host_id='$host_id' and field_name='ifAlias'");
							print $oid_name[0] . ":" . $alias ."\n";
							$i=$i+1;  // rx & tx description are the same
						break;
					}
				}
			}
		}
	} elseif ($cmd == "get") {
		for ($i=0;$i<(count($var));$i++) {
			if ($var[$i]["value"] == "14") {                // found a dBm entry
				$sensor_name = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, "", "", "", 5000, $snmp_retries, SNMP_POLLER));
				if ($direction == "tx") { 
					$int=$interface." Transmit Power Sensor"; 
				} elseif ($direction == "rx") { 
					$int=$interface." Receive Power Sensor"; 
				}
				preg_match("/[^\ ]+/", $sensor_name, $oid_name);
				if (strstr($int, $sensor_name)) {
					if (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER) == "1") {
						$result = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.4.\\1', $var[$i]["oid"]), $snmp_version, "", "", 161, 5000, $snmp_retries, SNMP_POLLER))/10;
					}
					else { $result = "-40"; // lights are off
					}
				}
			}
		}
	}
return trim($result);
}
?>
