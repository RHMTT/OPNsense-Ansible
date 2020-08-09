<?php

$json = file_get_contents('./array_short.json', true);
$dhcp = json_decode($json, true);

$filters = json_decode($_GET['filter'], true);

/*
http://10.200.8.117/?filter=[
{"field":"ip","match":"^10.200.5","group":"CAM"},
{"field":"ip","match":"^10.200.7","group":"KEV"},

{"field":"hostname","match":"echo","group":"IOT"},
{"field":"vendor","match":"belkin","group":"IOT"},
{"field":"hostname","match":"thermostat","group":"IOT"},

{"field":"vendor","match":"polycom","group":"PHONE"},
{"field":"vendor","match":"synology","group":"KEV"},
{"field":"DEFAULT","match":"DEFAULT","group":"DELETE"}

,{"default":"DEFAULTGROUP"}
]

// When no groups are matched, the (optional) default group is associated.
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
			if ( isset($filter['default']) and ! isset($default_group)){
				$default_group = $filter['default'];
			}else{
				$field = $filter['field'];
				$match = $filter['match'];
				$group = $filter['group'];

				if (preg_match("/$field/",$var)){
					if (preg_match("/$match/i",$val)){
						if ($group){
							$groupct = ($groupct + 1);
							$newarray[$id]['groups'][] = $group ;
						}
					}
				}
			}
		}
	}
	$newarray[$id]['groupcount'] = $groupct;
	if ($groupct == 0) $newarray[$id]['groups'][] = $default_group ;
}


print "<pre>\n";
print_r($newarray);
print "</pre>\n";


?>

