<?php


// this file was modified from the original dhcp_leases.php file by GoKEV

//  Drop this into your OPNsense at:
//  /usr/local/www/
//
//  and hit it by visiting
//  http://$OPNSENSE/ansible_leases_json.php



/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("config.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/dhcpd.inc");

function adjust_utc($dt)
{
    foreach (config_read_array('dhcpd') as $dhcpd) {
        if (!empty($dhcpd['dhcpleaseinlocaltime'])) {
            /* we want local time, so specify this is actually UTC */
            return strftime('%Y/%m/%d %H:%M:%S', strtotime("{$dt} UTC"));
        }
    }

    /* lease time is in UTC, here just pretend it's the correct time */
    return strftime('%Y/%m/%d %H:%M:%S UTC', strtotime($dt));
}

function remove_duplicate($array, $field)
{
    foreach ($array as $sub) {
        $cmp[] = $sub[$field];
    }
    $unique = array_unique(array_reverse($cmp,true));
    foreach ($unique as $k => $rien) {
        $new[] = $array[$k];
    }
    return $new;
}

$interfaces = legacy_config_get_interfaces(array('virtual' => false));
$leasesfile = dhcpd_dhcpv4_leasesfile();

    $awk = "/usr/bin/awk";
    /* this pattern sticks comments into a single array item */
    $cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";
    /* We then split the leases file by } */
    $splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

    /* stuff the leases file in a proper format into an array by line */
    exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
    $leases_count = count($leases_content);
    exec("/usr/sbin/arp -an", $rawdata);
    $arpdata_ip = array();
    $arpdata_mac = array();
    foreach ($rawdata as $line) {
        $elements = explode(' ',$line);
        if ($elements[3] != "(incomplete)") {
            $arpent = array();
            $arpdata_ip[] = trim(str_replace(array('(',')'),'',$elements[1]));
            $arpdata_mac[] = strtolower(trim($elements[3]));
        }
    }
    unset($rawdata);
    $pools = array();
    $leases = array();
    $i = 0;
    $l = 0;
    $p = 0;


    // Put everything together again
    foreach($leases_content as $lease) {
        /* split the line by space */
        $data = explode(" ", $lease);
        /* walk the fields */
        $f = 0;
        $fcount = count($data);
        /* with less then 20 fields there is nothing useful */
        if ($fcount < 20) {
            $i++;
            continue;
        }
        while($f < $fcount) {
            switch($data[$f]) {
                case "failover":
                    $pools[$p]['name'] = trim($data[$f+2], '"');
                    $pools[$p]['name'] = "{$pools[$p]['name']} (" . convert_friendly_interface_to_friendly_descr(substr($pools[$p]['name'], 5)) . ")";
                    $pools[$p]['mystate'] = $data[$f+7];
                    $pools[$p]['peerstate'] = $data[$f+14];
                    $pools[$p]['mydate'] = $data[$f+10];
                    $pools[$p]['mydate'] .= " " . $data[$f+11];
                    $pools[$p]['peerdate'] = $data[$f+17];
                    $pools[$p]['peerdate'] .= " " . $data[$f+18];
                    $p++;
                    $i++;
                    continue 3;
                case "lease":
                    $leases[$l]['ip'] = $data[$f+1];
                    $leases[$l]['type'] = "dynamic";
                    $f = $f+2;
                    break;
                case "starts":
                    $leases[$l]['start'] = $data[$f+2];
                    $leases[$l]['start'] .= " " . $data[$f+3];
                    $f = $f+3;
                    break;
                case "ends":
                    $leases[$l]['end'] = $data[$f+2];
                    $leases[$l]['end'] .= " " . $data[$f+3];
                    $f = $f+3;
                    break;
                case "tstp":
                    $f = $f+3;
                    break;
                case "tsfp":
                    $f = $f+3;
                    break;
                case "atsfp":
                    $f = $f+3;
                    break;
                case "cltt":
                    $f = $f+3;
                    break;
                case "binding":
                    switch($data[$f+2]) {
                        case "active":
                            $leases[$l]['act'] = "active";
                            break;
                        case "free":
                            $leases[$l]['act'] = "expired";
                            $leases[$l]['online'] = "offline";
                            break;
                        case "backup":
                            $leases[$l]['act'] = "reserved";
                            $leases[$l]['online'] = "offline";
                            break;
                    }
                    $f = $f+1;
                    break;
                case "next":
                    /* skip the next binding statement */
                    $f = $f+3;
                    break;
                case "rewind":
                    /* skip the rewind binding statement */
                    $f = $f+3;
                    break;
                case "hardware":
                    $leases[$l]['mac'] = $data[$f+2];
                    /* check if it's online and the lease is active */
                    if (in_array($leases[$l]['ip'], $arpdata_ip)) {
                        $leases[$l]['online'] = 'online';
                    } else {
                        $leases[$l]['online'] = 'offline';
                    }
                    $f = $f+2;
                    break;
                case "client-hostname":
                    if ($data[$f + 1] != '') {
                        $leases[$l]['hostname'] = preg_replace('/"/','',$data[$f + 1]);
                    } else {
                        $hostname = gethostbyaddr($leases[$l]['ip']);
                        if ($hostname != '') {
                            $leases[$l]['hostname'] = $hostname;
                        }
                    }
                    $f = $f+1;
                    break;
                case "uid":
                    $f = $f+1;
                    break;
          }
          $f++;
        }
        $l++;
        $i++;
        /* slowly chisel away at the source array */
        array_shift($leases_content);
    }
    /* remove the old array */
    unset($lease_content);

    /* remove duplicate items by mac address */
    if (count($leases) > 0) {
        $leases = remove_duplicate($leases,"ip");
    }

    if (count($pools) > 0) {
        $pools = remove_duplicate($pools,"name");
        asort($pools);
    }

    $macs = [];
    foreach ($leases as $i => $this_lease) {
        if (!empty($this_lease['mac'])) {
            $macs[$this_lease['mac']] = $i;
        }
    }
    foreach ($interfaces as $ifname => $ifarr) {
        if (isset($config['dhcpd'][$ifname]['staticmap'])) {
            foreach($config['dhcpd'][$ifname]['staticmap'] as $static) {
                $slease = array();
                $slease['ip'] = $static['ipaddr'];
                $slease['type'] = "static";
                $slease['mac'] = $static['mac'];
                $slease['start'] = '';
                $slease['end'] = '';
                $slease['hostname'] = $static['hostname'];
                $slease['descr'] = $static['descr'];
                $slease['act'] = "static";
                $slease['online'] = in_array(strtolower($slease['mac']), $arpdata_mac) ? 'online' : 'offline';
                if (isset($macs[$slease['mac']])) {
                    // update lease with static data
                    foreach ($slease as $key => $value) {
                        if (!empty($value)) {
                            $leases[$macs[$slease['mac']]][$key] = $slease[$key];
                        }
                    }
                } else {
                    $leases[] = $slease;
                }
            }
        }
    }


//    $order = ( $_GET['order'] ) ? $_GET['order'] : 'ip';
    $order = "mac";

// GoKEV note:  List the leases in order of...

// order=mac
// order=ip
// order=hostname
// order=descr
// order=start
// order=end
// order=online
// order=act    (expired / static / active)

// and I set ALL to a static value:
$_GET['all'] = '1';



    usort($leases,
        function ($a, $b) use ($order) {
            $cmp = strnatcasecmp($a[$order], $b[$order]);
            if ($cmp === 0) {
                $cmp = strnatcasecmp($a['ip'], $b['ip']);
            }
            return $cmp;
        }
    );


$service_hook = 'dhcpd';

include("head.inc");

$leases_count = 0;

print "<pre>\n";
print "opnsense_leases:\n";

foreach ($leases as $data) {
	$macsplode = explode(":",$data['mac']);
	$search_mac = strtoupper($macsplode[0] . ":" . $macsplode[1] . ":" . $macsplode[2]);
	$vendor_reply = exec ( "egrep \"^$search_mac\" mac_map.txt | head -n 1" );
	$vreply = explode("\t",$vendor_reply);
	$vendor = (isset($vreply[2]) ? $vreply[2] : $vreply[1]);

	print "  - mac: " . preg_replace("/:/","-",$data['mac']) . "\n";
	print "    vendor: " . $vendor . "\n";
	print "    ip: " . $data['ip'] . "\n";
	print "    leasetype: " . $data['act'] . "\n";
	print "    hostname: " . $data['hostname'] . "\n";
	print "    description: " . $data['descr'] . "\n";
	print "    online: " . $data['online'] . "\n";


}
print "</pre>\n";

