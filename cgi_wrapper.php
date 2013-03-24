<?php

/*
 * PHP CGI Wrapper
 *     by Frank Usrs
 *
 * This script is a wrapper for running CGI scripts through PHP. For
 * help and support, consult the included README file and/or visit
 * the GitHub project page:
 *
 * https://github.com/frankusrs/PHP-CGI-Wrapper
 *
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 */

// Defined here because we can't concatenate class constants.
define('MULTIPART_FORMAT', "%s\r\nContent-Disposition: form-data; name=\"%s\"");
define('MULTIPART_TEXT_FORMAT', MULTIPART_FORMAT."\r\n\r\n%s\r\n");
define('MULTIPART_FILE_FORMAT',
	MULTIPART_FORMAT."; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n");

class PHP_CGI_Wrapper {
	const BUFSIZ = 1024;

	protected $script, $ph, $pipes;
	public function __construct($script) {
		$this->script		= $script;

		$this->cookie		= &$_COOKIE;
		$this->env		= &$_ENV;
		$this->files		= &$_FILES;
		$this->get		= &$_GET;
		$this->post		= &$_POST;
		$this->server		= &$_SERVER;

		$this->content_type	= $_SERVER['CONTENT_TYPE'];
		$this->request_method	= $_SERVER['REQUEST_METHOD'];
	}

	public function run() {
		$this->fix_env();
		$this->set_cookies();

		$this->ph = proc_open($this->script, array(
			array('pipe', 'rb'), // STDIN
			array('pipe', 'wb'), // STDOUT
			// STDERR - TODO
		), $this->pipes);

		$this->handle_post();

		$this->do_headers();
		$this->do_body();

		$this->close();
	}

	protected function fix_env() {
		// Set the proper environment variables if they aren't present.
		if (!isset($this->env['REQUEST_METHOD'])) {
			foreach ($this->server as $var => $value) {
				putenv($var.'='.$value); // not sure if safe
			}
		}
	}

	protected function set_cookies() {
		if (isset($this->env['HTTP_COOKIE']) || !count($this->cookie))
			return; // cookies already set or none available

		// Set cookies
		$cookies = array();
		foreach ($this->cookie as $name => $value)
			$cookies[] = urlencode($name).'='.urlencode($value);

		putenv('HTTP_COOKIE='.implode('; ', $cookies));
	}

	protected $boundary;
	const MULTIPART_RE = '!^multipart/form-data; boundary=([^\s]+)!';

	protected function is_multipart() {
		if (preg_match(self::MULTIPART_RE, $this->content_type, $m)) {
			$this->boundary = '--'.$m[1];
			return true;
		}

		return false;
	}

	protected function handle_post() {
		if ($this->request_method === 'POST') {
			if ($this->is_multipart()) {
				$this->handle_multipart();
			} else {
				$this->handle_urlencoded();
			}
		}

		// we need to close STDIN regardless of whether we use it or not
		fclose($this->pipes[0]);
	}

	protected function handle_urlencoded() {
		/* send the unmodified POST data to STDIN */
		$fh = fopen('php://input', 'rb');
		while (($buf = fgets($fh, self::BUFSIZ)) !== false) {
			fwrite($this->pipes[0], $buf);
		}
		fclose($fh);
	}

	/* Because PHP is a steaming pile of shit, you can't access the raw post
	 * data when using multipart/form-data encoding. Thus, we waste our
	 * PRECIOUS CPU CYCLES reconstructing the POST data manually. */
	protected function handle_multipart() {
		// do regular POST values
		foreach ($this->post as $name => $value) {
			$name = $this->escapequotes($name);
			fwrite($this->pipes[0], sprintf(MULTIPART_TEXT_FORMAT,
				$this->boundary, $name, $value));
		}

		// handle files uploads
		$this->handle_files();

		// end of POST data
		fwrite($this->pipes[0], $this->boundary."--\r\n");
	}

	const DEFAULT_TYPE = 'application/octet-stream';
	protected function handle_files() {
		foreach ($this->files as $name => $file) {
			// should we use is_uploaded_file() here? only
			// register_globals could warrant that, and it's gone as
			// of php 5.4 anyway

			$error = $file['error'] !== UPLOAD_ERR_OK;

			$type = $error ? self::DEFAULT_TYPE : $file['type'];
			$filename = $file['name'];

			$name = $this->escapequotes($name);
			$filename = $this->escapequotes($filename);

			// header for file
			fwrite($this->pipes[0], sprintf(MULTIPART_FILE_FORMAT,
				$this->boundary, $name, $filename, $type));

			// write file to STDIN
			$fh = fopen($file['tmp_name'], 'rb');
			while (($buf = fgets($fh, self::BUFSIZ)) !== false) {
				fwrite($this->pipes[0], $buf);
			}
			fclose($fh);

			// end of file
			fwrite($this->pipes[0], "\r\n");
		}
	}

	public function do_headers() {
		while (1) {
			while (($line = fgets($this->pipes[1])) !== false) {
				if (preg_match('/^(?:\r?\n|\r)$/', $line))
					break 2; // End of headers

				header($line, false);
			}

			// Premature end of headers - TODO
			die();
		}
	}

	public function do_body() {
		while (($buf = fgets($this->pipes[1], self::BUFSIZ)) !== false)
			echo $buf;
	}

	public function close() {
		fclose($this->pipes[1]);
		proc_close($this->ph);
	}

	protected static function escapequotes($string) {
		return str_replace('"', '\"', $string);
	}

	const CGI_FILENAME = 'DOCUMENT_PATH';
	public static function is_handler() {
		return isset($_SERVER[self::CGI_FILENAME]);
	}
	
	public static function do_handler() {
		chdir(dirname($_SERVER[self::CGI_FILENAME]));
		execute_cgi(escapeshellarg($_SERVER[self::CGI_FILENAME]));
		exit;
	}
}

function execute_cgi($script) {
	$cgi = new PHP_CGI_Wrapper($script);
	$cgi->run();

	exit;
}

// If $_SERVER['DOCUMENT_PATH'], a non-standard environment value, is set, then
// assume we're acting like a handler for CGI scripts in a web server setup.
if (PHP_CGI_Wrapper::is_handler()) {
	PHP_CGI_Wrapper::do_handler();
}
