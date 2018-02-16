# php-landing

Nice landing page with statistic information about your server.

![Screenshot 1](https://raw.githubusercontent.com/lgg-awesome/php-landing/master/screen1.png)
![Screenshot 2](https://raw.githubusercontent.com/lgg-awesome/php-landing/master/screen2.png)

### Requirements

Windows, UNIX, or Linux web server, and PHP 5+.

Linux systems show the most accurate CPU information if the mpstat command is available. To install this on Ubuntu, run `sudo apt-get install sysstat`.

Windows systems do not currently display system uptime.

### Installation

Copy index.php to a web accessible directory on your server.

If you want to customize the text and appearance, copy the variables from the top of the index.php file to a new file called config.php, and edit them there. This will allow you to update the index.php file in the future without losing your changes.
