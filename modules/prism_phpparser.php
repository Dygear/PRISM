<?php

class PHPParser
{
	static $scriptCache = array();
	
	static function parseFile(HttpResponse &$r, $file, array $SERVER, array &$_GET, array &$_POST, array &$_COOKIE)
	{
		// Change working dir to www-docs
		chdir(ROOTPATH.'/www-docs');
		
		$scriptnameHash = md5(ROOTPATH.'/www-docs'.$file);
		$scriptMTime = filemtime(ROOTPATH.'/www-docs'.$file);
		clearstatcache();

		// 'run' the php script
		$html = '';

		// From cache?
		if (isset(self::$scriptCache[$scriptnameHash]) &&
			self::$scriptCache[$scriptnameHash][0] == $scriptMTime)
		{
			eval(self::$scriptCache[$scriptnameHash][1]);
		}
		else
		{
			// Validate the php file
			$parseResult = validatePHPFile(ROOTPATH.'/www-docs'.$file);
			if ($parseResult[0])
			{
				$phpScript = preg_replace(array('/<\?(php)?/', '/\?>/'), '', file_get_contents(ROOTPATH.'/www-docs'.$file));
				eval($phpScript);

				// Cache the php file
				self::$scriptCache[$scriptnameHash] = array($scriptMTime, $phpScript);
			}
			else
			{
				$eol = "\r\n";
				$html = '<html>'.$eol;
				$html .= '<head><title>Error parsing page</title></head>'.$eol;
				$html .= '<body bgcolor="white">'.$eol;
				$html .= '<center><h3>'.implode('<br />\r\n', $parseResult[1]).'</h3></center>'.$eol;
				$html .= '<hr><center>PRISM v'.PHPInSimMod::VERSION.'</center>'.$eol;
				$html .= '</body>'.$eol;
				$html .= '</html>'.$eol;
				unset(self::$scriptCache[$scriptnameHash]);
			}
		}
//		@include(ROOTPATH.'/www-docs'.$file);

//var_dump(self::$scriptCache[$scriptnameHash]);

		// Restore the working dir
		chdir(ROOTPATH);

		// Use compression?
		if (isset($SERVER['HTTP_ACCEPT_ENCODING']))
		{
			if (strpos($SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) $encoding = 'x-gzip';
			else if (strpos($SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) $encoding = 'gzip';
			
			if ($encoding) {
			    $r->addHeader('Content-Encoding: '.$encoding);
			    return gzencode ($html, 1);
			} else return $html;
		}
		else
			return $html;
	}
}

?>