<?php
/**
 * PHPInSimMod - PHPParser Module
 * @package PRISM
 * @subpackage PHPParser
*/

define('PRISM_SESSION_TIMEOUT', 900);		// how long a session is valid 

class PHPParser
{
	private static $scriptCache = array();
	private static $sessions = array();
	
	public static function parseFile(HttpResponse &$RESPONSE, $file, array $SERVER, array &$GET, array &$POST, array &$COOKIE, array &$FILES)
	{
		global $PRISM;
		
		// Restore session?
		if (isset($COOKIE['PrismSession']) && isset(self::$sessions[$COOKIE['PrismSession']]) && self::$sessions[$COOKIE['PrismSession']][0] > time() && self::$sessions[$COOKIE['PrismSession']][1] == $SERVER['REMOTE_ADDR']) {
			$_SESSION = self::$sessions[$COOKIE['PrismSession']][2];
			
			// Sessions only last for one request. We rewrite it later on if needed.
			unset(self::$sessions[$COOKIE['PrismSession']]);
		}
		
		// Change working dir to docRoot
		chdir($PRISM->http->getDocRoot());
		
		$prismScriptNameHash = md5($PRISM->http->getDocRoot().$file);
		$prismScriptMTime = filemtime($PRISM->http->getDocRoot().$file);
		clearstatcache();

		// Run script from cache?
		if (isset(self::$scriptCache[$prismScriptNameHash]) && self::$scriptCache[$prismScriptNameHash][0] == $prismScriptMTime) {
			ob_start();
			eval(self::$scriptCache[$prismScriptNameHash][1]);
			$html = ob_get_contents();
			ob_end_clean();
		} else {
			// Validate the php file
			$parseResult = validatePHPFile($PRISM->http->getDocRoot().$file);
			if ($parseResult[0]) {
				// Run the script from disk
				$prismPhpScript = preg_replace(array('/^<\?(php)?/', '/\?>$/'), '', file_get_contents($PRISM->http->getDocRoot().$file));
				ob_start();
				eval($prismPhpScript);
				$html = ob_get_contents();
				ob_end_clean();

				// Cache the php file
				self::$scriptCache[$prismScriptNameHash] = array($prismScriptMTime, $prismPhpScript);
			} else {
				$eol = "\r\n";
				$html = '<html>'.$eol;
				$html .= '<head><title>Error parsing page</title></head>'.$eol;
				$html .= '<body bgcolor="white">'.$eol;
				$html .= '<center><h4>'.implode("<br />\r\n", $parseResult[1]).'</h4></center>'.$eol;
				$html .= '<hr><center>PRISM v'.PHPInSimMod::VERSION.'</center>'.$eol;
				$html .= '</body>'.$eol;
				$html .= '</html>'.$eol;
				unset(self::$scriptCache[$prismScriptNameHash]);
			}
		}

		// Should we store the session?
		if (isset($_SESSION) && $_SESSION != '') {
			$sessionID = sha1(createRandomString(128, RAND_BINARY).time());
			self::$sessions[$sessionID] = array(time() + PRISM_SESSION_TIMEOUT, $SERVER['REMOTE_ADDR'], $_SESSION);
			$RESPONSE->setCookie('PrismSession', $sessionID, time() + PRISM_SESSION_TIMEOUT, '/', $SERVER['SERVER_NAME']);
		} else if (isset($COOKIE['PrismSession'])) {
			$RESPONSE->setCookie('PrismSession', '', 0, '/', $SERVER['SERVER_NAME']);
		}
        
		unset($_SESSION);
		
		// Restore the working dir
		chdir(ROOTPATH);

		// Use compression?
		if ($html != '' && isset($SERVER['HTTP_ACCEPT_ENCODING'])) {
			$encoding = '';
            
			if (strpos($SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false) {
                $encoding = 'x-gzip';
			} else if (strpos($SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                $encoding = 'gzip';
			} else {
    		    # Nothing...   
			}
			
			if ($encoding) {
			    $RESPONSE->addHeader('Content-Encoding: '.$encoding);
			    return gzencode ($html, 1);
			} else {
                return $html;
			}
		} else {
			return $html;
		}
	}
	
	public static function cleanSessions()
	{
		foreach (self::$sessions as $k => $v) {
			if ($v[0] < time()) {
				unset(self::$sessions[$k]);
			}
		}
	}
}
