<?php
/**
 * PHPInSimMod - Telnet Module
 * @package PRISM
 * @subpackage Telnet
*/

// IAC ACTION OPTION (3 bytes)
define('TELNET_OPT_BINARY',			chr(0x00));	// Binary (RCF 856)
define('TELNET_OPT_ECHO',			chr(0x01));	// (server) Echo (RFC 857)
define('TELNET_OPT_SGA',			chr(0x03));	// Suppres Go Ahead (RFC 858)
define('TELNET_OPT_TTYPE',			chr(0x18));	// Terminal Type (RFC 1091)
define('TELNET_OPT_NAWS',			chr(0x1F));	// Window Size (RFC 1073)
define('TELNET_OPT_TERMINAL_SPEED',	chr(0x20));	// Terminal Speed (RFC 1079)
define('TELNET_OPT_TOGGLE_FLOW_CONTROL', chr(0x21));	// flow control (RFC 1372)
define('TELNET_OPT_LINEMODE',		chr(0x22));	// Linemode (RFC 1184)
define('TELNET_OPT_NEW_ENVIRON',	chr(0x27));	// environment variables (RFC 1572)
define('TELNET_OPT_NOP',			chr(0xF1));	// No Operation.

// IAC OPTION (2 bytes)
define('TELNET_OPT_EOF',			chr(0xEC));
define('TELNET_OPT_SUSP',			chr(0xED));
define('TELNET_OPT_ABORT',			chr(0xEE));
define('TELNET_OPT_DM',				chr(0xF2));	// Indicates the position of a Synch event within the data stream. This should always be accompanied by a TCP urgent notification.
define('TELNET_OPT_BRK',			chr(0xF3));	// Break. Indicates that the “break” or “attention” key was hit.
define('TELNET_OPT_IP',				chr(0xF4));	// suspend/abort process.
define('TELNET_OPT_AO',				chr(0xF5));	// process can complete, but send no more output to users terminal.
define('TELNET_OPT_AYT',			chr(0xF6));	// check to see if system is still running.
define('TELNET_OPT_EC',				chr(0xF7));	// delete last character sent typically used to edit keyboard input.
define('TELNET_OPT_EL',				chr(0xF8));	// delete all input in current line.
define('TELNET_OPT_GA',				chr(0xF9));	// Used, under certain circumstances, to tell the other end that it can transmit.

// Suboptions Begin and End (variable byte length options with suboptions)
define('TELNET_OPT_SB',				chr(0xFA));	// Indicates that what follows is subnegotiation of the indicated option.
define('TELNET_OPT_SE',				chr(0xF0));	// End of subnegotiation parameters.

// ACTION bytes
define('TELNET_ACTION_WILL',		chr(0xFB));	// Indicates the desire to begin performing, or confirmation that you are now performing, the indicated option.
define('TELNET_ACTION_WONT',		chr(0xFC));	// Indicates the refusal to perform, or continue performing, the indicated option.
define('TELNET_ACTION_DO',			chr(0xFD));	// Indicates the request that the other party perform, or confirmation that you are expecting theother party to perform, the indicated option.
define('TELNET_ACTION_DONT',		chr(0xFE));	// Indicates the demand that the other party stop performing, or confirmation that you are no longer expecting the other party to perform, the indicated option.

// Command escape char
define('TELNET_IAC',				chr(0xFF));	// Interpret as command (commands begin with this value)

// Linemode sub options
define('LINEMODE_MODE',				chr(0x01));
define('LINEMODE_FORWARDMASK',		chr(0x02));
define('LINEMODE_SLC',				chr(0x03));	// Set Local Characters

// Linemode mode sub option values
define('LINEMODE_MODE_EDIT',		chr(0x01));
define('LINEMODE_MODE_TRAPSIG',		chr(0x02));
define('LINEMODE_MODE_MODE_ACK',	chr(0x04));
define('LINEMODE_MODE_SOFT_TAB',	chr(0x08));
define('LINEMODE_MODE_LIT_ECHO',	chr(0x10));

// Linemode Set Local Characters sub option values
define('LINEMODE_SLC_SYNCH',		chr(1));
define('LINEMODE_SLC_BRK',			chr(2));
define('LINEMODE_SLC_IP',			chr(3));
define('LINEMODE_SLC_AO',			chr(4));
define('LINEMODE_SLC_AYT',			chr(5));
define('LINEMODE_SLC_EOR',			chr(6));
define('LINEMODE_SLC_ABORT',		chr(7));
define('LINEMODE_SLC_EOF',			chr(8));
define('LINEMODE_SLC_SUSP',			chr(9));
define('LINEMODE_SLC_EC',			chr(10));
define('LINEMODE_SLC_EL',			chr(11));
define('LINEMODE_SLC_EW',			chr(12));
define('LINEMODE_SLC_RP',			chr(13));
define('LINEMODE_SLC_LNEXT',		chr(14));
define('LINEMODE_SLC_XON',			chr(15));
define('LINEMODE_SLC_XOFF',			chr(16));
define('LINEMODE_SLC_FORW1',		chr(17));
define('LINEMODE_SLC_FORW2',		chr(18));
define('LINEMODE_SLC_MCL',			chr(19));
define('LINEMODE_SLC_MCR',			chr(20));
define('LINEMODE_SLC_MCWL',			chr(21));
define('LINEMODE_SLC_MCWR',			chr(22));
define('LINEMODE_SLC_MCBOL',		chr(23));
define('LINEMODE_SLC_MCEOL',		chr(24));
define('LINEMODE_SLC_INSRT',		chr(25));
define('LINEMODE_SLC_OVER',			chr(26));
define('LINEMODE_SLC_ECR',			chr(27));
define('LINEMODE_SLC_EWR',			chr(28));
define('LINEMODE_SLC_EBOL', 		chr(29));
define('LINEMODE_SLC_EEOL',			chr(30));

define('LINEMODE_SLC_DEFAULT',		chr(3));
define('LINEMODE_SLC_VALUE',		chr(2));
define('LINEMODE_SLC_CANTCHANGE',	chr(1));
define('LINEMODE_SLC_NOSUPPORT',	chr(0));
define('LINEMODE_SLC_LEVELBITS',	chr(3));

define('LINEMODE_SLC_ACK',			chr(128));
define('LINEMODE_SLC_FLUSHIN',		chr(64));
define('LINEMODE_SLC_FLUSHOUT',		chr(32));

// Some telnet edit mode states
define('TELNET_MODE_ECHO', 1);
define('TELNET_MODE_LINEMODE', 2);
define('TELNET_MODE_BINARY', 4);
define('TELNET_MODE_SGA', 8);
define('TELNET_MODE_NAWS', 16);
define('TELNET_MODE_TERMINAL_SPEED', 32);
define('TELNET_MODE_NEW_ENVIRON', 64);

define('TELNET_MODE_INSERT', 1024);
define('TELNET_MODE_LINEEDIT', 2048);

define('TELNET_ECHO_NORMAL', 1);
define('TELNET_ECHO_STAR', 2);
define('TELNET_ECHO_NOTHING', 3);

define('TELNET_CURSOR_HIDE', 1);

// Terminal types
define('TELNET_TTYPE_OTHER',	0);
define('TELNET_TTYPE_XTERM',	1);
define('TELNET_TTYPE_ANSI',		2);
define('TELNET_TTYPE_NUM',		3);

// Standard control keys
define('KEY_IP',					chr(0x03));			// Interrupt Process (break)
define('KEY_BS',					chr(0x08));			// backspace
define('KEY_TAB',					chr(0x09));			// TAB
define('KEY_SHIFTTAB',				chr(0x01).chr(9));	// SHIFT-TAB
define('KEY_ENTER',					chr(0x0A));			// Enter
define('KEY_ESCAPE',				chr(0x1B));			// escape
define('KEY_DELETE',				chr(0x7F));			// del

// Self defined key codes
define('KEY_CURLEFT',				chr(0x01).chr(0));		// Cursor LEFT
define('KEY_CURRIGHT',				chr(0x01).chr(1));		// Cursor LEFT
define('KEY_CURUP',					chr(0x01).chr(2));		// Cursor LEFT
define('KEY_CURDOWN',				chr(0x01).chr(3));		// Cursor LEFT
define('KEY_HOME',					chr(0x01).chr(4));		// Home
define('KEY_END',					chr(0x01).chr(5));		// End
define('KEY_PAGEUP',				chr(0x01).chr(6));		// Home
define('KEY_PAGEDOWN',				chr(0x01).chr(7));		// End
define('KEY_INSERT',				chr(0x01).chr(8));		// Insert

define('KEY_CURLEFT_CTRL',			chr(0x02).chr(0));		// Cursor LEFT with ctrl
define('KEY_CURRIGHT_CTRL',			chr(0x02).chr(1));		// Cursor LEFT with ctrl
define('KEY_CURUP_CTRL',			chr(0x02).chr(2));		// Cursor LEFT with ctrl
define('KEY_CURDOWN_CTRL',			chr(0x02).chr(3));		// Cursor LEFT with ctrl

define('KEY_F1',					chr(0x01).chr(11));		// F1
define('KEY_F2',					chr(0x01).chr(12));		// F2
define('KEY_F3',					chr(0x01).chr(13));		// F3
define('KEY_F4',					chr(0x01).chr(14));		// F4
define('KEY_F5',					chr(0x01).chr(15));		// F5
define('KEY_F6',					chr(0x01).chr(17));		// F6
define('KEY_F7',					chr(0x01).chr(18));		// F7
define('KEY_F8',					chr(0x01).chr(19));		// F8
define('KEY_F9',					chr(0x01).chr(20));		// F9
define('KEY_F10',					chr(0x01).chr(21));		// F10
define('KEY_F11',					chr(0x01).chr(23));		// F11
define('KEY_F12',					chr(0x01).chr(24));		// F12

// ANSI escape sequences VT100
define('VT100_USG0',				KEY_ESCAPE.'(B');
define('VT100_USG1',				KEY_ESCAPE.')B');
define('VT100_USG0_LINE',			KEY_ESCAPE.'(0');
define('VT100_USG1_LINE',			KEY_ESCAPE.')0');
define('VT100_G0_ALTROM',			KEY_ESCAPE.'(1');
define('VT100_G1_ALTROM',			KEY_ESCAPE.')1');
define('VT100_G0_ALTROM_GFX',		KEY_ESCAPE.'(2');
define('VT100_G1_ALTROM_GFX',		KEY_ESCAPE.')2');

define('VT100_SSHIFT2',				KEY_ESCAPE.'N');
define('VT100_SSHIFT3',				KEY_ESCAPE.'O');

define('VT100_ED2',					KEY_ESCAPE.'[2J');		// Clear entire screen

define('VT100_CURSORHOME',			KEY_ESCAPE.'[H');		// Move cursor to upper-left corner

define('VT100_STYLE_RESET',			KEY_ESCAPE.'[0m');		// Attribs off
define('VT100_STYLE_BOLD',			KEY_ESCAPE.'[1m');		// bold
define('VT100_STYLE_LOWINTENS',		KEY_ESCAPE.'[2m');		// low intensity
define('VT100_STYLE_UNDERLINE',		KEY_ESCAPE.'[4m');		// underline
define('VT100_STYLE_BLINK',			KEY_ESCAPE.'[5m');		// blink
define('VT100_STYLE_REVERSE',		KEY_ESCAPE.'[7m');		// reverse video
define('VT100_STYLE_INVISIBLE',		KEY_ESCAPE.'[8m');		// invisible text

define('VT100_STYLE_BLACK',			KEY_ESCAPE.'[30m');
define('VT100_STYLE_RED',			KEY_ESCAPE.'[31m');
define('VT100_STYLE_GREEN',			KEY_ESCAPE.'[32m');
define('VT100_STYLE_YELLOW',		KEY_ESCAPE.'[33m');
define('VT100_STYLE_BLUE',			KEY_ESCAPE.'[34m');
define('VT100_STYLE_MAGENTA',		KEY_ESCAPE.'[35m');
define('VT100_STYLE_CYAN',			KEY_ESCAPE.'[36m');
define('VT100_STYLE_WHITE',			KEY_ESCAPE.'[37m');

define('VT100_STYLE_BG_BLACK',		KEY_ESCAPE.'[40m');
define('VT100_STYLE_BG_RED',		KEY_ESCAPE.'[41m');
define('VT100_STYLE_BG_GREEN',		KEY_ESCAPE.'[42m');
define('VT100_STYLE_BG_YELLOW',		KEY_ESCAPE.'[43m');
define('VT100_STYLE_BG_BLUE',		KEY_ESCAPE.'[44m');
define('VT100_STYLE_BG_MAGENTA',	KEY_ESCAPE.'[45m');
define('VT100_STYLE_BG_CYAN',		KEY_ESCAPE.'[46m');
define('VT100_STYLE_BG_WHITE',		KEY_ESCAPE.'[47m');

?>