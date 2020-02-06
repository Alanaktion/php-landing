<?php

// To change these values, create a file called config.php and copy/paste them there.
$server_name = "Server";
$server_desc = "";
$color_bg = "#222";
$color_name = "#fff";
$color_text = "#ccc";
$custom_css = "";

if (is_file("config.php")) {
	include "config.php";
}

// Detect Windows systems
$windows = defined("PHP_WINDOWS_VERSION_MAJOR");

// Detect Mac systems
$mac = PHP_OS == "Darwin";

// Get system status
if ($windows) {

	// Uptime parsing was a mess...
	$uptime = "Error";

	// Assuming C: as the system drive
	$df = shell_exec("fsutil volume diskfree c:");
	$disk_stats = explode(" ", trim(preg_replace("/\s+/", " ", preg_replace("/[^0-9 ]+/", "", $df))));
	$disk = round($disk_stats[0] / $disk_stats[1] * 100);

	$disk_total = 0;
	$disk_used = 0;

	// Memory checking is slow on Windows, will only set over AJAX to allow page to load faster
	$memory = 0;
	$mem_total = 0;
	$mem_used = 0;

	$swap = null;
	$swap_total = null;
	$swap_used = null;

} else {

	if ($mac) {
		$initial_uptime = time() - rtrim(shell_exec("/usr/sbin/sysctl -n kern.boottime | awk '{print $4}'"), ",\n");
	} else {
		$initial_uptime = shell_exec("cut -d. -f1 /proc/uptime");
	}
	$days = floor($initial_uptime / 60 / 60 / 24);
	$hours = $initial_uptime / 60 / 60 % 24;
	$mins = $initial_uptime / 60 % 60;
	$secs = $initial_uptime % 60;

	if ($days > 0) {
		$uptime = $days . "d " . $hours . "h";
	} elseif ($days == 0 && $hours > 0) {
		$uptime = $hours . "h " . $mins . "m";
	} elseif ($hours == 0 && $mins > 0) {
		$uptime = $mins . "m " . $secs . "s";
	} elseif ($mins < 0) {
		$uptime = $secs . "s";
	} else {
		$uptime = "Error retreving uptime.";
	}

	// Check disk stats
	if ($mac) {
		$disk_result = shell_exec("df -P | grep /System/Volumes/Data$");
	} else {
		$disk_result = shell_exec("df -P | grep /$");
	}
	$disk_result = explode(" ", preg_replace("/\s+/", " ", $disk_result));

	$disk_total = intval($disk_result[1]);
	$disk_used = intval($disk_result[2]);
	$disk = intval(rtrim($disk_result[4], "%"));

	// Get current RAM and Swap stats
	if ($mac) {
		// TODO: get macOS memory usage in a reliable way
		$memory = 0;
		$mem_total = 0;
		$mem_used = 0;

		$swap = null;
		$swap_total = null;
		$swap_used = null;
	} else {
		$meminfoStr = shell_exec('awk \'$3=="kB"{$2=$2/1024;$3=""} 1\' /proc/meminfo');
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
}

if (!empty($_GET["json"])) {
	// Determine number of CPUs
	$num_cpus = 1;
	if ($windows) {
		$process = @popen("wmic cpu get NumberOfCores", "rb");
		if (false !== $process) {
			fgets($process);
			$num_cpus = intval(fgets($process));
			pclose($process);
		}
	} elseif (is_file("/proc/cpuinfo")) {
		$cpuinfo = file_get_contents("/proc/cpuinfo");
		preg_match_all("/^processor/m", $cpuinfo, $matches);
		$num_cpus = count($matches[0]);
	} elseif ($mac) {
		$num_cpus = intval(trim(shell_exec("/usr/sbin/sysctl -n hw.ncpu"))) ?: 1;
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

	if ($windows) {
		// Get stats for Windows
		$cpu = intval(trim(preg_replace("/[^0-9]+/","",shell_exec("wmic cpu get loadpercentage"))));
		$memory_stats = explode(' ',trim(preg_replace("/\s+/"," ",preg_replace("/[^0-9 ]+/","",shell_exec("systeminfo | findstr Memory")))));
		$memory = round($memory_stats[4] / $memory_stats[0] * 100);
	} else {
		// Get stats for linux using simplest/most accurate possible methods
		if (is_file("mpstat")) {
			$cpu = 100 - round(shell_exec("mpstat 1 2 | tail -n 1 | sed 's/.*\([0-9\.+]\{5\}\)$/\\1/'"));
		} elseif (function_exists("sys_getloadavg")) {
			$load = sys_getloadavg();
			$cpu = $load[0] * 100 / $num_cpus;
		} elseif (is_file("/proc/loadavg")) {
			$cpu = 0;
			$output = file_get_contents("/proc/loadavg");
			$cpu = substr($output,0,strpos($output," "));
		} elseif (is_file("uptime")) {
			$str = substr(strrchr(shell_exec("uptime"),":"),1);
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

$ringBase = 339.292;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $server_name; ?></title>
<style type="text/css">
body {
	height: 100vh;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	background: <?php echo $color_bg; ?>;
	overflow: hidden;
}
.main, .footer {
	padding-left: 15%;
	padding-right: 15%;
}

.main {
	flex: 1;
	display: flex;
	flex-direction: column;
	justify-content: center;
	padding-top: 6.5rem;
}
.main h1 {
	font-size: 4rem;
	font-weight: 300;
	margin: 0;
	color: <?php echo $color_name; ?>;
}
.main p {
	font-size: 2rem;
	font-weight: 300;
	margin: 0;
	color: <?php echo $color_text; ?>;
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

.footer {
	display: flex;
	padding-top: 2rem;
	padding-bottom: 2rem;
	line-height: 2.5rem;
	color: <?php echo $color_text; ?>;
}
.footer > div {
	margin-right: 1rem;
}
.footer-end {
	margin-left: auto;
	margin-right: 0;
}

.ring {
	transform: rotate(-90deg);
	fill: none;
	stroke-width: 12;
	height: 2.5rem;
	width: 2.5rem;
	vertical-align: middle;
}
.ring-background {
	stroke: rgba(127,127,127,0.15);
}
.ring-value {
	stroke: <?php echo $color_text; ?>;
	stroke-dasharray: <?php echo $ringBase; ?>;
}
.ring-label {
	transform: rotate(90deg);
	transform-origin: 50% 50%;
	fill: <?php echo $color_text; ?>;
	text-anchor: middle;
	alignment-baseline: central;
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
.details {
	z-index: 2;
	position: absolute;
	box-sizing: border-box;
	padding: 1em 15%;
	bottom: 0;
	left: 0;
	width: 100%;
	min-height: 6rem;

	display: flex;
	justify-content: space-between;

	background-color: <?php echo $color_text; ?>;
	color: <?php echo $color_bg; ?>;

	transform: translateY(100%);
	transition: transform .2s cubic-bezier(.15,.75,.55,1);
}
.details.open {
	transform: translateY(0);
}
.details h2 {
	color: <?php echo $color_bg; ?>;
	font-weight: 100;
	font-size: 2em;
	margin: 0;
	line-height: 1.3;
}

/* Begin: Custom CSS */
<?php echo $custom_css; ?>
/* End: Custom CSS */
</style>
</head>
<body>
	<main class="main">
		<h1><?php echo $server_name; ?></h1>
		<p><?php echo $server_desc; ?></p>
	</main>
	<footer class="footer">
		<?php if (!$windows && !empty($uptime)) { ?>
			<div>Uptime: <span id="uptime"><?php echo $uptime; ?></span></div>
		<?php } ?>
		<div>
			Disk usage:
			<svg id="k-disk" class="ring" viewBox="0 0 120 120">
				<circle class="ring-background" cx="60" cy="60" r="54" />
				<circle class="ring-value" cx="60" cy="60" r="54" stroke-dashoffset="<?php echo $ringBase * (1 - ($disk/100)); ?>" />
				<text class="ring-label" x="60" y="72" font-size="40"><?php echo $disk ?></text>
			</svg>
		</div>
		<div>
			Memory:
			<svg id="k-memory" class="ring" viewBox="0 0 120 120">
				<circle class="ring-background" cx="60" cy="60" r="54" />
				<circle class="ring-value" cx="60" cy="60" r="54" stroke-dashoffset="<?php echo $ringBase * (1 - ($memory / 100)); ?>" />
				<text class="ring-label" x="60" y="72" font-size="40"><?php echo $memory ?: null ?></text>
			</svg>
		</div>
		<?php if ($swap_total !== null) { ?>
			<div>
				Swap:
				<svg id="k-swap" class="ring" viewBox="0 0 120 120">
					<circle class="ring-background" cx="60" cy="60" r="54" />
					<circle class="ring-value" cx="60" cy="60" r="54" stroke-dashoffset="<?php echo $ringBase * (1 - ($swap / 100)); ?>" />
					<text class="ring-label" x="60" y="72" font-size="40"><?php echo $swap ?></text>
				</svg>
			</div>
		<?php } ?>
		<div>
			CPU:
			<svg id="k-cpu" class="ring" viewBox="0 0 120 120">
				<circle class="ring-background" cx="60" cy="60" r="54" />
				<circle class="ring-value" cx="60" cy="60" r="54" stroke-dashoffset="<?php echo $ringBase; ?>" />
				<text class="ring-label" x="60" y="72" font-size="40" />
			</svg>
		</div>
		<div class="footer-end">
			<a href="#" id="detail">Detail</a>
		</div>
	</footer>
	<div class="details" aria-hidden="true">
		<div>
			<h2><?php echo $windows ? $_SERVER["SERVER_NAME"] : shell_exec("hostname -f"); ?></h2>
			<?php
				if (!$windows) {
					$version = null;
					if (is_file("/etc/issue")) {
						$version_arr = explode("\\", file_get_contents("/etc/issue"));
						$version = $version_arr[0];
					} else {
						$version_cmd = shell_exec("lsb_release -d");
						if (strpos($version_cmd, "Description") === 0) {
							$version = preg_replace("/^Description:\\s/", "", $version_cmd);
						}
					}
					echo $version ? $version . "<br>" : "";
				}
			?>
			<?php echo $_SERVER["SERVER_ADDR"]; ?>
		</div>
		<div>
			<b>Disk:</b> <span id="dt-disk-used"><?php echo round($disk_used / 1048576, 2); ?></span> GB / <?php echo round($disk_total / 1048576, 2); ?> GB<br>
			<b>Memory:</b> <span id="dt-mem-used"><?php echo $mem_used; ?></span> MB / <?php echo $mem_total; ?> MB<br>
			<?php if ($swap_total !== null) { ?>
				<b>Swap:</b> <span id="dt-swap-used"><?php echo $swap_used ?></span> MB / <?php echo $swap_total ?> MB<br>
			<?php } else { ?>
				<b>Swap:</b> N/A<br>
			<?php }?>
			<b>CPU Cores:</b> <span id="dt-num-cpus"></span>
		</div>
	</div>
	<script>
	var ringBase = parseFloat('<?php echo $ringBase; ?>');

	function update() {
		var xhr = new XMLHttpRequest();
		xhr.addEventListener('load', function() {
			data = JSON.parse(xhr.responseText);

			// Update footer
			if (document.getElementById('uptime')) {
				document.getElementById('uptime').textContent = data.uptime;
			}
			document.querySelector('#k-cpu .ring-value').setAttribute('stroke-dashoffset', ringBase * (1 - (data.cpu / 100)));
			document.querySelector('#k-cpu .ring-label').textContent = Math.round(data.cpu);
			document.querySelector('#k-memory .ring-value').setAttribute('stroke-dashoffset', ringBase * (1 - (data.memory / 100)));
			document.querySelector('#k-memory .ring-label').textContent = Math.round(data.memory);
			if (data.swap_total) {
				document.querySelector('#k-swap .ring-value').setAttribute('stroke-dashoffset', ringBase * (1 - (data.swap / 100)));
				document.querySelector('#k-swap .ring-label').textContent = Math.round(data.swap);
			}

			// Update details
			document.getElementById('dt-disk-used').textContent = Math.round(data.disk_used / 10485.76) / 100;
			document.getElementById('dt-mem-used').textContent = data.memory_used;
			document.getElementById('dt-num-cpus').textContent = data.num_cpus;
			if (data.swap_total && document.getElementById('dt-swap-used')) {
				document.getElementById('dt-swap-used').textContent = data.swap_used;
			}

			window.setTimeout(update, 3000);
		});
		xhr.open('POST', '<?php echo basename(__FILE__); ?>?json=1');
		xhr.send();
	}

	// Start AJAX update loop
	update();

	// Bind events
	document.getElementById('detail').addEventListener('click', function(e) {
		e.preventDefault();

		let details = document.getElementsByClassName('details')[0];
		details.classList.add('open');

		let overlay = document.createElement('div');
		overlay.className = 'overlay';
		document.body.appendChild(overlay);
	});
	document.body.addEventListener('click', function(e) {
		if (e.target.className == 'overlay') {
			let details = document.getElementsByClassName('details')[0];
			details.classList.remove('open');
			e.target.remove();
		}
	});
	</script>
</body>
</html>
