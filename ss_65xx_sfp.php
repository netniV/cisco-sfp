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

function ss_sfp($hostname, $host_id, $snmp_auth, $cmd, $arg1 = "", $arg2 = "") {
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


        $result			= "";
        $oid_name		= "";
        $sensor_name		= "";
        $sensor_status		= "";
        $sensor_string		= "";
        $tx_status_string	= "";
        $rx_status_string	= "";
        $tx_status		= 0;
        $int			= "";
        $snmp_retries		= read_config_option("snmp_retries");
        $var			= cacti_snmp_walk($hostname, $snmp_community, ".1.3.6.1.4.1.9.9.91.1.1.1.1.1", $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

	if ($cmd == "index") {

		// loop through $var
		for ($i=0;$i<(count($var));$i++) {

			// find dBm entries
			if ($var[$i]["value"] == "14") {

				// get interface name, will duplicate because of TX/RX both having dBm sensors (1st line = TX, 2nd = RX)
				$sensor_name = cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

				// get rid of OID and only care for interface name
				preg_match("/[^\ ]+/", $sensor_name, $oid_name);

				// print it, but skip RX because of duplicates
				print $oid_name[0]."\n";
				$i=$i+1;
			}
		}
	} elseif ($cmd == "query") {

		// loop through $var
		for ($i=0;$i<(count($var));$i++) {

			// find dBm entries
			if ($var[$i]["value"] == "14") {

				// get interface name, will duplicate because of TX/RX both having dBm sensors (1st line = TX, 2nd = RX)
				$sensor_name = cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

				//get interface status: 1 = ok, 2 = unavailable, 3 = nonoperational
                                $sensor_status = cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

				// get rid of OID and only care for interface name
				preg_match("/[^\ ]+/", $sensor_name, $oid_name);

				if ($arg1 == "status") {
					if ($tx_status == 0) {
						if ($sensor_status == 1) {
							$tx_status_string = "TX Online";
							$tx_status = 1;
						} else {
							$tx_status_string = "TX Failure";
							$tx_status = 1;
						}
					} else {
						if ($sensor_status == 1) {
							$rx_status_string = "RX Online";
						} else {
							$rx_status_string = "RX Failure";
						}
						$tx_status = 0;
					}
					if ($tx_status_string && $rx_status_string) {
						print $oid_name[0].":".$tx_status_string." / ".$rx_status_string."\n";
						$i=$i+1;
						$tx_status_string = "";
						$rx_status_string = "";
					}

				} elseif ($arg1 == "descr") {

					$host_id = db_fetch_cell("select id from host where hostname = '$hostname'");
					$snmp_index = db_fetch_cell("select snmp_index from host_snmp_cache where host_id = '$host_id' and field_value = '$oid_name[0]' and field_name = 'ifDescr'");
					$alias = db_fetch_cell("select field_value from host_snmp_cache where snmp_index='$snmp_index' and host_id='$host_id' and field_name='ifAlias'");

					// print it, but skip RX because descriptions are the same
					print $oid_name[0] . ":" . $alias ."\n";
					$i=$i+1;

				} elseif ($arg1 == "index") {

					// print it, but skip RX
					print $oid_name[0].":".$oid_name[0]."\n";
					$i=$i+1;
				}
			}
		}
	} elseif ($cmd == "get") {

		// loop through $var
		for ($i=0;$i<(count($var));$i++) {

			// find dBm entries
			if ($var[$i]["value"] == "14") {

				// get interface name, will duplicate because of TX/RX both having dBm sensors (1st line = TX, 2nd = RX)
				$sensor_name = cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.2.1.47.1.1.1.1.2.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER);

				if ($arg1 == "tx") {
					$int=$arg2." Transmit Power Sensor";
				} elseif ($arg1 == "rx") {
					$int=$arg2." Receive Power Sensor";
				}
				preg_match("/[^\ ]+/", $sensor_name, $oid_name);

				if (strstr($int, $sensor_name)) {
					if (cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.5.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER) == "1") {
						$result = cacti_snmp_get($hostname, $snmp_community, ereg_replace('.*\.[0-9]+\.[0-9]+\.([0-9]+)$', '.1.3.6.1.4.1.9.9.91.1.1.1.1.4.\\1', $var[$i]["oid"]), $snmp_version, $snmp_auth_username, $snmp_auth_password, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_port, $snmp_timeout, $ping_retries, $max_oids, SNMP_POLLER)/10;
					} else {
						// if not ok, send -40, symbolic for lights off
						$result = "-40";
					}
				}
			}
		}
	}
	return trim($result);
}
?>