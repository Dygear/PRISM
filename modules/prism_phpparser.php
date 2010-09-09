<?php

class PHPParser
{
	static function parseFile(HttpResponse &$r, $file, array $_SERVER, array &$_GET, array &$_POST, array &$_COOKIE)
	{
		$html = '';
		@include($file);
		return $html;
	}
}

?>