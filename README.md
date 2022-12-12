# cisco-sfp for cacti 1.x
This is to host code for https://forums.cacti.net/viewtopic.php?f=19&amp;t=23089 for others

Cisco SFP statistics for cacti (c) 2007-2008 sodium 
in 2017 the code copyright moved Github to Creative Commons BY-NC-SA

This code is compatibile with cacti 1.x.  There may be issues with the 0.8.x versions of cacti (use release versions of this script prior to 0.3.0).

## INSTALLATION :
1. copy cisco_sfp.xml to [$cacti_home]/resources/script_server directory
2. copy ss_cisco_catalyst_sfp.php to [$cacti_home]/scripts directory
3. import the following template (within cacti) `cacti_data_query_cisco_catalyst_sfp_statistics.xml`
4. add Cisco - Catalyst - SFP statistics to your catalyst host template or add it to the "Associated Data Queries" in the devices list.

That's it...

## THOLD plugin compatibility

Note: if your are running the thold plugin you should patch it (thold baseline function can't handle negative numbers (you do NOT have to patch if you only do high/low watermark checking.

I have included the path file for thold. (v0.3.9) to appy patch: cacti@nms:/usr/share/cacti/plugins/thold$ patch -p0 < thold_functions-0.3.9.patch

Assumptions made within the code:
- When the script can not read the value from the SFP -40 (dBm) is returned
- snmp_port = 161
- snmp_timeout = 5000 ms
