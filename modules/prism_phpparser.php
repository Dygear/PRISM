<?php

class PHPParser
{
	static function parseFile(HttpResponse &$r, $file, array $_SERVER, array &$_GET, array &$_POST, array &$_COOKIE)
	{
		$html = '';
		@include($file);

		// Use compression?		
		if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) $encoding = 'x-gzip';
		else if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) $encoding = 'gzip';
		
		if ($encoding) {
		    $r->addHeader('Content-Encoding: '.$encoding);
		    return gzencode ($html, 1);
		} else return $html;
	}
}

?>