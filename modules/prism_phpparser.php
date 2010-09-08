<?php

class PHPParser
{
	static function parseFile(HttpResponse &$r, $file, &$_SERVER, &$_GET, &$_POST, &$_COOKIE)
	{
		$html = '';
		@include($file);
		return $html;
	}
}

?>