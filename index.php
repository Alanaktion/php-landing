<?php

// To change these values, create a file called config.php and copy/paste them there.
$server_name = "Server";
$server_desc = "";
$color_bg = "#222";
$color_name = "#fff";
$color_text = "#ccc";
$custom_css = "";

if(is_file("config.php")) {
	include "config.php";
}

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
<title><?php echo $server_name; ?></title>
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
	background: <?php echo $color_bg; ?>
}
h1, p {
	padding-left: 15%;
}
h1 {
	color: <?php echo $color_name; ?>;
	font-weight: 100;
	font-size: 4em;
	margin: 0;
}
p {
	color: <?php echo $color_text; ?>;
	font-size: 2em;
	margin: 0;
}
footer {
	font-family: "Segoe UI",'Helvetica Neue',"Open Sans","Tahoma","Verdana","Arial",sans-serif;
	position: absolute;
	position: fixed;
	line-height: 40px;
	bottom: 2em;
	left: 15%;
	color: <?php echo $color_text; ?>;
}

/* Yes, this is a hack. */
footer canvas {
	vertical-align: middle;
}
footer canvas + input {
	margin-top: 16px !important;
	font-size: 12px !important;
}

/* Begin: Custom CSS */
<?php echo $custom_css; ?>
/* End: Custom CSS */
</style>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script src="jquery.knob.min.js"></script>
<script>
function update() {
	$.post('<?php echo basename(__FILE__); ?>?json=1', function(data) {

		$('#k-cpu').val(data.cpu).trigger("change");
		$('#k-memory').val(data.memory).trigger("change");

		window.setTimeout(update, 1000);

	},'json');
}
$(document).ready(function() {
	update();
	$("#k-disk, #k-memory, #k-cpu").knob({
		readOnly: true,
		width: 40,
		height: 40,
		thickness: 0.2,
		fontWeight: 'normal',
		bgColor: 'rgba(127,127,127,0.08)',
		fgColor: '<?php echo $color_text; ?>'
	});
});
</script>
</head>
<body>
<h1><?php echo $server_name; ?></h1>
<p><?php echo $server_desc; ?></p>
<footer>
	<?php if(!$windows) { ?>
		Uptime: <?php echo $uptime; ?>&emsp;
	<?php } ?>
	Disk usage: <input id="k-disk" value="<?php echo $disk; ?>">&emsp;
	Memory: <input id="k-memory" value="<?php echo $memory; ?>">&emsp;
	CPU: <input id="k-cpu" value="0">
</footer>
</body>
</html>
