<?php
/**
 *
 * Icinga2 Wallboard
 * Make use of Icinga2's API to display status. Suitable for data room screens.
 * 
 * Inspired by Naglite3 (https://github.com/saz/Naglite3) and 
 * Icinga2 API example (https://github.com/Icinga/icinga2-api-examples/blob/master/scripts/objects/services/problems.php)
 *
 * @author	Andrei Buzoianu <andrei.buzoianu@gmail.com>
 * @version	0.1
 * @license	GPL
 *
 * */

// Icinga2 host
$ApiHost = "localhost";

// Default Icinga2 API username
$ApiUser = 'root';

// Default Icinga2 API Password
$ApiPass = 'icinga';

// Default refresh time in seconds
$refresh = 10;

// Default heading
$wallHeading = 'Icinga2 Monitoring System';

// Also show hostname in host and service lists
$showHostname = FALSE;

// Icinga 2 API: PHP API client
require_once('api.php');

// If there is a config file, require it to overwrite some values
$config = 'config.php';
if (file_exists($config)) {
    require $config;
}

// Disable caching
header("Pragma: no-cache");

// Set refresh interval
if (!empty($_GET["refresh"]) && is_numeric($_GET["refresh"])) {
	$refresh = $_GET["refresh"];
}
header("Refresh: " .$refresh);

// Status Map
$icinga["host"]["ok"] = 0;
$icinga["host"]["down"] = 1;
$icinga["host"]["unreachable"] = 2;
$icinga["host"] += array_keys($icinga["host"]);
$icinga["service"]["ok"] = 0;
$icinga["service"]["warning"] = 1;
$icinga["service"]["critical"] = 2;
$icinga["service"]["unknown"] = 3;
$icinga["service"] += array_keys($icinga["service"]);

function duration($end) {
	$DAY = 86400;
	$HOUR = 3600;
	$now = time();
	$diff = $now - $end;
	$days = floor($diff / $DAY);
	$hours = floor(($diff % $DAY) / $HOUR);
	$minutes = floor((($diff % $DAY) % $HOUR) / 60);
	$secs = $diff % 60;
	return sprintf("%dd, %02d:%02d:%02d", $days, $hours, $minutes, $secs);
}
function serviceTable($icinga, $services, $select = false, $type = false) {
	global $showHostname;

	if (false === $type) {
		print("<table><tr>\n");
	} else {
		print(sprintf("<table><tr class='%s'>\n", $type));
	}
	print("<th>Host</th><th>Service</th><th>Status</th><th>Duration</th><th>Attempts</th><th>Plugin Output</th>\n");
	print("</tr>");
    foreach ($select as $selectedType) {
        if ($services[$selectedType]) {
            foreach ($services[$selectedType] as $service) {
                $showService = true;
                if (in_array($service["joins"]["host"]["acknowledgement"], array("1", "2"))) {
                    $showService = false;
                }
                if ($service["joins"]["host"]["state"] == $icinga["host"]["down"]) {
                    $showService = false;
                }
                if ($showService) {
                    $state = $icinga["service"][$service["attrs"]["state"]];
                    if (false === $type) {
                        $rowType = $state;
                    } else {
                        $rowType = $type;
                        if ("acknowledged" !== $type) {
                            $state = $type;
                        }
                    }
                    print(sprintf("<tr class='%s'>\n", $rowType));
                    if ($showHostname) {
                        print(sprintf("<td class='hostname'>%s(%s)</td>\n", $service["joins"]["host"]["display_name"], $service["attrs"]["host_name"]));
                    } else {
                        print(sprintf("<td class='hostname'>%s</td>\n", $service["joins"]["host"]["display_name"]));
                    }
                    print(sprintf("<td class='service'>%s</td>\n", $service["attrs"]['display_name']));
                    print(sprintf("<td class='state'>%s", $state));
                    if ($service["attrs"]["check_attempt"] < $service["attrs"]["max_check_attempts"]) {
                        print(" (Soft)");
                    }
                    print("</td>\n");
                    print(sprintf("<td class='duration'>%s</td>\n", duration($service["attrs"]['last_state_change'])));
                    print(sprintf("<td class='attempts'>%s/%s</td>\n", $service["attrs"]['check_attempt'], $service["attrs"]['max_check_attempts']));
                    print(sprintf("<td class='output'>%s</td>\n", strip_tags($service["attrs"]["last_check_result"]['output'], '<a>')));
		    print("</tr>\n");
		}
            }
        }
    }
	print("</table>\n");
}

function sectionHeader($type, $counter) {
    print(sprintf('<div id="%s" class="section">', $type));
    print(sprintf('<h2 class="title">%s Status</h2>', ucfirst($type)));
    print('<div class="stats">');
    foreach($counter[$type] as $type => $value) {
        print(sprintf('<div class="stat %s">%s %s</div>', $type, $value, ucfirst($type)));
    }
    print('</div></div>');
}

// Initialize some variables
$counter = array();
$states = array();

$client = new ApiClient($ApiHost);
$client->setCredentials($ApiUser, $ApiPass);

$body = array(
      'joins' => array(
              'host'
      ),
);

$getHeader = array('X-HTTP-Method-Override: GET');

$hosts = json_decode(json_encode($client->request("post", "objects/hosts", $getHeader, null)), true);
$services = json_decode(json_encode($client->request("post", "objects/services", $getHeader, $body)), true);

foreach($hosts as $host) {
	if ((int)$host["attrs"]['downtime_depth'] > 0) {
        	continue;
        } else if ($host["attrs"]['acknowledgement'] == '1' or $host["attrs"]['acknowledgement'] == '2') {
        	$counter['hosts']['acknowledged']++;
                $states['hosts']['acknowledged'][] = $host["attrs"]['display_name'];
        } else if ($host["attrs"]['enable_notifications'] == 0) {
                $counter['hosts']['notification']++;
                $states['hosts']['notification'][] = $host["attrs"]['display_name'];
        } else if ($host["attrs"]['last_check_result'] == null) {
                $counter['hosts']['pending']++;
                $states['hosts']['pending'][] = $host['display_name'];
	} else {
		switch ($host["attrs"]['state']) {
               		case $icinga['host']['ok']:
               			$counter['hosts']['ok']++;
				break;
                	case $icinga['host']['down']:
                    		$counter['hosts']['down']++;
                    		$states['hosts']['down'][] = $host;
				break;
                	case $icinga['host']['unreachable']:
                    		$counter['hosts']['unreachable']++;
                    		$states['hosts']['unreachable'][] = $host;
    				break;
		}
	}
}

foreach($services as $service) {
	if ((int)$service["attrs"]['downtime_depth'] > 0) {
        	continue;
	} else if ($service["attrs"]['acknowledgement'] == '1' or $service["attrs"]['acknowledgement'] == '2') {
		$counter['services']['acknowledged']++;
		$states['services']['acknowledged'][] = $service;
	} else if ($service["attrs"]['enable_notifications'] == '0') {
		$counter['services']['notification']++;
		$states['services']['notification'][] = $service;
	} else if ($service["attrs"]['last_check_result'] == null) {
		$counter['services']['pending']++;
		$states['services']['pending'][] = $service;
	} else {
		switch ($service["attrs"]['state']) {
			case $icinga['service']['ok']:
				$counter['services']['ok']++;
				break;
			case $icinga['service']['warning']:
				$counter['services']['warning']++;
				$states['services']['warning'][] = $service;
				break;
			case $icinga['service']['critical']:
				$counter['services']['critical']++;
				$states['services']['critical'][] = $service;
				break;
			case $icinga['service']['unknown']:
				$counter['services']['unknown']++;
				$states['services']['unknown'][] = $service;
				break;
		}
	}
}

/*
 * Output
 */
echo "<!doctype html>\n";
echo "\n";
echo "<html lang=\"en\">\n";
echo "<head>\n";
  echo "<meta charset=\"utf-8\">\n";
  echo "\n";
  echo "<title>$wallHeading</title>\n";
  echo "<meta name=\"description\" content=\"$wallHeading\">\n";
  echo "\n";
  echo "<link rel=\"stylesheet\" href=\"default.css\">\n";
  echo "\n";
echo "</head>\n";
echo "<body>\n";

sectionHeader('hosts', $counter);

if ($counter['hosts']['down']) {
	echo "<table>";
	echo "<tr><th>Host</th><th>Status</th><th>Duration</th><th>Status Information</th></tr>";
	foreach($states['hosts']['down'] as $host) {
		$state = $icinga["host"][$host["attrs"]["state"]];
		echo "<tr class='".$state."'>\n";
		if ($showHostname) {
			echo "<td class='hostname'>{$host["attrs"]["display_name"]}({$host["attrs"]["__name"]})</td>\n";
		} else {
			echo "<td class='hostname'>{$host["attrs"]["display_name"]}</td>\n";
		}
		echo "<td class='state'>{$state}</td>\n";
		echo "<td class='duration'>".duration($host["attrs"]["last_state_change"])."</td>\n";
        print(sprintf("<td class='output'>%s</td>\n", strip_tags($host["attrs"]["last_check_result"]['output'])));
		echo "</tr>\n";
	}
	echo "</table>";
} else {
	echo "\n";
	echo "<div class='state up'>ALL MONITORED HOSTS UP</div>\n";
}

foreach(array('unreachable', 'acknowledged', 'pending', 'notification') as $type) {
    if ($counter['hosts'][$type]) {
        print(sprintf('<div class="subhosts %s"><b>%s:</b> %s</div>', $type, ucfirst($type), implode(', ', $states['hosts'][$type])));
    }
}

sectionHeader('services', $counter);

if ($counter['services']['warning'] || $counter['services']['critical'] || $counter['services']['unknown']) {
	serviceTable($icinga, $states['services'], array('critical', 'warning', 'unknown'));
} else {
	print("<div class='state up'>ALL MONITORED SERVICES OK</div>\n");
}

foreach(array('acknowledged', 'notification', 'pending') as $type) {
    if ($counter['services'][$type]) {
        print(sprintf('<h3 class="title">%s</h3>', ucfirst($type)));
        print('<div class="subsection">');
        serviceTable($icinga, $states['services'], array($type), $type);
        print('</div>');
    }
}

print("</body>\n");
print("</html>\n");
