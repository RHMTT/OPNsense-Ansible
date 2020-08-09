<?php

// This doesn't yet work in OPNsense.  In testing, this ttakes the array from a static file (identtical to the one in OPNsense

$json = file_get_contents('./array_short.json', true);
$dhcp = json_decode($json, true);

$filters = json_decode($_GET['filter'], true);

/*
http://10.200.8.117/?filter=[
{"field":"ip","match":"^10.20.5","group":"GROUP2"},
{"field":"ip","match":"^10.20.7","group":"KEV"},

{"field":"hostname","match":"echo","group":"IOT"},
{"field":"vendor","match":"belkin","group":"IOT"},
{"field":"hostname","match":"thermostat","group":"IOT"},

{"field":"vendor","match":"polycom","group":"PHONE"},
{"field":"vendor","match":"synology","group":"KEV"},
{"field":"DEFAULT","match":"DEFAULT","group":"DELETE"}

,{"default":"DEFAULT"}
]

*/

$newarray = array();

foreach( $dhcp as $id => $values){
	foreach( $values as $var => $val){
		if (preg_match("/^ip$|^mac$|^hostname$|^descr$/",$var)) $newarray[$id][$var] = $val;

		if (preg_match("/^mac$/",$var)){
			$thismac = $val;

			$macsplode = explode(":",$thismac);
			$search_mac = strtoupper($macsplode[0] . ":" . $macsplode[1] . ":" . $macsplode[2]);
			$vendor_reply = exec ( "egrep \"^$search_mac\" mac_map.txt | head -n 1" );
			$vreply = explode("\t",$vendor_reply);
			$vendor = (isset($vreply[2]) ? $vreply[2] : $vreply[1]);
			$newarray[$id]['vendor'] = $vendor;
		}
	}
}

foreach( $newarray as $id => $values){
	$groupct = 0;
        foreach( $values as $var => $val){
		foreach($filters as $filter){
			$field = $filter['field'];
			$match = $filter['match'];
			$group = $filter['group'];

			if (preg_match("/$field/",$var)){
				if (preg_match("/$match/i",$val)){
					$groupct = ($groupct + 1);
					$newarray[$id]['groups'][] = $group ;
				}
			}
		}
		$newarray[$id]['groupcount'] = $groupct;
}

//$newarray = array_map("unserialize", array_unique(array_map("serialize", $newarray)));

print "<pre>\n";
print "\n\n\nNEW ARRAY:\n";
print_r($newarray);
print "\n\n\nOLD ARRAY:\n";
print_r($dhcp);
print "</pre>\n";



?>


