# php-landing

### Requirements

Windows, UNIX, or Linux web server, PHP 5.

UNIX/Linux require the mpstat command to be available for CPU usage to be displayed. To install this on Ubuntu, run `sudo apt-get install sysstat`.

Windows systems do not currently display system uptime.

### Installation

Copy index.php and jqknob.js to a web accessible directory on your server.

If you want to customize the text and appearance, copy the variables from the top of the index.php file to a new file called config.php, and edit them there. This will allow you to update the index.php file in the future without losing your changes.
