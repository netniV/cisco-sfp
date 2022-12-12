# Cisco SFP Statistics for Cacti 1.x
This is to host code for https://forums.cacti.net/viewtopic.php?f=19&amp;t=23089 for others to use.  I do not actively maintain this code, but welcome pull requests from others where they believe this can be improved.

Cisco SFP Statistics for Cacti (c) 2007-2008 sodium in 2017 the code copyright moved Github under Creative Commons BY-NC-SA

## Version compatibility
Version | Branch | Compatability | Url
--- | --- | --- | ---
[v0.3.0](https://github.com/netniV/cisco-sfp/releases/tag/0.3.0) - vx.x.x | [master](https://github.com/netniV/cisco-sfp/tree/master) | This code is compatibile with Cacti 1.x. | 
x.x.x - [v0.2.5](https://github.com/netniV/cisco-sfp/releases/tag/0.2.5) | [cacti-v.0.8.8](https://github.com/netniV/cisco-sfp/tree/cacti-v0.8.8) | This code is compatible with Cacti 0.8.8x

## How to install
1. Copy `cisco_sfp.xml` to `[$cacti_home]/resources/script_server` directory
2. Copy `ss_cisco_catalyst_sfp.php` to `[$cacti_home]/scripts` directory
3. From the Cacti web console, import the template `cacti_data_query_cisco_catalyst_sfp_statistics.xml`
4. Add `Cisco - Catalyst - SFP statistics` to the desired host template, or add it to the `Associated Data Queries` section when editing host.

That's it...

## Compatibility with THold monitoring plugin

If your are running the thold plugin you may need to patch it as the `baseline` function can't handle negative numbers under earlier versions of the plugin. If you only utilise `high/low` watermark checking, you do NOT need to patch.

NOTE: The patch file was made against THold v0.3.9 and may no longer apply.  Use of the patch is at your descresion

```shell
cacti/plugins/thold$ patch -p0 < thold_functions-0.3.9.patch
```

Assumptions made within the code:
- When the script can not read the value from the SFP -40 (dBm) is returned
- snmp_port = 161
- snmp_timeout = 5000 ms
