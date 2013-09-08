<?php

$server_name = "";
$server_desc = "";

// Detect Windows systems
$windows = defined('PHP_WINDOWS_VERSION_MAJOR');

// Get system status
if($windows) {
	// Uptime parsing was a mess...
	$uptime = 'Error';
	// Assuming C: as the system drive
	$disk_stats = explode(' ',trim(preg_replace('/\s+/',' ',preg_replace('/[^0-9 ]+/','',`fsutil volume diskfree c:`))));
	$disk = round($disk_stats[0] / $disk_stats[1] * 100);
	// Memory checking is slow on Windows, will only set over AJAX to allow page to load faster
	$memory = 0;
} else {
	$uptime = trim(str_replace(" ","",`uptime | grep -o '[0-9]\+ [ydhms]'`));
	$disk = trim(intval(trim(`df -k | grep /dev/sda | awk ' { print $5 } '`, "%\n")),'%');
	$memory = 100 - round(`free | awk '/buffers\/cache/{print $4/($3+$4) * 100.0;}'`);
}

// Round to nearest multiple of 5 for pie charts
$disk_pct = round($disk/5)*5;
$memory_pct = round($memory/5)*5;

if(!empty($_GET['json'])) {
	// CPU requires systat to be installed on unix/linux systems
	if($windows) {
		$cpu = intval(trim(preg_replace('/[^0-9]+/','',`wmic cpu get loadpercentage`)));
		$memory_stats = explode(' ',trim(preg_replace('/\s+/',' ',preg_replace('/[^0-9 ]+/','',`systeminfo | findstr Memory`))));
		$memory = round($memory_stats[4] / $memory_stats[0] * 100);
	} else {
		if(`which mpstat`) {
			$cpu = 100 - round(`mpstat 1 2 | tail -n 1 | sed 's/.*\([0-9\.+]\{5\}\)$/\\1/'`);
		} else {
			$cpu = 0;
		}
	}
	exit(json_encode(array(
		'uptime' => $uptime,
		'disk' => $disk,
		'cpu' => $cpu,
		'memory' => $memory,
	)));
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?=$server_name?></title>
<link rel="stylesheet" href="res/fonts/piechart.css">
<style type="text/css">
html {
	height: 100%;
}
html, body {
	margin: 0;
	padding: 0;
}
body {
	position: absolute;
	top: 50%;
	width: 100%;
	margin-top: -4em;
	font-family: "Segoe UI Light",'HelveticaNeue-UltraLight','Helvetica Neue UltraLight','Helvetica Neue',"Open Sans","Segoe UI","Tahoma","Verdana","Arial",sans-serif;
	font-weight: 300;
	background: #222;
}
h1, p {
	padding-left: 15%;
}
h1 {
	color: #ccc;
	color: #fff;
	font-weight: 100;
	font-size: 4em;
	margin: 0;
}
p {
	color: #999;
	color: #ccc;
	font-size: 2em;
	margin: 0;
}
footer {
	position: absolute;
	position: fixed;
	bottom: 2em;
	left: 15%;
	color: #ccc;
}
.pie {
	vertical-align: middle;
	padding-bottom: 5px;
}
</style>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script type="text/javascript">
function update() {
	$.post('?json=1',function(data){
		// Update CPU text/pie
		$('#cpu').text(data.cpu + '%');
		$('#cpu-pie').attr('class','pie').addClass('pie-' + (Math.round(data.cpu/5)*5));
		// Update memory text/pie
		$('#memory').text(data.memory + '%');
		$('#memory-pie').attr('class','pie').addClass('pie-' + (Math.round(data.memory/5)*5));
		// Check again in 8 seconds
		window.setTimeout('update()',1000);
	},'json');
}
$(document).ready(function(){
	update();
});
</script>
</head>
<body>
<h1><?=$server_name?></h1>
<p><?=$server_desc?></p>
<footer>
<?php if(!$windows): ?>
Uptime: <?=$uptime?>&emsp;
<?php endif; ?>
Disk usage: <?=$disk?>% <span class="pie pie-<?=$disk_pct?>"></span>&emsp;
Memory: <span id="memory"><?=$memory?>%</span> <span id="memory-pie" class="pie pie-<?=$memory_pct?>"></span>&emsp;
CPU: <span id="cpu">&hellip;</span> <span id="cpu-pie" class="pie"></span>
</footer>
</body>
</html>
