<?php

/*
Some files to check out:

/var/unbound/dhcpleases.conf	unbound leases
/usr/local/etc/dnsmasq.conf	dnsmasq config (no idea where the leases file is stored since I'm not using it)
	the option dhcp-leasefile=<lease file>. E.g. dhcp-leasefile=/var/lib/dnsmasq/dnsmasq.leases



*/

$json = file_get_contents('./array_short.json', true);
$dhcp = json_decode($json, true);
$output = ($_REQUEST['output']);

if (isset($_GET['filter'])){
	$textfilter = $_REQUEST['filter'];
	$urlfilter = urlencode($_REQUEST['filter']);
	$urlfilter = urlencode($_REQUEST['filter']);
	$filters = json_decode($_REQUEST['filter'], true);
}

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
//			if ( isset($filter['default']) and ! isset($default_group)){
			if ( isset($filter['default'])){
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


if ($output == "json"){
	header('Content-Type: application/json');
	$results = json_encode($newarray,JSON_PRETTY_PRINT);
}elseif ($output == "dict"){
	header('Content-Type: text/plain');
	$results = "opnsense_leases:\n";
	foreach ($newarray as $data) {
		$results .= "  - mac: " . preg_replace("/:/","-",$data['mac']) . "\n";
		$results .="    vendor: " . $data['vendor'] . "\n";
		$results .="    ip: " . $data['ip'] . "\n";
		$results .="    hostname: " . $data['hostname'] . "\n";
		$results .="    description: " . $data['descr'] . "\n";
		$results .="    groups: \n";

		foreach ($data['groups'] as $group){
			$results .="      - $group" . "\n";
		}


	}

}else{
	$json_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . "/" . $_SERVER['SCRIPT_NAME'] . "?output=json&filter=" . $urlfilter;
	$dict_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . "/" . $_SERVER['SCRIPT_NAME'] . "?output=dict&filter=" . $urlfilter;

	$results=<<<ALLDONE
<font face="verdana,sans,arial">
<textarea cols="100" rows="10">$textfilter</textarea><br>
<a href="$json_url">JSON</a> output<br>
<a href="$dict_url">DICT</a> output<br>
		This

ALLDONE;
	$results .= "<pre>" . print_r($newarray, true) . "</pre>";
}


?>
<?=$results?>
