PHP CGI Wrapper
===============

About
-----

PHP CGI Wrapper is a PHP script made to run CGI applications on hosts which
don't support CGI. Something similar has been created previously (see
http://www.fun2code.de/articles/wrapping_perl_with_php/wrapping_perl_with_php.html),
but that lacked support for file uploads thanks to PHP's **brilliant** idea of
disabling access to raw POST data when using multipart/form-data encoding. This
wrapper gets around that problem by reconstructing the POST data and feeding it
to the application's STDIN.

Usage
-----

Create a PHP file for every CGI application you want to wrap using PHP. It
should look like this:

	<?php
	require 'cgi_wrapper.php';
	execute_cgi('/path/to/your/cgi/application.pl');

The argument of `execute_cgi()` can be either a relative path
(`./some-script.pl`), an absolute path (like in the example above) or a shell
command (`python cgi-script.py`). In the first two cases, the script will most
likely have to be executable by the web server.

See https://github.com/frankusrs/PHP-CGI-Wrapper/wiki for other deployment
options, security considerations and so on.

Licence
-------

This script is licenced under the Do What The Fuck You Want To Public License
for maximum freedom. See the terms and conditions here:

http://sam.zoy.org/wtfpl/COPYING
