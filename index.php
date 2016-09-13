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
$windows = defined("PHP_WINDOWS_VERSION_MAJOR");

// Get system status
if($windows) {

	// Uptime parsing was a mess...
	$uptime = "Error";

	// Assuming C: as the system drive
	$df = shell_exec("fsutil volume diskfree c:");
	$disk_stats = explode(" ", trim(preg_replace("/\s+/", " ", preg_replace("/[^0-9 ]+/", "", $df))));
	$disk = round($disk_stats[0] / $disk_stats[1] * 100);

	$disk_total = "";
	$disk_used = "";

	// Memory checking is slow on Windows, will only set over AJAX to allow page to load faster
	$memory = 0;
	$mem_total = 0;
	$mem_used = 0;

	$swap = 0;
	$swap_total = 0;
	$swap_used = 0;

} else {

	$initial_uptime = shell_exec("/usr/bin/cut -d. -f1 /proc/uptime");
	$days = floor($initial_uptime / 60 / 60 / 24);
	$hours = $initial_uptime / 60 / 60 % 24;
	$mins = $initial_uptime / 60 % 60;
	$secs = $initial_uptime % 60;

	if($days > "0") {
		$uptime = $days . "d " . $hours . "h";
	} elseif ($days == "0" && $hours > "0") {
		$uptime = $hours . "h " . $mins . "m";
	} elseif ($hours == "0" && $mins > "0") {
		$uptime = $mins . "m " . $secs . "s";
	} elseif ($mins < "0") {
		$uptime = $secs . "s";
	} else {
		$uptime = "Error retreving uptime.";
	}

	// Get path for grep and df
    $path_grep = str_replace("\n", "", shell_exec("which grep"));
    $path_df = str_replace("\n", "", shell_exec("which df"));

	// Check disk stats
	$disk_result = shell_exec($path_df." -P | ".$path_grep." /$");
	$disk_result = explode(" ", preg_replace("/\s+/", " ", $disk_result));

	$disk_total = intval($disk_result[1]);
	$disk_used = intval($disk_result[2]);
	$disk = intval(rtrim($disk_result[4], "%"));

	// Get current RAM and Swap stats
	$meminfoStr = shell_exec('/usr/bin/awk \'$3=="kB"{$2=$2/1024;$3=""} 1\' /proc/meminfo');
	$mem = array();
	foreach(explode("\n", trim($meminfoStr)) as $m) {
		$m = explode(": ", $m, 2);
		$mem[$m[0]] = trim($m[1]);
	}

	// Calculate current RAM usage
	$mem_total = round($mem['MemTotal']);
	$mem_used = $mem_total - round($mem['MemFree']) - round($mem['Cached']);
	$memory = round($mem_used / $mem_total * 100);

	// Calculate current swap usage
	$swap_total = round($mem['SwapTotal']);
	$swap_used = $swap_total - round($mem['SwapFree']);
	$swap = round($swap_used / $swap_total * 100);
}

if(!empty($_GET["json"])) {

	// Determine number of CPUs
	$num_cpus = 1;
	if (is_file("/proc/cpuinfo")) {
		$cpuinfo = file_get_contents("/proc/cpuinfo");
		preg_match_all("/^processor/m", $cpuinfo, $matches);
		$num_cpus = count($matches[0]);
	} else if ("WIN" == strtoupper(substr(PHP_OS, 0, 3))) {
		$process = @popen("wmic cpu get NumberOfCores", "rb");
		if (false !== $process) {
			fgets($process);
			$num_cpus = intval(fgets($process));
			pclose($process);
		}
	} else {
		$process = @popen("sysctl -a", "rb");
		if (false !== $process) {
			$output = stream_get_contents($process);
			preg_match("/hw.ncpu: (\d+)/", $output, $matches);
			if ($matches) {
				$num_cpus = intval($matches[1][0]);
			}
			pclose($process);
		}
	}

	if($windows) {

		// Get stats for Windows
		$cpu = intval(trim(preg_replace("/[^0-9]+/","",shell_exec("wmic cpu get loadpercentage"))));
		$memory_stats = explode(' ',trim(preg_replace("/\s+/"," ",preg_replace("/[^0-9 ]+/","",shell_exec("systeminfo | findstr Memory")))));
		$memory = round($memory_stats[4] / $memory_stats[0] * 100);

	} else {
	    // Get path for sed
        $path_sed = str_replace("\n", "", shell_exec("which sed"));

		// Get stats for linux using simplest possible methods
		if(is_file("/usr/bin/mpstat")) {
			$cpu = 100 - round(shell_exec("/usr/bin/mpstat 1 2 | /usr/bin/tail -n 1 | ".$path_sed." 's/.*\([0-9\.+]\{5\}\)$/\\1/'"));
		} elseif(function_exists("sys_getloadavg")) {
			$load = sys_getloadavg();
			$cpu = $load[0] * 100 / $num_cpus;
		} elseif(is_file("/proc/loadavg")) {
			$cpu = 0;
			$output = file_get_contents("/proc/loadavg");
			$cpu = substr($output,0,strpos($output," "));
		} elseif(is_file("/usr/bin/uptime")) {
			$str = substr(strrchr(shell_exec("/usr/bin/uptime"),":"),1);
			$avs = array_map("trim",explode(",",$str));
			$cpu = $avs[0] * 100 / $num_cpus;
		} else {
			$cpu = 0;
		}

	}

	header("Content-type: application/json");
	exit(json_encode(array(
		"uptime" => $uptime,
		"disk" => $disk,
		"disk_total" => $disk_total,
		"disk_used" => $disk_used,
		"cpu" => $cpu,
		"num_cpus" => $num_cpus,
		"memory" => $memory,
		"memory_total" => $mem_total,
		"memory_used" => $mem_used,
		"swap" => $swap,
		"swap_total" => $swap_total,
		"swap_used" => $swap_used,
	)));
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $server_name; ?></title>
<style type="text/css">
html,body {
	height: 100%;
	margin: 0;
	padding: 0;
}
body {
	font-family: "Segoe UI Light",'HelveticaNeue-UltraLight','Helvetica Neue UltraLight','Helvetica Neue',"Open Sans","Segoe UI","Tahoma","Verdana","Arial",sans-serif;
	font-weight: 300;
	background: <?php echo $color_bg; ?>;
	overflow: hidden;
}
section {
	position: absolute;
	top: 50%;
	width: 100%;
	margin-top: -4em;
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
a, a:link, a:visited {
	color: <?php echo $color_name; ?>;
	text-decoration: none;
	cursor: pointer;
}
a:hover, a:focus, a:active {
	color: <?php echo $color_name; ?>;
	text-decoration: underline;
}
.left {
	text-align: left;
	float: left;
}
.right {
	text-align: right;
	float: right;
}
footer {
	font-family: "Segoe UI",'Helvetica Neue',"Open Sans","Tahoma","Verdana","Arial",sans-serif;
	position: absolute;
	position: fixed;
	line-height: 40px;
	bottom: 2em;
	left: 15%;
	width: 70%;
	color: <?php echo $color_text; ?>;
}
.overlay {
	z-index: 1;
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: black;
	opacity: 0.3;
}
dialog {
	z-index: 2;
	position: absolute;
	bottom: 0;
	left: 0;
	width: 100%;
	min-height: 100px;

	border: none;
	padding: 1em 15%;

	background-color: <?php echo $color_text; ?>;
	color: <?php echo $color_bg; ?>;

	-moz-box-sizing: border-box;
	box-sizing: border-box;

	-webkit-transform: translateY(130px);
	transform: translateY(130px);
	-webkit-transition: -webkit-transform .2s cubic-bezier(.15,.75,.55,1);
	transition: transform .2s cubic-bezier(.15,.75,.55,1);
}
dialog[open],
dialog.open {
	-webkit-transform: translateY(0);
	transform: translateY(0);
}
dialog h2 {
	color: <?php echo $color_bg; ?>;
	font-weight: 100;
	font-size: 2em;
	margin: 0;
	line-height: 1.3;
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
<script src="jqknob.js"></script>
<script>
function update() {
	$.post('<?php echo basename(__FILE__); ?>?json=1', function(data) {

		// Update footer
		$('#uptime').text(data.uptime);
		$('#k-cpu').val(data.cpu).trigger("change");
		$('#k-memory').val(data.memory).trigger("change");
		if(data.swap_total) {
			$('#k-swap').val(data.swap).trigger("change");
		}

		// Update details
		$('#dt-disk-used').text(Math.round(data.disk_used / 10485.76) / 100);
		$('#dt-mem-used').text(data.memory_used);
		$('#dt-num-cpus').text(data.num_cpus);
		if(data.swap_total) {
			$('#dt-swap_used').text(data.swap_used);
		}

		window.setTimeout(update, 3000);

	},'json');
}
$(document).ready(function() {
	// Show ring charts
	$("#k-disk, #k-memory, #k-swap, #k-cpu").knob({
		readOnly: true,
		width: 40,
		height: 40,
		thickness: 0.2,
		fontWeight: 'normal',
		bgColor: 'rgba(127,127,127,0.15)', // 50% grey with a low opacity, should work with most backgrounds
		fgColor: '<?php echo $color_text; ?>'
	});
	// Start AJAX update loop
	update();
	//
	$('#detail').click(function(e) {
		$('dialog').prop('open', true).addClass('open');
		$('<div />').addClass('overlay').appendTo('body');
	});
	$('body').on('click', '.overlay', function(e) {
		$('dialog').prop('open', false).removeClass('open');
		$('.overlay').remove();
	});
});
</script>
</head>
<body>
<section>
	<h1><?php echo $server_name; ?></h1>
	<p><?php echo $server_desc; ?></p>
</section>
<footer>
	<?php if(!$windows && !empty($uptime)) { ?>
		Uptime: <span id="uptime"><?php echo $uptime; ?></span>&emsp;
	<?php } ?>
	Disk usage: <input id="k-disk" value="<?php echo $disk; ?>">&emsp;
	Memory: <input id="k-memory" value="<?php echo $memory; ?>">&emsp;
	<?php if($swap_total !== "0") { ?>
		Swap: <input id="k-swap" value="<?php echo $swap; ?>">&emsp;
	<?php } ?>
	CPU: <input id="k-cpu" value="0">
	<a class="right" id="detail">Detail</a>
</footer>
<dialog>
	<div class="left">
		<h2><?php echo $windows ? $_SERVER["SERVER_NAME"] : shell_exec("hostname -f"); ?></h2>
		<?php
			if(!$windows) {
				$version_cmd = shell_exec("/bin/cat /etc/issue");
				$version = "";
				if($version_cmd) {
					$version_arr = explode("\\", $version_cmd);
					$version = $version_arr[0];
				} else {
					$version_cmd = shell_exec("/usr/bin/lsb_release -d");
					if(strpos($version_cmd, "Description") === 0) {
						$version = preg_replace("/^Description:\\s/", "", $version_cmd);
					}
				}
				echo $version ? $version . "<br>" : "";
			}
		?>
		<?php echo $_SERVER["SERVER_ADDR"]; ?>
	</div>
	<div class="right">
		<b>Disk:</b> <span id="dt-disk-used"><?php echo round($disk_used / 1048576, 2); ?></span> GB / <?php echo round($disk_total / 1048576, 2); ?> GB<br>
		<b>Memory:</b> <span id="dt-mem-used"><?php echo $mem_used; ?></span> MB / <?php echo $mem_total; ?> MB<br>
		<?php if($swap_total !== "0") { ?>
			<b>Swap:</b> <span id="dt-swap-used"><?php echo $swap_used ?></span> MB / <?php echo $swap_total ?> MB<br>
		<?php } else { ?>
			<b>Swap:</b> N/A<br>
		<?php }?>
		<b>CPU Cores:</b> <span id="dt-num-cpus"></span>
	</div>
</dialog>
</body>
</html>
