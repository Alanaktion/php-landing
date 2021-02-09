# php-landing

A nice landing page with statistics about your server

![Screenshot 1](https://cdn.rawgit.com/Alanaktion/php-landing/master/screen1.png)
![Screenshot 2](https://cdn.rawgit.com/Alanaktion/php-landing/master/screen2.png)

## Requirements

Linux, macOS, or Windows web server, and PHP 7+.

Linux systems show the most accurate CPU information if the mpstat command is available. On Ubuntu, run:

```bash
sudo apt-get install sysstat
```

Not all systems currently display all metrics.

## Installation

Copy `index.php` to a web accessible directory on your server.

If you want to customize the text and appearance, copy the variables from the top of the `index.php` file to a new file called `config.php`, and edit them there. This will allow you to update the `index.php` file in the future without losing your changes.

## Demo

You can see this page in-use on several of my web servers, including my current [primary testing server, Ava](https://ava.alanaktion.net/).
