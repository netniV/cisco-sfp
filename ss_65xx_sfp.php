<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 sodium                                           |
 |                                                                       |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License       |
 | as published by the Free Software Foundation; either version 2         |
 | of the License, or (at your option) any later version.                 |
 |                                                                       |
 | This program is distributed in the hope that it will be useful,       |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                     |
 +-------------------------------------------------------------------------+
 | name    : ss_65xx_sfp.php                                           |
 | version : 0.2.2                                                       |
 | date    : 20080429                                                 |
 +-------------------------------------------------------------------------+
*/
$no_http_headers = true;
error_reporting(E_ERROR);

include_once(dirname(__FILE__) . "/../lib/snmp.php");

if (!isset($called_by_script_server)) {
        include_once(dirname(__FILE__) . "/../include/config.php");
        include_once(dirname(__FILE__) . "/../include/global.php");
        array_shift($_SERVER["argv"]);
        print call_user_func_array("ss_sfp", $_SERVER["argv"]);

}

# PortIndex - 1.3.6.1.2.1.47.1.1.1.1.2
# entSensorValue - 1.3.6.1.4.1.9.9.91.1.1.1.1.4 (RX = .4.[index] TX =.4.[index] + 1)
# entSensorStatus - 1.3.6.1.4.1.9.9.91.1.1.1.1.5 (1 = ok, 2 = unavailable, 3 = nonoperational)
# entSensorType - 1.3.6.1.4.1.9.9.91.1.1.1.1.1 (output type 14 = in dBm)
#
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 index
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 query index|status|descr
# /usr/bin/php -q /usr/share/cacti/scripts/ss_65xx_sfp.php 192.168.1.11 public 2 get rx|tx TenGigabitEthernet1/1
#

function ss_sfp($hostname, $host_id, $snmp_auth, $cmd, $direction = "", $interface = "") {
        $snmp           = explode(":", $snmp_auth);
        $snmp_version   = $snmp[0];
        $snmp_port      = $snmp[1];
        $snmp_timeout   = $snmp[2];
        $ping_retries   = $snmp[3];
        $max_oids       = $snmp[4];

        $snmp_auth_username     = "";
        $snmp_auth_password     = "";
        $snmp_auth_protocol     = "";
        $snmp_priv_passphrase   = "";
        $snmp_priv_protocol     = "";
        $snmp_context           = "";
        $snmp_community         = "";

        if ($snmp_version == 3) {
                $snmp_auth_username     = $snmp[6];
                $snmp_auth_password     = $snmp[7];
                $snmp_auth_protocol     = $snmp[8];
                $snmp_priv_passphrase   = $snmp[9];
                $snmp_priv_protocol     = $snmp[10];
                $snmp_context           = $snmp[11];
        } else {
                $snmp_community         = $snmp[5];
        }




        $result         = "";
        $oid_name       = "";
        $sensor_name    = "";
        $sensor_status  = "";
        $sensor_string  = "";
        $status_string  = "";
        $tx_status      = 0;
        $int            = "";
        $snmp_retries   = read_config_option("snmp_retries");
        $var            = (cacti_snmp_walk($hostname, $snmp_community, ".1.3.6.1.4.1.9.9.91.1.1.1.1.1", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));

        if ($cmd == "index" || $cmd == "query") {
                for ($i=0;$i<(count($var));$i++) {
                        if ($var[$i]["value"] == "14") {                // found a dBm entry
                                $sensor_name = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
                                $sensor_status = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));
				

				// Extract the Slot/Module/Port from "subslot 3/0 transceiver 8 Rx Power Sensor"
				
				if (preg_match_all("/subslot (\d+\/\d+) transceiver (\d+) .x Power Sensor/"
				                   ,$sensor_name,$matches,PREG_PATTERN_ORDER)) {
					
					$modSlotPort = $matches[1][0].'/'.$matches[2][0];
					$oid_name[0] = "";						// Reset this to null

					// Test interface exists in the host_snmp_cache table 
					// Try to match Gig and Ten Gig combinations
					
					foreach (array('GigabitEthernet','TenGigabitEthernet') as $type) {
					
						$test_name = $type.$modSlotPort;
			
						if (db_fetch_cell(
						"select snmp_index from host_snmp_cache 
							where host_id = '$host_id' and 
							field_value = '$test_name' and 
							field_name = 'ifDescr'")) {
	
								$oid_name[0] = $test_name;
						}
					}
				}
				else {				

         	                       preg_match("/[^\ ]+/", $sensor_name, $oid_name); // don't care about the rest of the string
	
				}


                                if ($cmd == "index") {
                                        print $oid_name[0]."\n";
                                } elseif ($cmd == "query") {
                                        switch ($direction) {
                                                case "index":
                                                        print $oid_name[0].":".$oid_name[0]."\n";
                                                        $i=$i+1;        // skip rx
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
                                                        }
                                                        $tx_status = 0;
                                                        print $oid_name[0].":".$status_string."\n";
                                                break;
                                                case "descr":
                                                        $host_id = db_fetch_cell("select id from host where hostname = '$hostname'");
                                                        $snmp_index = db_fetch_cell("select snmp_index from host_snmp_cache where host_id = '$host_id' and field_value = '$oid_name[0]' and field_name = 'ifDescr'");
                                                        $alias = db_fetch_cell("select field_value from host_snmp_cache where snmp_index='$snmp_index' and host_id='$host_id' and field_name='ifAlias'");
							$alias = preg_replace("/:/","-",$alias); // Fix script server exploding args
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

                                $sensor_name = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER));

		
				// For some reason the TenGigabitEthernet transceivers on the SUP cards return results in 1/10th dB
				// ..and the [Ten]GigabitEthernet interfaces on SPAs are rounded down to the nearest whole DB. 
				// Nice Cisco..


				$divisor = 1;		// By default we devide by 1

				// Reverse GigabitEthernetx\y\z into subslot x/y transceiver z Rx Power Sensor

				if (preg_match("/[Ten]?GigabitEthernet(\d+)\/(\d+)\/(\d+)$/",
				    $interface,$matches)) {
					$suffix = $direction == "tx" ? "Tx Power Sensor" : "Rx Power Sensor";
					$int = "subslot ".$matches[1]."/".$matches[2]." transceiver ".$matches[3] ." $suffix";

				}
				elseif (preg_match("/TenGigabitEthernet\d+\/\d+$/",$interface)) {
			
					$divisor = 10;

                                	if ($direction == "tx") {
					
                                        	$int=$interface." Transmit Power Sensor";


                                	} elseif ($direction == "rx") {
                                        	$int=$interface." Receive Power Sensor";
                               	 }
				}
				
                                preg_match("/[^\ ]+/", $sensor_name, $oid_name);
                                if (strstr($int, $sensor_name)) {


                                        if (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER) == "1") {
                                                $result = (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.4.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER))/$divisor;
                                        } else {
                                                $result = "-40";        // lights are off
                                        }
                                }
                        }
                }
        }
        return trim($result);
}

?>
