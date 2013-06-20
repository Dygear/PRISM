<?php
/**
 * PHPInSimMod - Telnet Module
 * @package PRISM
 * @subpackage Telnet
*/

require_once(ROOTPATH . '/modules/prism_telnet_defines.php');
require_once(ROOTPATH . '/modules/prism_telnet_server.php');
require_once(ROOTPATH . '/modules/prism_telnet_admins.php');
require_once(ROOTPATH . '/modules/prism_telnet_hosts.php');
require_once(ROOTPATH . '/modules/prism_telnet_plugins.php');

define('TELNET_NOT_LOGGED_IN', 0);
define('TELNET_ASKED_USERNAME', 1);
define('TELNET_ASKED_PASSWORD', 2);
define('TELNET_LOGGED_IN', 3);

class TelnetHandler extends SectionHandler
{
	private $telnetSock		= null;
	private $clients		= array();
	private $numClients		= 0;
	
	private $telnetVars		= array();
	
	public function __construct()
	{
		$this->iniFile = 'telnet.ini';
	}

	public function __destruct()
	{
		$this->close(true);
	}
	
	public function initialise()
	{
		global $PRISM;
		
		$this->telnetVars = array
		(
			'ip' => '', 
			'port' => 0,
		);

		if ($this->loadIniFile($this->telnetVars, false)) {
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
				console('Loaded '.$this->iniFile);
			}
		} else {
			# We ask the client to manually input the connection details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryTelnet($this->telnetVars);
			
			# Then build a telnet.ini file based on these details provided.
			$extraInfo = <<<ININOTES
;
; Telnet listen details (for remote console access).
; 0.0.0.0 (default) will bind the socket to all available network interfaces.
; To limit the bind to one interface only, you can enter its IP address here.
; If you do not want to use the telnet feature, you can comment or remove the 
; lines, or enter "" and 0 for the ip and port.
;

ININOTES;
			if ($this->createIniFile('Telnet Configuration (remote console)', array('telnet' => &$this->telnetVars), $extraInfo)) {
				console('Generated config/'.$this->iniFile);
			}
		}
		
		// Setup telnet socket to listen on
		if (!$this->setupListenSocket()) {
			return false;
		}
		
		return true;
	}

	private function setupListenSocket()
	{
		$this->close(false);
		
		if ($this->telnetVars['ip'] != '' && $this->telnetVars['port'] > 0) {
			$this->telnetSock = @stream_socket_server('tcp://'.$this->telnetVars['ip'].':'.$this->telnetVars['port'], $errNo, $errStr);
            
			if (!is_resource($this->telnetSock) || $this->telnetSock === FALSE || $errNo) {
				console('Error opening telnet socket : '.$errStr.' ('.$errNo.')');
				return false;
			} else {
				console('Listening for telnet input on '.$this->telnetVars['ip'].':'.$this->telnetVars['port']);
			}
		}
        
		return true;
	}

	private function close($all)
	{
		if (is_resource($this->telnetSock)) {
			fclose($this->telnetSock);
		}
		
		if (!$all) {
			return;
		}
		
		for ($k=0; $k<$this->numClients; $k++) {
			array_splice($this->clients, $k, 1);
			$k--;
			$this->numClients--;
		}
	}

	public function getSelectableSockets(array &$sockReads, array &$sockWrites)
	{
		// Add http sockets to sockReads
		if (is_resource($this->telnetSock)) {
			$sockReads[] = $this->telnetSock;
		}

		for ($k=0; $k<$this->numClients; $k++) {
			if (is_resource($this->clients[$k]->getSocket())) {
				$sockReads[] = $this->clients[$k]->getSocket();
				
				// If write buffer was full, we must check to see when we can write again
				if ($this->clients[$k]->getSendQLen() > 0) {
					$sockWrites[] = $this->clients[$k]->getSocket();
				}
			}
		}
	}

	public function checkTraffic(array &$sockReads, array &$sockWrites)
	{
		$activity = 0;

		// telnetSock input (incoming telnet connection)
		if (in_array($this->telnetSock, $sockReads)) {
			$activity++;
			
			// Accept the new connection
			$peerInfo = '';
			$sock = @stream_socket_accept ($this->telnetSock, null, $peerInfo);
            
			if (is_resource($sock)) {
				//stream_set_blocking ($sock, 0);
				
				// Add new connection to clients array
				$exp = explode(':', $peerInfo);
				$this->clients[] = new PrismTelnet($sock, $exp[0], $exp[1]);
				$this->numClients++;
				console('Telnet Client '.$exp[0].':'.$exp[1].' connected.');
			}
			unset ($sock);
		}
		
		// telnet clients input
		for ($k=0; $k<$this->numClients; $k++) {
			// Recover from a full write buffer?
			if ($this->clients[$k]->getSendQLen() > 0 && in_array($this->clients[$k]->getSocket(), $sockWrites)) {
				$activity++;
				
				// Flush the sendQ (bit by bit, not all at once - that could block the whole app)
				if ($this->clients[$k]->getSendQLen() > 0) {
					$this->clients[$k]->flushSendQ();
				}
			}
			
			// Did we receive something from a httpClient?
			if (!in_array($this->clients[$k]->getSocket(), $sockReads)) {
				continue;
			}

			$activity++;
			
			$data = $this->clients[$k]->read($data);
			
			// Did the client hang up?
			if ($data == '') {
				console('Closed telnet client (client initiated) '.$this->clients[$k]->getRemoteIP().':'.$this->clients[$k]->getRemotePort());
				array_splice ($this->clients, $k, 1);
				$k--;
				$this->numClients--;
				continue;
			}

			$this->clients[$k]->addInputToBuffer($data);
			$this->clients[$k]->processInput();

			if ($this->clients[$k]->getMustClose()) {
				$this->clients[$k]->__destruct();
				console('Closed telnet client (client ctrl-c) '.$this->clients[$k]->getRemoteIP().':'.$this->clients[$k]->getRemotePort());
				array_splice ($this->clients, $k, 1);
				$k--;
				$this->numClients--;
			}
		}
		
		return $activity;
	}
}

define('TS_SECTION_MAIN', 1);

/**
 * The PrismTelnet class handles :
 * -the Prism telnet login session
 * -all the information coming from the telnet client (KB input / commands)
 * -what will be drawn on the telnet client's screen
*/
class PrismTelnet extends TelnetServer
{
	// If filled in, the user is logged in (or half-way logging in).
	private $username		= '';
	
	// The state of the login process.
	private $loginState		= 0;
	
	// Section vars
	private $curSection		= '';		// holds the name of the currently active section
	private $section		= null;		// holds the actual active section object itself (accounts, hosts, plugins)
	
	private $menuBar		= null;		// cosmetic menu bar
	
	private $adminSection	= null;		// handles all account related stuff
	private $hostSection	= null;		// handles all host related stuff
	private $pluginSection	= null;		// handles all plugin related stuff
	
	public function __construct(&$sock, &$ip, &$port)
	{
		parent::__construct($sock, $ip, $port);

		// Clear screen
		$this->screenClear(true);
		
		// Send welcome message and ask for username
		$msg = "Welcome to the ".VT100_STYLE_BOLD."Prism v".PHPInSimMod::VERSION.VT100_STYLE_RESET." remote console.\r\n";
		$msg .= "Please login with your Prism account details.\r\n\r\n";
		$msg .= "Username : ";
		
		$this->writeBuf($msg);
		$this->flush();
		$this->loginState = TELNET_ASKED_USERNAME;
		
		$this->registerInputCallback($this, 'doLogin', TELNET_MODE_LINEEDIT);
	}
	
	public function __destruct()
	{
		$this->registerInputCallback(null);
		$this->setCursorProperties(0);
		
		// Remove all visual objects
		$this->clearObjects(true);
		
		// Clean up the sections
		if ($this->adminSection) {
			$this->adminSection->__destruct();
			$this->hostSection->__destruct();
			$this->pluginSection->__destruct();
		}
		
		$this->writeBuf(VT100_STYLE_RESET.VT100_USG0."Goodbye...\r\n");
		$this->flush();
	}

	protected function doLogin($line)
	{
		switch($this->getLoginState()) {
			case TELNET_NOT_LOGGED_IN :
				// Send error notice and ask for username
				$msg .= "\r\nPlease login with your Prism account details.\r\n";
				$msg .= "Username : ";
				
				$this->write($msg);
				$this->loginState = TELNET_ASKED_USERNAME;
				
				break;
			case TELNET_ASKED_USERNAME :
				if ($line == '') {
					$this->write("\r\nUsername : ");
					break;
				}
                
				$this->username = $line;
				$this->write("\r\nPassword : ");
				$this->loginState = TELNET_ASKED_PASSWORD;
				$this->setEchoChar('*');
				
				break;
			case TELNET_ASKED_PASSWORD :
				$this->setEchoChar(null);
				
				if ($this->verifyLogin($line)) {
					$this->loginState = TELNET_LOGGED_IN;

					$this->writeBuf("\r\nLogin successful\r\n");
					$this->writeBuf("(x or ctrl-c to exit)\r\n");
					$this->setCursorProperties(TELNET_CURSOR_HIDE);
					$this->flush();

					console('Successful telnet login from '.$this->username.' on '.date('r'));
					
					// Now setup the screen
					$this->setupMenu();
				} else {
					$msg = "\r\nIncorrect login. Please try again.\r\n";
					$msg .= "Username : ";
					$this->username = '';
					$this->write($msg);
					$this->loginState = TELNET_ASKED_USERNAME;
				}
				break;
		}
	}
	
	protected function getLoginState()
	{
		return $this->loginState;
	}
	
	private function verifyLogin(&$password)
	{
		global $PRISM;

		return ($PRISM->admins->isPasswordCorrect($this->username, $password));
	}
	
	private function setupMenu()
	{
		$this->screenClear();
		
		// Create section bar (header bar)
		$this->menuBar = new MenuBar($this->getTType());
		$this->add($this->menuBar);
		
		// Initialise the actual sections as separate objects.
		$this->adminSection = new TSAdminSection($this, $this->getWidth(), $this->getHeight()-3, $this->getTType());
		$this->adminSection->setActive(true);
		$this->add($this->adminSection);
		$this->section = $this->adminSection;
		$this->curSection = 'admins';

		$this->hostSection = new TSHostSection($this, $this->getWidth(), $this->getHeight()-3, $this->getTType());
		$this->hostSection->setActive(true);
		$this->setVisible(false);
		$this->add($this->hostSection);

		$this->pluginSection = new TSPluginSection($this, $this->getWidth(), $this->getHeight()-3, $this->getTType());
		$this->pluginSection->setActive(true);
		$this->setVisible(false);
		$this->add($this->pluginSection);

		$this->registerInputCallback($this, 'handleKey');
		$this->reDraw();
	}
	
	private function selectSection($section)
	{
		if ($this->curSection == $section) {
			return true;
		}
		

		// Make the section active
		switch ($section) {
			case 'admins' :
				$this->section->setVisible(false);
				$this->section = $this->adminSection;
				break;
				
			case 'hosts' :
				$this->section->setVisible(false);
				$this->section = $this->hostSection;
				break;
				
			case 'plugins' :
				$this->section->setVisible(false);
				$this->section = $this->pluginSection;
				break;
				
			default :
				return false;	
		}

		$this->section->setVisible(true);

		$this->menuBar->selectSection($section);
		$this->curSection = $section;
		
		return true;
	}
	
	/**
	 * When we are not in line-edit mode (to process a whole line of user-input),
	 * we use this handleKey function to process single key presses.
	 * These key presses drive the telnet text console application.
	 * In other words, the handleKey function is the main() of the telnet console application.
	*/
	protected function handleKey($key)
	{
		if (($tl = $this->getObjectById('testline')) === null) {
			$tl = new TSTextArea(1, $this->winSize[1], $this->winSize[0], 1);
			$tl->setId('testline');
			$this->add($tl);
		}
		
		// Handle section specific keys
		if ($this->section && $this->section->handleKey($key)) {
			$this->redraw();
			return;
		}
		
		// Default key actions
		switch ($key) {
			case 'A' :
				$this->selectSection('admins');
				break;
			case 'H' :
				$this->selectSection('hosts');
				break;
			case 'P' :
				$this->selectSection('plugins');
				break;
			case 'x' :
				$this->shutdown();
				return;
			case KEY_ENTER :
				$tl->setText('Enter');
				break;
			case KEY_CURLEFT :
				$tl->setText('Cursor left');
				break;
			case KEY_CURRIGHT :
				$tl->setText('Cursor right');
				break;
			case KEY_CURUP :
				$tl->setText('Cursor up');
				break;
			case KEY_CURDOWN :
				$tl->setText('Cursor down');
				break;
			case KEY_CURLEFT_CTRL :
				$tl->setText('CTRL-Cursor left');
				break;
			case KEY_CURRIGHT_CTRL :
				$tl->setText('CTRL-Cursor right');
				break;
			case KEY_CURUP_CTRL :
				$tl->setText('CTRL-Cursor up');
				break;
			case KEY_CURDOWN_CTRL :
				$tl->setText('CTRL-Cursor down');
				break;
			case KEY_HOME :
				$tl->setText('Home key');
				break;
			case KEY_END :
				$tl->setText('End key');
				break;
			case KEY_PAGEUP :
				$tl->setText('Page up');
				break;
			case KEY_PAGEDOWN :
				$tl->setText('Page down');
				break;
			case KEY_INSERT :
				$tl->setText('Insert');
				break;
			case KEY_BS :
				$tl->setText('Backspace');
				break;
			case KEY_TAB :
				$tl->setText('TAB key');
				break;
			case KEY_SHIFTTAB :
				$tl->setText('SHIFT-TAB key');
				break;
			case KEY_DELETE :
				$tl->setText('Delete key');
				break;
			case KEY_ESCAPE :
				$tl->setText('Escape key');
				break;
			case KEY_F1 :
				$tl->setText('F1 key');
				break;
			case KEY_F2 :
				$tl->setText('F2 key');
				break;
			case KEY_F3 :
				$tl->setText('F3 key');
				break;
			case KEY_F4 :
				$tl->setText('F4 key');
				break;
			case KEY_F5 :
				$tl->setText('F5 key');
				break;
			case KEY_F6 :
				$tl->setText('F6 key');
				break;
			case KEY_F7 :
				$tl->setText('F7 key');
				break;
			case KEY_F8 :
				// Toggle ttypes
				$this->setTType($this->getTType() + 1);
                
				if ($this->getTType() == TELNET_TTYPE_NUM) {
					$this->setTType(0);
				}
                
				$this->updateTTypes($this->getTType());
				$tl->setText('Toggling ttype ('.$this->getTType().')');
				break;
			case KEY_F9 :
				$tl->setText('F9 key');
				break;
			case KEY_F10 :
				$tl->setText('F10 key');
				break;
			case KEY_F11 :
				$tl->setText('F11 key');
				break;
			case KEY_F12 :
				$tl->setText('F12 key');
				break;
			default :
				$tl->setText($key.' pressed');
				break;
		}
		
		$this->redraw();
	}
}

class MenuBar extends ScreenContainer
{
	public function __construct($ttype)
	{
		$this->setSize(80, 1);
		$this->setId('mainMenu');
		$this->setTType($ttype);
		
		$textArea = new TSTextArea(3, 1, 6, 1);
		$textArea->setId('admins');
		$textArea->setOptions(TS_OPT_ISSELECTABLE | TS_OPT_ISSELECTED);
		$textArea->setText(VT100_STYLE_BOLD.'A'.VT100_STYLE_RESET.'dmins');
		$this->add($textArea);

		$textArea = new TSTextArea(16, 0, 5, 1);
		$textArea->setId('hosts');
		$textArea->setOptions(TS_OPT_ISSELECTABLE);
		$textArea->setText(VT100_STYLE_BOLD.'H'.VT100_STYLE_RESET.'osts');
		$this->add($textArea);

		$textArea = new TSTextArea(26, 0, 7, 1);
		$textArea->setId('plugins');
		$textArea->setOptions(TS_OPT_ISSELECTABLE);
		$textArea->setText(VT100_STYLE_BOLD.'P'.VT100_STYLE_RESET.'lugins');
		$this->add($textArea);

		$l = strlen('Prism v'.PHPInSimMod::VERSION);
		$textArea = new TSTextArea(80 - ($l + 1), 0, $l, 1);
		$textArea->setId('prismVersion');
		$textArea->setText(VT100_STYLE_GREEN.VT100_STYLE_BOLD.'Prism v'.PHPInSimMod::VERSION.VT100_STYLE_RESET);
		$this->add($textArea);

		$line = new TSHLine(2, 2, $this->getWidth() - 2);
		$line->setTType($this->getTType());
		$this->add($line);
	}
	
	public function selectSection($section)
	{
		$a = 0;
        
		while ($object = $this->getObjectByIndex($a)) {
			if ($object->getId() == $section) {
				if (($object->getOptions() & TS_OPT_ISSELECTED) == 0)
					$object->toggleSelected();
			} else {
				if (($object->getOptions() & TS_OPT_ISSELECTED) > 0) {
					$object->toggleSelected();
				}
			}
            
			$a++;
		}
	}
}

?>