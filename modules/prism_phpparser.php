<?php

class PHPParser
{
	static function parseFile(HttpResponse &$r, $file, array $_SERVER, array &$_GET, array &$_POST, array &$_COOKIE)
	{
		// Change working dir to www-docs
		chdir(ROOTPATH.'/www-docs');
		
		// 'run' the php script
		$html = '';
		@include(ROOTPATH.'/www-docs'.$file);

		// Restore the working dir
		chdir(ROOTPATH);

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