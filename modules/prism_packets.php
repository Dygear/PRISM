<?php
/**
 * PHPInSimMod - Packet Module
 * @package PRISM
 * @subpackage Packet
 */

/* Start of PRISM PACKET HEADER */
abstract class Struct
{
	public function __conStruct($rawPacket = null)
	{
		if ($rawPacket !== null) {
			$this->unpack($rawPacket);
		}
        
		return $this;
	}
    
	public function __invoke()
	{
		$argv = func_get_args();
		$argi = 0;
		$argc = count($argv);
        
		foreach ($this as $property => $value) {
			$RP = new ReflectionProperty(get_class($this), $property);
            
			if ($RP->isPublic()) {
				$object->$property = $argv[$argi++];
			}
            
			if ($argc == $argi) {
				continue;
			}
		}
	}
    
	public function __toString()
	{
		return $this->printPacketDetails();
	}
    
	// Magic Methods (Object Overloading)
	public function &__get($name)
	{
		$return = false;
        
		if (!property_exists(get_class($this), $name)) {
			return $return;
		} else {
			return $this->$name;
		}
	}
    
	public function &__call($name, $arguments)
	{
		if (property_exists(get_class($this), $name)) {
			$this->$name = array_shift($arguments);
		}
        
		return $this;
	}
    
	public function __isset($name)
	{
		return isset($this->$name);
	}
    
	public function __unset($name)
	{
		if (isset($this->$name)) {
			$this->$name = null;
		}
	}
    
	// Normal Methods
	public function send($hostId = null)
	{
		global $PRISM;
		$PRISM->hosts->sendPacket($this, $hostId);
		return $this;
	}
    
	public function printPacketDetails($pre = '')
	{
		global $TYPEs;
		$packFormat = $this->parsePackFormat();
		$propertyNumber = -1;
		$str = $pre . get_class($this) . ' {' . PHP_EOL;
        
		foreach ($this as $property => $value) {
			$pkFnkFormat = $packFormat[++$propertyNumber];
            
			if (gettype($this->$property) == 'array') {
				$str .= "{$pre}\tArray\t{$property}\t= {" . PHP_EOL;
                
				foreach ($this->$property as $k => $v) {
					if ($v instanceof Struct) {
						$str .= $pre . $v->printPacketDetails($pre . "\t\t\t") . PHP_EOL;
					} else {
						$str .= "{$pre}\t\t\t{$k}\t{$v}" . PHP_EOL;
					}
				}
                
				$str .= "{$pre}\t}" . PHP_EOL;
				break;
			} elseif ($property == 'Type') {
				$str .= "{$pre}\t{$pkFnkFormat}\t{$property}\t= {$TYPEs[$this->Type]} ({$this->$property})" . PHP_EOL;
			} else {
				$str .= "{$pre}\t{$pkFnkFormat}\t{$property}\t= {$this->$property}" . PHP_EOL;
			}
		}
        
		return "{$str}{$pre}}" . PHP_EOL;
	}
    
	public function unpack($rawPacket)
	{
		foreach (unpack($this::UNPACK, $rawPacket) as $property => $value) {
			$this->$property = $value;
		}

		return $this;
	}
	public function pack()
	{
		$return = '';
		$packFormat = $this->parsePackFormat();
		$propertyNumber = -1;
        
		foreach ($this as $property => $value) {
			$pkFnkFormat = $packFormat[++$propertyNumber];
			
            if ($pkFnkFormat == 'x') {
				$return .= pack('C', 0); # null & 0 are the same thing in Binary (00000000) and Hex (x00), so null == 0.
			} elseif (is_array($pkFnkFormat)) {
				list($type, $elements) = $pkFnkFormat;
                
				if (($j = count($value)) > $elements) {
					$j = $elements;
				}
                
				for ($i = 0; $i < $j; ++$i, --$j) {
					var_dump($value, $type, $elements, $i, $j, $value[$i]);
					$return .= pack($type, $value[$i]);
				}
                
				if ($j > 0) {
					$return .= pack("x{$j}");	# Fills the rest of the space with null data.
				}
			} else {
				$return .= pack($pkFnkFormat, $value);
			}
		}
        
		return $return;
	}
    
	public function parseUnpackFormat()
	{
		$return = array();
        
		foreach (explode('/', $this::UNPACK) as $element) {
			for ($i = 1; is_numeric($element{$i}); ++$i) {}
            
			$dataType = substr($element, 0, $i);
			$dataName = substr($element, $i);
			$return[$dataName] = $dataType;
		}
        
		return $return;
	}
    
	public function parsePackFormat()
	{
		$format = $this::PACK; # It does not like using $this::PACK directly.
		$elements = array();

        for ($i = 0, $j = 1, $k = strLen($format); $i < $k; ++$i, ++$j) # i = Current Character; j = Look ahead for numbers. {
			# Is current is string and next is no number
			if (is_string($format{$i}) && !isset($format[$j]) || !is_numeric($format[$j])) {
				$elements[] = $format{$i};
			} else {
				while (isset($format{$j}) && is_numeric($format{$j})) {
					++$j;	# Will be the last number of the current element.
				}

				$number = substr($format, $i + 1, $j - ($i + 1));

				if ($format{$i} == 'a' || $format{$i} == 'A') { # In these cases it's a string type where dealing with.
					$elements[] = $format{$i}.$number;
				} else { # In these cases, we should get an array.
					$elements[] = array($format{$i}, $number);
				}

				$i = $j - 1; # Movies the pointer to the end of this element.
			}
		}
        
		return $elements;
	}
}

/* End of PRISM PACKET HEADER */

#ifndef _ISPACKETS_H_
#define _ISPACKETS_H_
/////////////////////

// InSim for Live for Speed : 0.5Z

// InSim allows communication between up to 8 external programs and LFS.

// TCP or UDP packets can be sent in both directions, LFS reporting various
// things about its state, and the external program requesting info and
// controlling LFS with special packets, text commands or keypresses.

// NOTE : This text file was written with a TAB size equal to 4 spaces.


// NOTE : This text file was written with a TAB size equal to 4 spaces.
// ====================

/* const int INSIM_VERSION = 5; */
define('INSIM_VERSION', 5);

// CHANGES
// =======

// Version 0.5Z (compatible so no change to INSIM_VERSION)

// NLP / MCI packets are now output at regular intervals
// CCI_LAG bit added to the CompCar Structure

// Version 0.5Z30 (INSIM_VERSION increased to 5)

// NLP / MCI minimum time interval reduced to 40 ms (was 50 ms)
// IS_CON (CONtact) reports contact between two cars (if ISF_CON is enabled)
// IS_MTC (Msg To Connection) now has a variable length (up to 128 characters)
// IS_MTC can be sent to all (UCID = 255) and sound effect can be specified
// ISS_SHIFTU_HIGH is no longer used (no distinction between high and low view)
// FIX : Clutch axis / button was not reported after a change in Controls screen

// Version 0.5Z32

// OG_SHIFT and OG_CTRL bits added to OutGaugePack
// Lap timing info added to IS_RST (Timing byte)
// IS_VTC now cancels game votes even if the majority has not been reached

// Version 0.6A1

// IS_OBH reports information about any object hit
// IS_HLV reports incidents that would violate HLVC
// IS_PLC sets allowed cars for individual players
// IS_AXM to add / remove / clear autocross objects
// IS_ACR to report (attempted) admin commands


// TYPES : (all multi-byte types are PC style - lowest byte first)
// =====

// type			machine byte type			php pack / unpack type
// char			1-byte character			c
// byte			1-byte unsigned integer		C
// word			2-byte unsigned integer		v
// short		2-byte signed integer		s
// unsigned		4-byte unsigned integer		V
// int			4-byte signed integerz		l
// float		4-byte float				f
/* string		var-byte array of charaters	a		*/

// RaceLaps (rl) : (various meanings depending on range)

// 0       : practice
// 1-99    : number of laps...   laps  = rl
// 100-190 : 100 to 1000 laps... laps  = (rl - 100) * 10 + 100
// 191-238 : 1 to 48 hours...    hours = rl - 190


// InSim PACKETS
// =============

// All InSim packets use a four byte header

// Size : total packet size - a multiple of 4
// Type : packet identifier from the ISP_ enum (see below)
// ReqI : non zero if the packet is a packet request or a reply to a request
// Data : the first data byte


// INITIALISING InSim
// ==================

// To initialise the InSim system, type into LFS : /insim xxxxx
// where xxxxx is the TCP and UDP port you want LFS to open.

// OR start LFS with the command line option : LFS /insim=xxxxx
// This will make LFS listen for packets on that TCP and UDP port.


// TO START COMMUNICATION
// ======================

// TCP : Connect to LFS using a TCP connection, then send this packet :
// UDP : No connection required, just send this packet to LFS :

#What is the long name of this class?

class IS_ISI extends Struct // InSim Init - packet to initialise the InSim system
{
	const PACK = 'CCCxvvxCva16a16';
	const UNPACK = 'CSize/CType/CReqI/CZero/vUDPPort/vFlags/CSp0/CPrefix/vInterval/a16Admin/a16IName';

	protected $Size = 44;				# 44
	protected $Type = ISP_ISI;			# always ISP_ISI
	public $ReqI;						# If non-zero LFS will send an IS_VER packet
	protected $Zero = null;				# 0

	public $UDPPort;					# Port for UDP replies from LFS (0 to 65535)
	public $Flags;						# Bit flags for options (see below)

	protected $Sp0 = null;				# 0
	public $Prefix;						# Special host message prefix character
	public $Interval;					# Time in ms between NLP or MCI (0 = none)

	public $Admin;						# Admin password (if set in LFS)
	public $IName;						# A short name for your program
}; function IS_ISI() { return new IS_ISI; }

// NOTE 1) UDPPort field when you connect using UDP :

// zero     : LFS sends all packets to the port of the incoming packet
// non-zero : LFS sends all packets to the specified UDPPort

// NOTE 2) UDPPort field when you connect using TCP :

// zero     : LFS sends NLP / MCI packets using your TCP connection
// non-zero : LFS sends NLP / MCI packets to the specified UDPPort

// NOTE 3) Flags field (set the relevant bits to turn on the option) :

define('ISF_RES_0',		1);		// bit  0 : spare
define('ISF_RES_1',		2);		// bit  1 : spare
define('ISF_LOCAL',		4);		// bit  2 : guest or single player
define('ISF_MSO_COLS',	8);		// bit  3 : keep colours in MSO text
define('ISF_NLP',		16);	// bit  4 : receive NLP packets
define('ISF_MCI',		32);	// bit  5 : receive MCI packets
define('ISF_CON',		64);	// bit  6 : receive CON packets
define('ISF_OBH',		128);	// bit  7 : receive OBH packets
define('ISF_HLV',		256);	// bit  8 : receive HLV packets
define('ISF_AXM_LOAD',	512);	// bit  9 : receive AXM when loading a layout
define('ISF_AXM_EDIT',	1024);	// bit 10 : receive AXM when changing objects
$ISF = array(ISF_RES_0 => 'ISF_RES_0', ISF_RES_1 => 'ISF_RES_1', ISF_LOCAL => 'ISF_LOCAL', ISF_MSO_COLS => 'ISF_MSO_COLS', ISF_NLP => 'ISF_NLP', ISF_MCI => 'ISF_MCI', ISF_CON => 'ISF_CON', ISF_OBH => 'ISF_OBH', ISF_HLV => 'ISF_HLV', ISF_AXM_LOAD => 'ISF_AXM_LOAD', ISF_AXM_EDIT => 'ISF_AXM_EDIT');

// In most cases you should not set both ISF_NLP and ISF_MCI flags
// because all IS_NLP information is included in the IS_MCI packet.

// The ISF_LOCAL flag is important if your program creates buttons.
// It should be set if your program is not a host control system.
// If set, then buttons are created in the local button area, so
// avoiding conflict with the host buttons and allowing the user
// to switch them with SHIFT+B rather than SHIFT+I.

// NOTE 4) Prefix field, if set when initialising InSim on a host :

// Messages typed with this prefix will be sent to your InSim program
// on the host (in IS_MSO) and not displayed on anyone's screen.


// ENUMERATIONS FOR PACKET TYPES
// =============================

// the second byte of any packet is one of these
define('ISP_NONE',	0);	//  0					: not used
define('ISP_ISI',	1);	//  1 - inStruction		: insim initialise
define('ISP_VER',	2);	//  2 - info			: version info
define('ISP_TINY',	3);	//  3 - both ways		: multi purpose
define('ISP_SMALL',	4);	//  4 - both ways		: multi purpose
define('ISP_STA',	5);	//  5 - info			: state info
define('ISP_SCH',	6);	//  6 - inStruction		: single character
define('ISP_SFP',	7);	//  7 - inStruction		: state flags pack
define('ISP_SCC',	8);	//  8 - inStruction		: set car camera
define('ISP_CPP',	9);	//  9 - both ways		: cam pos pack
define('ISP_ISM',	10);// 10 - info			: start multiplayer
define('ISP_MSO',	11);// 11 - info			: message out
define('ISP_III',	12);// 12 - info			: hidden /i message
define('ISP_MST',	13);// 13 - inStruction		: type message or /command
define('ISP_MTC',	14);// 14 - inStruction		: message to a connection
define('ISP_MOD',	15);// 15 - inStruction		: set screen mode
define('ISP_VTN',	16);// 16 - info			: vote notification
define('ISP_RST',	17);// 17 - info			: race start
define('ISP_NCN',	18);// 18 - info			: new connection
define('ISP_CNL',	19);// 19 - info			: connection left
define('ISP_CPR',	20);// 20 - info			: connection renamed
define('ISP_NPL',	21);// 21 - info			: new player (joined race)
define('ISP_PLP',	22);// 22 - info			: player pit (keeps slot in race)
define('ISP_PLL',	23);// 23 - info			: player leave (spectate - loses slot)
define('ISP_LAP',	24);// 24 - info			: lap time
define('ISP_SPX',	25);// 25 - info			: split x time
define('ISP_PIT',	26);// 26 - info			: pit stop start
define('ISP_PSF',	27);// 27 - info			: pit stop finish
define('ISP_PLA',	28);// 28 - info			: pit lane enter / leave
define('ISP_CCH',	29);// 29 - info			: camera changed
define('ISP_PEN',	30);// 30 - info			: penalty given or cleared
define('ISP_TOC',	31);// 31 - info			: take over car
define('ISP_FLG',	32);// 32 - info			: flag (yellow or blue)
define('ISP_PFL',	33);// 33 - info			: player flags (help flags)
define('ISP_FIN',	34);// 34 - info			: finished race
define('ISP_RES',	35);// 35 - info			: result confirmed
define('ISP_REO',	36);// 36 - both ways		: reorder (info or inStruction)
define('ISP_NLP',	37);// 37 - info			: node and lap packet
define('ISP_MCI',	38);// 38 - info			: multi car info
define('ISP_MSX',	39);// 39 - inStruction		: type message
define('ISP_MSL',	40);// 40 - inStruction		: message to local computer
define('ISP_CRS',	41);// 41 - info			: car reset
define('ISP_BFN',	42);// 42 - both ways		: delete buttons / receive button requests
define('ISP_AXI',	43);// 43 - info			: autocross layout information
define('ISP_AXO',	44);// 44 - info			: hit an autocross object
define('ISP_BTN',	45);// 45 - inStruction		: show a button on local or remote screen
define('ISP_BTC',	46);// 46 - info			: sent when a user clicks a button
define('ISP_BTT',	47);// 47 - info			: sent after typing into a button
define('ISP_RIP',	48);// 48 - both ways		: replay information packet
define('ISP_SSH',	49);// 49 - both ways		: screenshot
define('ISP_CON',	50);// 50 - info			: contact (collision report)
define('ISP_OBH',	51);// 51 - info			: contact car + object (collision report)
define('ISP_HLV',	52);// 52 - info			: report incidents that would violate HLVC
define('ISP_PLC',	53);// 53 - instruction		: player cars
define('ISP_AXM',	54);// 54 - both ways		: autocross multiple objects
define('ISP_ACR',	55);// 55 - info			: admin command report
$ISP = array(ISP_NONE => 'ISP_NONE', ISP_ISI => 'ISP_ISI', ISP_VER => 'ISP_VER', ISP_TINY => 'ISP_TINY', ISP_SMALL => 'ISP_SMALL', ISP_STA => 'ISP_STA', ISP_SCH => 'ISP_SCH', ISP_SFP => 'ISP_SFP', ISP_SCC => 'ISP_SCC', ISP_CPP => 'ISP_CPP', ISP_ISM => 'ISP_ISM', ISP_MSO => 'ISP_MSO', ISP_III => 'ISP_III', ISP_MST => 'ISP_MST', ISP_MTC => 'ISP_MTC', ISP_MOD => 'ISP_MOD', ISP_VTN => 'ISP_VTN', ISP_RST => 'ISP_RST', ISP_NCN => 'ISP_NCN', ISP_MTC => 'ISP_MTC', ISP_CNL => 'ISP_CNL', ISP_CPR => 'ISP_CPR', ISP_NPL => 'ISP_NPL', ISP_PLP => 'ISP_PLP', ISP_PLL => 'ISP_PLL', ISP_LAP => 'ISP_LAP', ISP_SPX => 'ISP_SPX', ISP_PIT => 'ISP_PIT', ISP_PSF => 'ISP_PSF', ISP_PLA => 'ISP_PLA', ISP_CCH => 'ISP_CCH', ISP_PEN => 'ISP_PEN', ISP_TOC => 'ISP_TOC', ISP_FLG => 'ISP_FLG', ISP_PFL => 'ISP_PFL', ISP_FIN => 'ISP_FIN', ISP_RES => 'ISP_RES', ISP_REO => 'ISP_REO', ISP_NLP => 'ISP_NLP', ISP_MCI => 'ISP_MCI', ISP_MSX => 'ISP_MSX', ISP_MSL => 'ISP_MSL', ISP_CRS => 'ISP_CRS', ISP_BFN => 'ISP_BFN', ISP_AXI => 'ISP_AXI', ISP_AXO => 'ISP_AXO', ISP_BTN => 'ISP_BTN', ISP_BTC => 'ISP_BTC', ISP_BTT => 'ISP_BTT', ISP_RIP => 'ISP_RIP', ISP_SSH => 'ISP_SSH', ISP_CON => 'ISP_CON', ISP_OBH => 'ISP_OBH', ISP_HLV => 'ISP_HLV', ISP_PLC => 'ISP_PLC', ISP_AXM => 'ISP_AXM', ISP_ACR => 'ISP_ACR');

// the fourth byte of an IS_TINY packet is one of these
define('TINY_NONE',	0);	//  0 - keep alive		: see "maintaining the connection"
define('TINY_VER',	1);	//  1 - info request	: get version
define('TINY_CLOSE',2);	//  2 - inStruction		: close insim
define('TINY_PING',	3);	//  3 - ping request	: external progam requesting a reply
define('TINY_REPLY',4);	//  4 - ping reply		: reply to a ping request
define('TINY_VTC',	5);	//  5 - both ways		: game vote cancel (info or request)
define('TINY_SCP',	6);	//  6 - info request	: send camera pos
define('TINY_SST',	7);	//  7 - info request	: send state info
define('TINY_GTH',	8);	//  8 - info request	: get time in hundredths (i.e. SMALL_RTP)
define('TINY_MPE',	9);	//  9 - info			: multi player end
define('TINY_ISM',	10);// 10 - info request	: get multiplayer info (i.e. ISP_ISM)
define('TINY_REN',	11);// 11 - info			: race end (return to game setup screen)
define('TINY_CLR',	12);// 12 - info			: all players cleared from race
define('TINY_NCN',	13);// 13 - info request	: get all connections
define('TINY_NPL',	14);// 14 - info request	: get all players
define('TINY_RES',	15);// 15 - info request	: get all results
define('TINY_NLP',	16);// 16 - info request	: send an IS_NLP
define('TINY_MCI',	17);// 17 - info request	: send an IS_MCI
define('TINY_REO',	18);// 18 - info request	: send an IS_REO
define('TINY_RST',	19);// 19 - info request	: send an IS_RST
define('TINY_AXI',	20);// 20 - info request	: send an IS_AXI - AutoX Info
define('TINY_AXC',	21);// 21 - info			: autocross cleared
define('TINY_RIP',	22);// 22 - info request	: send an IS_RIP - Replay Information Packet
$TINY = array(TINY_NONE => 'TINY_NONE', TINY_VER => 'TINY_VER', TINY_CLOSE => 'TINY_CLOSE', TINY_PING => 'TINY_PING', TINY_REPLY => 'TINY_REPLY', TINY_VTC => 'TINY_VTC', TINY_SCP => 'TINY_SCP', TINY_SST => 'TINY_SST', TINY_GTH => 'TINY_GTH', TINY_MPE => 'TINY_MPE', TINY_ISM => 'TINY_ISM', TINY_REN => 'TINY_REN', TINY_CLR => 'TINY_CLR', TINY_NCN => 'TINY_NCN', TINY_NPL => 'TINY_NPL', TINY_RES => 'TINY_RES', TINY_NLP => 'TINY_NLP', TINY_MCI => 'TINY_MCI', TINY_REO => 'TINY_REO', TINY_RST => 'TINY_RST', TINY_AXI => 'TINY_AXI', TINY_AXC => 'TINY_AXC', TINY_RIP => 'TINY_RIP');

// the fourth byte of an IS_SMALL packet is one of these
define('SMALL_NONE',0);	//  0					: not used
define('SMALL_SSP',	1);	//  1 - inStruction		: start sending positions
define('SMALL_SSG',	2);	//  2 - inStruction		: start sending gauges
define('SMALL_VTA',	3);	//  3 - report			: vote action
define('SMALL_TMS',	4);	//  4 - inStruction		: time stop
define('SMALL_STP',	5);	//  5 - inStruction		: time step
define('SMALL_RTP',	6);	//  6 - info			: race time packet (reply to GTH)
define('SMALL_NLI',	7);	//  7 - inStruction		: set node lap interval
$SMALL = array(SMALL_NONE => 'SMALL_NONE', SMALL_SSP => 'SMALL_SSP', SMALL_SSG => 'SMALL_SSG', SMALL_VTA => 'SMALL_VTA', SMALL_TMS => 'SMALL_TMS', SMALL_STP => 'SMALL_STP', SMALL_RTP => 'SMALL_RTP', SMALL_NLI => 'SMALL_NLI');


// GENERAL PURPOSE PACKETS - IS_TINY (4 bytes) and IS_SMALL (8 bytes)
// =======================

// To avoid defining several packet Structures that are exactly the same, and to avoid
// wasting the ISP_ enumeration, IS_TINY is used at various times when no additional data
// other than SubT is required.  IS_SMALL is used when an additional integer is needed.

// IS_TINY - used for various requests, replies and reports

class IS_TINY extends Struct // General purpose 4 byte packet
{
	const PACK = 'CCCC';
	const UNPACK = 'CSize/CType/CReqI/CSubT';

	protected $Size = 4;				# always 4
	protected $Type = ISP_TINY;			# always ISP_TINY
	public $ReqI;						# 0 unless it is an info request or a reply to an info request
	public $SubT;						# subtype, from TINY_ enumeration (e.g. TINY_RACE_END)
}; function IS_TINY() { return new IS_TINY; }

// IS_SMALL - used for various requests, replies and reports

class IS_SMALL extends Struct // General purpose 8 byte packet
{
	const PACK = 'CCCCV';
	const UNPACK = 'CSize/CType/CReqI/CSubT/VUVal';

	protected $Size = 8;				# always 8
	protected $Type = ISP_SMALL;		# always ISP_SMALL
	public $ReqI;						# 0 unless it is an info request or a reply to an info request
	public $SubT;						# subtype, from SMALL_ enumeration (e.g. SMALL_SSP)

	public $UVal;						# value (e.g. for SMALL_SSP this would be the OutSim packet rate)
}; function IS_SMALL() { return new IS_SMALL; }


// VERSION REQUEST
// ===============

// It is advisable to request version information as soon as you have connected, to
// avoid problems when connecting to a host with a later or earlier version.  You will
// be sent a version packet on connection if you set ReqI in the IS_ISI packet.

// This version packet can be sent on request :

class IS_VER extends Struct // VERsion
{
	const PACK = 'CCCxa8a6v';
	const UNPACK = 'CSize/CType/CReqI/CZero/a8Version/a6Product/vInSimVer';

	protected $Size = 20;				# 20
	protected $Type = ISP_VER;			# ISP_VERSION
	public $ReqI;						# ReqI as received in the request packet
	protected $Zero;

	public $Version;					# LFS version, e.g. 0.3G
	public $Product;					# Product : DEMO or S1
	public $InSimVer = INSIM_VERSION;	# InSim Version : increased when InSim packets change
}; function IS_VER() { return new IS_VER; }

// To request an InSimVersion packet at any time, send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_VER		(request an IS_VER)


// CLOSING InSim
// =============

// You can send this IS_TINY to close the InSim connection to your program :

// ReqI : 0
// SubT : TINY_CLOSE	(close this connection)

// Another InSimInit packet is then required to start operating again.

// You can shut down InSim completely and stop it listening at all by typing /insim=0
// into LFS (or send a MsgTypePack to do the same thing).


// MAINTAINING THE CONNECTION - IMPORTANT
// ==========================

// If InSim does not receive a packet for 70 seconds, it will close your connection.
// To open it again you would need to send another InSimInit packet.

// LFS will send a blank IS_TINY packet like this every 30 seconds :

// ReqI : 0
// SubT : TINY_NONE		(keep alive packet)

// You should reply with a blank IS_TINY packet :

// ReqI : 0
// SubT : TINY_NONE		(has no effect other than resetting the timeout)

// NOTE : If you want to request a reply from LFS to check the connection
// at any time, you can send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_PING		(request a TINY_REPLY)

// LFS will reply with this IS_TINY :

// ReqI : non-zero		(as received in the request packet)
// SubT : TINY_REPLY	(reply to ping)


// STATE REPORTING AND REQUESTS
// ============================

// LFS will send a StatePack any time the info in the StatePack changes.

class IS_STA extends Struct // STAte
{
	const PACK = 'CCCxfvCCCCCCCCxxa6CC';
	const UNPACK = 'CSize/CType/CReqI/CZero/fReplaySpeed/vFlags/CInGameCam/CViewPLID/CNumP/CNumConns/CNumFinished/CRaceInProg/CQualMins/CRaceLaps/CSpare2/CSpare3/a6Track/CWeather/CWind';

	protected $Size = 28;				# 28
	protected $Type = ISP_STA;			# ISP_STA
	public $ReqI;						# ReqI if replying to a request packet
	protected $Zero;

	public $ReplaySpeed;				# 4-byte float - 1.0 is normal speed

	public $Flags;						# ISS state flags (see below)
	public $InGameCam;					# Which type of camera is selected (see below)
	public $ViewPLID;					# Unique ID of viewed player (0 = none)

	public $NumP;						# Number of players in race
	public $NumConns;					# Number of connections including host
	public $NumFinished;				# Number finished or qualified
	public $RaceInProg;					# 0 - no race / 1 - race / 2 - qualifying

	public $QualMins;
	public $RaceLaps;					# see "RaceLaps" near the top of this document
	protected $Spare2;
	protected $Spare3;

	public $Track;						# short name for track e.g. FE2R
	public $Weather;					# 0,1,2...
	public $Wind;						# 0=off 1=weak 2=strong
}; function IS_STA() { return new IS_STA; }

// InGameCam is the in game selected camera mode (which is
// still selected even if LFS is actually in SHIFT+U mode).
// For InGameCam's values, see "View identifiers" below.

// ISS state flags

define('ISS_GAME',			1);		// in game (or MPR)
define('ISS_REPLAY',		2);		// in SPR
define('ISS_PAUSED',		4);		// paused
define('ISS_SHIFTU',		8);		// SHIFT+U mode
define('ISS_16',			16);	// UNUSED
define('ISS_SHIFTU_FOLLOW',	32);	// FOLLOW view
define('ISS_SHIFTU_NO_OPT',	64);	// SHIFT+U buttons hidden
define('ISS_SHOW_2D',		128);	// showing 2d display
define('ISS_FRONT_END',		256);	// entry screen
define('ISS_MULTI',			512);	// multiplayer mode
define('ISS_MPSPEEDUP',		1024);	// multiplayer speedup option
define('ISS_WINDOWED',		2048);	// LFS is running in a window
define('ISS_SOUND_MUTE',	4096);	// sound is switched off
define('ISS_VIEW_OVERRIDE',	8192);	// override user view
define('ISS_VISIBLE',		16384);	// InSim buttons visible
$ISS = array(ISS_GAME => 'ISS_GAME', ISS_REPLAY => 'ISS_REPLAY', ISS_PAUSED => 'ISS_PAUSED', ISS_SHIFTU => 'ISS_SHIFTU', ISS_16 => 'ISS_16', ISS_SHIFTU_FOLLOW => 'ISS_SHIFTU_FOLLOW', ISS_SHIFTU_NO_OPT => 'ISS_SHIFTU_NO_OPT', ISS_SHOW_2D => 'ISS_SHOW_2D', ISS_FRONT_END => 'ISS_FRONT_END', ISS_MULTI => 'ISS_MULTI', ISS_MPSPEEDUP => 'ISS_MPSPEEDUP', ISS_WINDOWED => 'ISS_WINDOWED', ISS_SOUND_MUTE => 'ISS_SOUND_MUTE', ISS_VIEW_OVERRIDE => 'ISS_VIEW_OVERRIDE', ISS_VISIBLE => 'ISS_VISIBLE');

// To request a StatePack at any time, send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_SST		(Send STate)

// Setting states

// These states can be set by a special packet :

// ISS_SHIFTU_NO_OPT	- SHIFT+U buttons hidden
// ISS_SHOW_2D			- showing 2d display
// ISS_MPSPEEDUP		- multiplayer speedup option
// ISS_SOUND_MUTE		- sound is switched off

class IS_SFP extends Struct // State Flags Pack
{
	const PACK = 'CCCxvCx';
	const UNPACK = 'CSize/CType/CReqI/CZero/vFlag/COffOn/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_SFP;			# ISP_SFP
	protected $ReqI;					# 0
	protected $Zero;

	public $Flag;						# the state to set
	public $OffOn;						# 0 = off / 1 = on
	protected $Sp3;						# spare
}; function IS_SFP() { return new IS_SFP; }

// Other states must be set by using keypresses or messages (see below)


// SCREEN MODE
// ===========

// You can send this packet to LFS to set the screen mode :

class IS_MOD extends Struct // MODe : send to LFS to change screen mode
{
	const PACK = 'CCxxVVVV';
	const UNPACK = 'CSize/CType/CReqI/CZero/VBits16/VRR/VWidth/VHeight';

	protected $Size = 20;				# 20
	protected $Type = ISP_MOD;			# ISP_MOD
	public $ReqI;						# 0
	public $Zero;

	public $Bits16;						# set to choose 16-bit
	public $RR;							# refresh rate - zero for default
	public $Width;						# 0 means go to window
	public $Height;						# 0 means go to window
}; function IS_MOD() { return new IS_MOD; }

// The refresh rate actually selected by LFS will be the highest available rate
// that is less than or equal to the specified refresh rate.  Refresh rate can
// be specified as zero in which case the default refresh rate will be used.

// If Width and Height are both zero, LFS will switch to windowed mode.


// TEXT MESSAGES AND KEY PRESSES
// ==============================

// You can send 64-byte text messages to LFS as if the user had typed them in.
// Messages that appear on LFS screen (up to 128 bytes) are reported to the
// external program.  You can also send simulated keypresses to LFS.

// MESSAGES OUT (FROM LFS)
// ------------

class IS_MSO extends Struct // MSg Out - system messages and user messages
{
	const PACK = 'CCxxCCCCa128';
	const UNPACK = 'CSize/CType/CReqI/CZero/CUCID/CPLID/CUserType/CTextStart/a128Msg';

	protected $Size = 136;				# 136
	protected $Type = ISP_MSO;			# ISP_MSO
	protected $ReqI = null;			# 0
	protected $Zero = null;

	public $UCID = 0;					# connection's unique id (0 = host)
	public $PLID = 0;					# player's unique id (if zero, use UCID)
	public $UserType;					# set if typed by a user (see User Values below)
	public $TextStart;					# first character of the actual text (after player name)

	public $Msg;
}; function IS_MSO() { return new IS_MSO; }

// User Values (for UserType byte)

define('MSO_SYSTEM',	0);		// 0 - system message
define('MSO_USER',		1);		// 1 - normal visible user message
define('MSO_PREFIX',	2);		// 2 - hidden message starting with special prefix (see ISI)
define('MSO_O',			3);		// 3 - hidden message typed on local pc with /o command
define('MSO_NUM',		4);
$MSO = array(MSO_SYSTEM => 'MSO_SYSTEM', MSO_USER => 'MSO_USER', MSO_PREFIX => 'MSO_PREFIX', MSO_O => 'MSO_O', MSO_NUM => 'MSO_NUM');

// NOTE : Typing "/o MESSAGE" into LFS will send an IS_MSO with UserType = MSO_O

class IS_III extends Struct // InsIm Info - /i message from user to host's InSim
{
	const PACK = 'CCxxCCxxa64';
	const UNPACK = 'CSize/CType/CReqI/CZero/CUCID/CPLID/CSp2/CSp3/a64Msg';

	protected $Size = 72;				# 72
	protected $Type = ISP_III;			# ISP_III
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $UCID = 0;					# connection's unique id (0 = host)
	public $PLID = 0;					# player's unique id (if zero, use UCID)
	protected $Sp2 = null;
	protected $Sp3 = null;

	public $Msg;
}; function IS_III() { return new IS_III; }

class IS_ACR extends Struct // Admin Command Report - any user typed an admin command
{
	const PACK = 'CCxxCCxxa64';
	const UNPACK = 'CSize/CType/xReqI/xZero/CUCID/CAdmin/CResult/xSp3/a64Text';

	protected $Size = 72;				# 72
	protected $Type = ISP_ACR;			# ISP_ACR
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $UCID;						# connection's unique id (0 = host)
	public $Admin;						# set if user is an admin
	public $Result;						# 1 - processed / 2 - rejected / 3 - unknown command
	private $Sp3;

	public $Text;
}; function IS_ACR() { return new IS_ACR; }

// MESSAGES IN (TO LFS)
// -----------

class IS_MST extends Struct		// MSg Type - send to LFS to type message or command
{
	const PACK = 'CCxxa64';
	const UNPACK = 'CSize/CType/CReqI/CZero/a64Msg';

	protected $Size = 68;				# 68
	protected $Type = ISP_MST;			# ISP_MST
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $Msg;						# last byte must be zero

	public function pack()
	{
		if (strLen($this->Msg) > 63) {
			foreach(explode("\n", wordwrap($this->Msg, 63, "\n", true)) as $Msg) {
				$this->Msg($Msg)->Send();
			}
            
			return;
		}
        
		return parent::pack();
	}
}; function IS_MST() { return new IS_MST; }

class IS_MSX extends Struct		// MSg eXtended - like MST but longer (not for commands)
{
	const PACK = 'CCxxa96';
	const UNPACK = 'CSize/CType/CReqI/CZero/a96Msg';

	protected $Size = 100;				# 100
	protected $Type = ISP_MSX;			# ISP_MSX
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $Msg;						# last byte must be zero

	public function pack()
	{
		if (strLen($this->Msg) > 95) {
			foreach(explode("\n", wordwrap($this->Msg, 95, "\n", true)) as $Msg) {
				$this->Msg($Msg)->Send();
			}
		}
        
		return parent::pack();
	}
}; function IS_MSX() { return new IS_MSX; }

class IS_MSL extends Struct		// MSg Local - message to appear on local computer only
{
	const PACK = 'CCxCa128';
	const UNPACK = 'CSize/CType/CReqI/CSound/a128Msg';

	protected $Size = 132;				# 132
	protected $Type = ISP_MSL;			# ISP_MSL
	protected $ReqI = 0;				# 0
	public $Sound = SND_SILENT;			# sound effect (see Message Sounds below)

	public $Msg;						# last byte must be zero

	public function pack()
	{
		if (strLen($this->Msg) > 127){
			foreach(explode("\n", wordwrap($this->Msg, 127, "\n", true)) as $Msg) {
				$this->Msg($Msg)->Send();
			}
		}
        
		return parent::pack();
	}
}; function IS_MSL() { return new IS_MSL; }

class IS_MTC extends Struct		// Msg To Connection - hosts only - send to a connection / a player / all
{
	const PACK = 'CCxCCCxxa128';
	const UNPACK = 'CSize/CType/CReqI/CSound/CUCID/CPLID/CSp2/CSp3/a128Text';

	protected $Size = 136;				# 8 + TEXT_SIZE (TEXT_SIZE = 4, 8, 12... 128)
	protected $Type = ISP_MTC;			# ISP_MTC
	protected $ReqI = 0;				# 0
	public $Sound = null;				# sound effect (see Message Sounds below)

	public $UCID = 0;					# connection's unique id (0 = host / 255 = all)
	public $PLID = 0;					# player's unique id (if zero, use UCID)
	protected $Sp2 = null;
	protected $Sp3 = null;

	public $Text;						# up to 128 characters of text - last byte must be zero

	public function pack()
	{
		if (strLen($this->Text) > 127) {
			foreach(explode("\n", wordwrap($this->Text, 127, "\n", true)) as $Text) {
				$this->Text($Text)->Send();
			}
		}
        
		return parent::pack();
	}
}; function IS_MTC() { return new IS_MTC; }

// Message Sounds (for Sound byte)

define('SND_SILENT', 	0);
define('SND_MESSAGE',	1);
define('SND_SYSMESSAGE',2);
define('SND_INVALIDKEY',3);
define('SND_ERROR', 	4);
define('SND_NUM',		5);
$SND = array(SND_SILENT => 'SND_SILENT', SND_MESSAGE => 'SND_MESSAGE', SND_SYSMESSAGE => 'SND_SYSMESSAGE', SND_INVALIDKEY => 'SND_INVALIDKEY', SND_ERROR => 'SND_ERROR', SND_NUM => 'SND_NUM');

// You can send individual key presses to LFS with the IS_SCH packet.
// For standard keys (e.g. V and H) you should send a capital letter.
// This does not work with some keys like F keys, arrows or CTRL keys.
// You can also use IS_MST with the /press /shift /ctrl /alt commands.

class IS_SCH extends Struct		// Single CHaracter
{
	const PACK = 'CCxxCCxx';
	const UNPACK = 'CSize/CType/CReqI/CZero/CCharB/CFlags/CSpare2/CSpare3';

	protected $Size = 8;				# 8
	protected $Type = ISP_SCH;			# ISP_SCH
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $CharB;						# key to press
	public $Flags;						# bit 0 : SHIFT / bit 1 : CTRL
	protected $Spare2 = null;
	protected $Spare3 = null;
}; function IS_SCH() { return new IS_SCH; }


// MULTIPLAYER NOTIFICATION
// ========================

// LFS will send this packet when a host is started or joined :

class IS_ISM extends Struct		// InSim Multi
{
	const PACK = 'CCCxCxxxa32';
	const UNPACK = 'CSize/CType/CReqI/CZero/CHost/CSp1/CSp2/CSp3/a32HName';

	protected $Size = 40;				# 40
	protected $Type = ISP_ISM;			# ISP_ISM
	protected $ReqI = 0;				# usually 0 / or if a reply : ReqI as received in the TINY_ISM
	protected $Zero = null;

	public $Host;						# 0 = guest / 1 = host
	protected $Sp1 = null;
	protected $Sp2 = null;
	protected $Sp3 = null;

	public $HName;						# the name of the host joined or started
}; function IS_ISM() { return new IS_ISM; }

// On ending or leaving a host, LFS will send this IS_TINY :

// ReqI : 0
// SubT : TINY_MPE		(MultiPlayerEnd)

// To request an IS_ISM packet at any time, send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_ISM		(request an IS_ISM)

// NOTE : If LFS is not in multiplayer mode, the host name in the ISM will be empty.


// VOTE NOTIFY AND CANCEL
// ======================

// LFS notifies the external program of any votes to restart or qualify

// The Vote Actions are defined as :

define('VOTE_NONE',		0);		// 0 - no vote
define('VOTE_END',		1);		// 1 - end race
define('VOTE_RESTART',	2);		// 2 - restart
define('VOTE_QUALIFY',	3);		// 3 - qualify
define('VOTE_NUM',		4);
$VOTE = array(VOTE_NONE => 'VOTE_NONE', VOTE_END => 'VOTE_END', VOTE_RESTART => 'VOTE_RESTART', VOTE_QUALIFY => 'VOTE_QUALIFY', VOTE_NUM => 'VOTE_NUM');

class IS_VTN extends Struct		// VoTe Notify
{
	const PACK = 'CCxxCCxx';
	const UNPACK = 'CSize/CType/CReqI/CZero/CUCID/CAction/CSpare2/CSpare3';

	protected $Size = 8;				# 8
	protected $Type = ISP_VTN;			# ISP_VTN
	public $ReqI;						# 0
	protected $Zero;

	public $UCID;						# connection's unique id
	public $Action;						# VOTE_X (Vote Action as defined above)
	protected $Spare2;
	protected $Spare3;
}; function IS_VTN() { return new IS_VTN; }

// When a vote is cancelled, LFS sends this IS_TINY

// ReqI : 0
// SubT : TINY_VTC		(VoTe Cancelled)

// When a vote is completed, LFS sends this IS_SMALL

// ReqI : 0
// SubT : SMALL_VTA  	(VoTe Action)
// UVal : action 		(VOTE_X - Vote Action as defined above)

// You can inStruct LFS host to cancel a vote using an IS_TINY

// ReqI : 0
// SubT : TINY_VTC		(VoTe Cancel)


// ALLOWED CARS
// ============

// You can send a packet to limit the cars that can be used by a given connection
// The resulting set of selectable cars is a subset of the cars set to be available
// on the host (by the /cars command)

// For example :
// Cars = 0          ... no cars can be selected on the specified connection
// Cars = 0xffffffff ... all the host's available cars can be selected

class IS_PLC extends Struct // PLayer Cars
{
	const PACK = 'CCCxCxxxV';
	const UNPACK = 'CSize/CType/CReqI/CZero/CUCID/CSp1/CSp2/CSp3/VCars';

	protected $Size = 12;				# 12
	protected $Type = ISP_PLC;			# ISP_PLC
	public $ReqI;						# 0
	protected $Zero = null;

	public $UCID;						# connection's unique id (0 = host / 255 = all)
	protected $Sp1;
	protected $Sp2;
	protected $Sp3;

	public $Cars;						# allowed cars - see below
}; function IS_PLC() { return new IS_PLC; }

// XF GTI			-       1
// XR GT			-       2
// XR GT TURBO		-       4
// RB4 GT			-       8
// FXO TURBO		-    0x10
// LX4				-    0x20
// LX6				-    0x40
// MRT5				-    0x80
// UF 1000			-   0x100
// RACEABOUT		-   0x200
// FZ50				-   0x400
// FORMULA XR		-   0x800
// XF GTR			-  0x1000
// UF GTR			-  0x2000
// FORMULA V8		-  0x4000
// FXO GTR			-  0x8000
// XR GTR			- 0x10000
// FZ50 GTR			- 0x20000
// BMW SAUBER F1.06	- 0x40000
// FORMULA BMW FB02	- 0x80000


// RACE TRACKING
// =============

// In LFS there is a list of connections AND a list of players in the race
// Some packets are related to connections, some players, some both

// If you are making a multiplayer InSim program, you must maintain two lists
// You should use the unique identifier UCID to identify a connection

// Each player has a unique identifier PLID from the moment he joins the race, until he
// leaves.  It's not possible for PLID and UCID to be the same thing, for two reasons :

// 1) there may be more than one player per connection if AI drivers are used
// 2) a player can swap between connections, in the case of a driver swap (IS_TOC)

// When all players are cleared from race (e.g. /clear) LFS sends this IS_TINY

// ReqI : 0
// SubT : TINY_CLR		(CLear Race)

// When a race ends (return to game setup screen) LFS sends this IS_TINY

// ReqI : 0
// SubT : TINY_REN  	(Race ENd)

// You can inStruct LFS host to cancel a vote using an IS_TINY

// ReqI : 0
// SubT : TINY_VTC		(VoTe Cancel)

// The following packets are sent when the relevant events take place :

class IS_RST extends Struct // Race STart
{
	const PACK = 'CCCxCCCCa6CCvvvvvv';
	const UNPACK = 'CSize/CType/CReqI/CZero/CRaceLaps/CQualMins/CNumP/CTiming/a6Track/CWeather/CWind/vFlags/vNumNodes/vFinish/vSplit1/vSplit2/vSplit3';

	protected $Size = 28;				# 28
	protected $Type = ISP_RST;			# ISP_RST
	public $ReqI = true;				# 0 unless this is a reply to an TINY_RST request
	protected $Zero = null;

	public $RaceLaps;					# 0 if qualifying
	public $QualMins;					# 0 if race
	public $NumP;						# number of players in race
	public $Timing;						# lap timing (see below)

	public $Track;						# short track name
	public $Weather;
	public $Wind;

	public $Flags;						# race flags (must pit, can reset, etc - see below)
	public $NumNodes;					# total number of nodes in the path
	public $Finish;						# node index - finish line
	public $Split1;						# node index - split 1
	public $Split2;						# node index - split 2
	public $Split3;						# node index - split 3
}; function IS_RST() { return new IS_RST; }

// Lap timing info (for Timing byte)

// bits 6 and 7 (Timing & 0xc0) :

// 0x40 : standard timing
// 0x80 : custom timing
// 0xc0 : no lap timing

// bits 0 and 1 (Timing & 0x03) : number of checkpoints if lap timing is enabled

// To request an IS_RST packet at any time, send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_RST		(request an IS_RST)

class IS_NCN extends Struct // New ConN
{
	const PACK = 'CCCCa24a24CCCx';
	const UNPACK = 'CSize/CType/CReqI/CUCID/a24UName/a24PName/CAdmin/CTotal/CFlags/CSp3';

	protected $Size = 56;				# 56
	protected $Type = ISP_NCN;			# ISP_NCN
	public $ReqI = null;				# 0 unless this is a reply to a TINY_NCN request
	public $UCID;						# new connection's unique id (0 = host)

	public $UName;						# username
	public $PName;						# nickname

	public $Admin;						# 1 if admin
	public $Total;						# number of connections including host
	public $Flags;						# bit 2 : remote
	protected $Sp3;
}; function IS_NCN() { return new IS_NCN; }

class IS_CNL extends Struct // ConN Leave
{
	const PACK = 'CCxCCCxx';
	const UNPACK = 'CSize/CType/CReqI/CUCID/CReason/CTotal/CSp2/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_CNL;			# ISP_CNL
	public $ReqI;						# 0
	public $UCID;						# unique id of the connection which left

	public $Reason;						# leave reason (see below)
	public $Total;						# number of connections including host
	protected $Sp2;
	protected $Sp3;
}; function IS_CNL() { return new IS_CNL; }

class IS_CPR extends Struct // Conn Player Rename
{
	const PACK = 'CCxCa24a8';
	const UNPACK = 'CSize/CType/CReqI/CUCID/a24PName/A8Plate';

	protected $Size = 36;				# 36
	protected $Type = ISP_CPR;			# ISP_CPR
	public $ReqI = null;				# 0
	public $UCID;						# unique id of the connection

	public $PName;						# new name
	public $Plate;						# number plate - NO ZERO AT END!
}; function IS_CPR() { return new IS_CPR; }

class IS_NPL extends Struct // New PLayer joining race (if PLID already exists, then leaving pits)
{
	const PACK = 'CCCCCCva24a8a4a16C4CCCCVCCxx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/CUCID/CPType/vFlags/a24PName/A8Plate/a4CName/a16SName/C4Tyres/CH_Mass/CH_TRes/CModel/CPass/VSpare/CSetF/CNumP/CSp2/CSp3';

	protected $Size = 76;				# 76
	protected $Type = ISP_NPL;			# ISP_NPL
	public $ReqI;						# 0 unless this is a reply to an TINY_NPL request
	public $PLID;						# player's newly assigned unique id

	public $UCID;						# connection's unique id
	public $PType;						# bit 0 : female / bit 1 : AI / bit 2 : remote
	public $Flags;						# player flags

	public $PName;						# nickname
	public $Plate;						# number plate - NO ZERO AT END!

	public $CName;						# car name
	public $SName;						# skin name - MAX_CAR_TEX_NAME
	public $Tyres = array();			# compounds

	public $H_Mass;						# added mass (kg)
	public $H_TRes;						# intake restriction
	public $Model;						# driver model
	public $Pass;						# passengers byte

	protected $Spare;

	public $SetF;						# setup flags (see below)
	public $NumP;						# number in race (same when leaving pits, 1 more if new)
	protected $Sp2;
	protected $Sp3;

	public function unpack($rawPacket)
	{
		$pkClass = unpack($this::UNPACK, $rawPacket);

		for ($Tyre = 1; $Tyre <= 4; ++$Tyre) {
			$pkClass['Tyres'][] = $pkClass["Tyres{$Tyre}"];
			unset($pkClass["Tyres{$Tyre}"]);
		}

		foreach ($pkClass as $property => $value) {
			$this->$property = $value;
		}

		return $this;
	}

	public function isFemale() { return ($this->PType & 1); }
	public function isAI() { return ($this->PType & 2); }
	public function isRemote(){ return ($this->PType & 4); }
}; function IS_NPL() { return new IS_NPL; }

// NOTE : PType bit 0 (female) is not reported on dedicated host as humans are not loaded
// You can use the driver model byte instead if required (and to force the use of helmets)

// Setup flags (for SetF byte)

define('SETF_SYMM_WHEELS',	1);
define('SETF_TC_ENABLE',	2);
define('SETF_ABS_ENABLE',	4);
$SETF = array(SETF_SYMM_WHEELS => 'SETF_SYMM_WHEELS', SETF_TC_ENABLE => 'SETF_TC_ENABLE', SETF_ABS_ENABLE => 'SETF_ABS_ENABLE');

// More...

class IS_PLP extends Struct // PLayer Pits (go to settings - stays in player list)
{
	const PACK = 'CCxC';
	const UNPACK = 'CSize/CType/CReqI/CPLID';

	protected $Size = 4;				# 4
	protected $Type = ISP_PLP;			# ISP_PLP
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id
}; function IS_PLP() { return new IS_PLP; }

class IS_PLL extends Struct // PLayer Leave race (spectate - removed from player list)
{
	const PACK = 'CCxC';
	const UNPACK = 'CSize/CType/CReqI/CPLID';

	protected $Size = 4;				# 4
	protected $Type = ISP_PLL;			# ISP_PLL
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id
}; function IS_PLL() { return new IS_PLL; }

class IS_CRS extends Struct // Car ReSet
{
	const PACK = 'CCxC';
	const UNPACK = 'CSize/CType/CReqI/CPLID';

	protected $Size = 4;				# 4
	protected $Type = ISP_CRS;			# ISP_CRS
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id
}; function IS_CRS() { return new IS_CRS; }

class IS_LAP extends Struct // LAP time
{
	const PACK = 'CCxCVVvvxCCx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/VLTime/VETime/vLapsDone/vFlags/CSp0/CPenalty/CNumStops/CSp3';

	protected $Size = 20;				# 20
	protected $Type = ISP_LAP;			# ISP_LAP
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $LTime;						# lap time (ms)
	public $ETime;						# total time (ms)

	public $LapsDone;					# laps completed
	public $Flags;						# player flags

	protected $Sp0;
	public $Penalty;					# current penalty value (see below)
	public $NumStops;					# number of pit stops
	protected $Sp3;
}; function IS_LAP() { return new IS_LAP; }

class IS_SPX extends Struct // SPlit X time
{
	const PACK = 'CCxCVVCCCx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/VSTime/VETime/CSplit/CPenalty/CNumStops/CSp3';

	protected $Size = 16;				# 16
	protected $Type = ISP_SPX;			# ISP_SPX
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id

	public $STime;						# split time (ms)
	public $ETime;						# total time (ms)

	public $Split;						# split number 1, 2, 3
	public $Penalty;					# current penalty value (see below)
	public $NumStops;					# number of pit stops
	protected $Sp3;
}; function IS_SPX() { return new IS_SPX; }

class IS_PIT extends Struct // PIT stop (stop at pit garage)
{
	const PACK = 'CCxCvvxCCxC4VV';
	const UNPACK = 'CSize/CType/CReqI/CPLID/vLapsDone/vFlags/CSp0/CPenalty/CNumStops/CSp3/C4Tyres/VWork/VSpare';

	protected $Size = 24;				# 24
	protected $Type = ISP_PIT;			# ISP_PIT
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id

	public $LapsDone;					# laps completed
	public $Flags;						# player flags

	protected $Sp0;
	public $Penalty;					# current penalty value (see below)
	public $NumStops;					# number of pit stops
	protected $Sp3;

	public $Tyres = array();			# tyres changed

	public $Work;						# pit work
	protected $Spare;

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		for ($Tyre = 1; $Tyre <= 4; ++$Tyre) {
			$Property = "Tyres{$Tyre}";
			$this->Tyres[] = $this->$Property;
			unset($this->$Property);
		}

		return $this;
	}

}; function IS_PIT() { return new IS_PIT; }

class IS_PSF extends Struct // Pit Stop Finished
{
	const PACK = 'CCxCVV';
	const UNPACK = 'CSize/CType/CReqI/CPLID/VSTime/VSpare';

	protected $Size = 12;				# 12
	protected $Type = ISP_PSF;			# ISP_PSF
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $STime;						# stop time (ms)
	protected $Spare;
}; function IS_PSF() { return new IS_PSF; }

class IS_PLA extends Struct // Pit LAne
{
	const PACK = 'CCxCCxxx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/CFact/CSp1/CSp2/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_PLA;			# ISP_PLA
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $Fact;						# pit lane fact (see below)
	protected $Sp1;
	protected $Sp2;
	protected $Sp3;
}; function IS_PLA() { return new IS_PLA; }

// IS_CCH : Camera CHange

// To track cameras you need to consider 3 points

// 1) The default camera : VIEW_DRIVER
// 2) Player flags : CUSTOM_VIEW means VIEW_CUSTOM at start or pit exit
// 3) IS_CCH : sent when an existing driver changes camera

class IS_CCH extends Struct // Camera CHange
{
	const PACK = 'CCxCCxxx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/CCamera/CSp1/CSp2/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_CCH;			# ISP_CCH
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $Camera;						# view identifier (see below)
	protected $Sp1;
	protected $Sp2;
	protected $Sp3;
}; function IS_CCH() { return new IS_CCH; }

class IS_PEN extends Struct // PENalty (given or cleared)
{
	const PACK = 'CCxCCCCx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/COldPen/CNewPen/CReason/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_PEN;			# ISP_PEN
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $OldPen;						# old penalty value (see below)
	public $NewPen;						# new penalty value (see below)
	public $Reason;						# penalty reason (see below)
	protected $Sp3;
}; function IS_PEN() { return new IS_PEN; }

class IS_TOC extends Struct // Take Over Car
{
	const PACK = 'CCxCCCxx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/COldUCID/CNewUCID/CSp2/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_TOC;			# ISP_TOC
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $OldUCID;					# old connection's unique id
	public $NewUCID;					# new connection's unique id
	protected $Sp2;
	protected $Sp3;
}; function IS_TOC() { return new IS_TOC; }

class IS_FLG extends Struct // FLaG (yellow or blue flag changed)
{
	const PACK = 'CCxCCCCx';
	const UNPACK = 'CSize/CType/CReqI/CPLID/COffOn/CFlag/CCarBehind/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_FLG;			# ISP_FLG
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $OffOn;						# 0 = off / 1 = on
	public $Flag;						# 1 = given blue / 2 = causing yellow
	public $CarBehind;					# unique id of obStructed player
	protected $Sp3;
}; function IS_FLG() { return new IS_FLG; }

class IS_PFL extends Struct // Player FLags (help flags changed)
{
	const PACK = 'CCxCvv';
	const UNPACK = 'CSize/CType/CReqI/CPLID/vFlags/vSpare';

	protected $Size = 8;				# 8
	protected $Type = ISP_PFL;			# ISP_PFL
	protected $ReqI;					# 0
	public $PLID;						# player's unique id

	public $Flags;						# player flags (see below)
	protected $Spare;
}; function IS_PFL() { return new IS_PFL; }

class IS_FIN extends Struct // FINished race notification (not a final result - use IS_RES)
{
	const PACK = 'CCxCVVxCCxvv';
	const UNPACK = 'CSize/CType/CReqI/CPLID/VTTime/VBTime/CSpA/CNumStops/CConfirm/CSpB/vLapsDone/vFlags';

	protected $Size = 20;				# 20
	protected $Type = ISP_FIN;			# ISP_FIN
	protected $ReqI;					# 0
	public $PLID;						# player's unique id (0 = player left before result was sent)

	public $TTime;						# race time (ms)
	public $BTime;						# best lap (ms)

	protected $SpA;
	public $NumStops;					# number of pit stops
	public $Confirm;					# confirmation flags : disqualified etc - see below
	protected $SpB;

	public $LapsDone;					# laps completed
	public $Flags;						# player flags : help settings etc - see below
}; function IS_FIN() { return new IS_FIN; }

class IS_RES extends Struct // RESult (qualify or confirmed finish)
{
	const PACK = 'CCxCa24a24a8a4VVxCCxvvCCv';
	const UNPACK = 'CSize/CType/CReqI/CPLID/a24UName/a24PName/A8Plate/a4CName/VTTime/VBTime/CSpA/CNumStops/CConfirm/CSpB/vLapsDone/vFlags/CResultNum/CNumRes/vPSeconds';

	protected $Size = 84;				# 84
	protected $Type = ISP_RES;			# ISP_RES
	public $ReqI;						# 0 unless this is a reply to a TINY_RES request
	public $PLID;						# player's unique id (0 = player left before result was sent)

	public $UName;						# username
	public $PName;						# nickname
	public $Plate;						# number plate - NO ZERO AT END!
	public $CName;						# skin prefix

	public $TTime;						# race time (ms)
	public $BTime;						# best lap (ms)

	protected $SpA;
	public $NumStops;					# number of pit stops
	public $Confirm;					# confirmation flags : disqualified etc - see below
	protected $SpB;

	public $LapsDone;					# laps completed
	public $Flags;						# player flags : help settings etc - see below

	public $ResultNum;					# finish or qualify pos (0 = win / 255 = not added to table)
	public $NumRes;						# total number of results (qualify doesn't always add a new one)
	public $PSeconds;					# penalty time in seconds (already included in race time)
}; function IS_RES() { return new IS_RES; }

// IS_REO : REOrder - this packet can be sent in either direction

// LFS sends one at the start of every race or qualifying session, listing the start order

// You can send one to LFS before a race start, to specify the starting order.
// It may be a good idea to avoid conflict by using /start=fixed (LFS setting).
// Alternatively, you can leave the LFS setting, but make sure you send your IS_REO
// AFTER you receive the SMALL_VTA (VoTe Action).  LFS does its default grid reordering at
// the same time as it sends the SMALL_VTA and you can override this by sending an IS_REO.

class IS_REO extends Struct // REOrder (when race restarts after qualifying)
{
	const PACK = 'CCCCC32';
	const UNPACK = 'CSize/CType/CReqI/CNumP/C32PLID';

	protected $Size = 36;				# 36
	protected $Type = ISP_REO;			# ISP_REO
	public $ReqI;						# 0 unless this is a reply to an TINY_REO request
	public $NumP;						# number of players in race

	public $PLID;						# all PLIDs in new order

	public function unpack($rawPacket)
	{
		$pkClass = unpack($this::UNPACK, $rawPacket);
		$pkClass['PLID'] = array();
        
		for ($Pos = 1; $Pos <= 32; ++$Pos) {
			if ($pkClass["PLID{$Pos}"] != 0) {
				$pkClass['PLID'][$Pos] = $pkClass["PLID{$Pos}"];
			}
			unset($pkClass["PLID{$Pos}"]);
		}

		foreach ($pkClass as $property => $value)
		{
			$this->$property = $value;
		}

		return $this;
	}
}; function IS_REO() { return new IS_REO; }

// To request an IS_REO packet at any time, send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_REO		(request an IS_REO)

// Pit Lane Facts

define('PITLANE_EXIT',		0);	// 0 - left pit lane
define('PITLANE_ENTER',		1);	// 1 - entered pit lane
define('PITLANE_NO_PURPOSE',2);	// 2 - entered for no purpose
define('PITLANE_DT',		3);	// 3 - entered for drive-through
define('PITLANE_SG',		4);	// 4 - entered for stop-go
define('PITLANE_NUM',		5);
$PITLANE = array(PITLANE_EXIT => 'PITLANE_EXIT', PITLANE_ENTER => 'PITLANE_ENTER', PITLANE_NO_PURPOSE => 'PITLANE_NO_PURPOSE', PITLANE_DT => 'PITLANE_DT', PITLANE_SG => 'PITLANE_SG', PITLANE_NUM => 'PITLANE_NUM');

// Pit Work Flags

define('PSE_NOTHING',	(1 << 1));	// bit 0 (1)
define('PSE_STOP',		(1 << 2));	// bit 1 (2)
define('PSE_FR_DAM',	(1 << 3));	// bit 2 (4)
define('PSE_FR_WHL',	(1 << 4));	// etc...
define('PSE_LE_FR_DAM',	(1 << 6));
define('PSE_LE_FR_WHL',	(1 << 7));
define('PSE_RI_FR_DAM', (1 << 8));
define('PSE_RI_FR_WHL',	(1 << 9));
define('PSE_RE_DAM',	(1 << 10));
define('PSE_RE_WHL',	(1 << 11));
define('PSE_LE_RE_DAM',	(1 << 12));
define('PSE_LE_RE_WHL',	(1 << 13));
define('PSE_RI_RE_DAM',	(1 << 14));
define('PSE_RI_RE_WHL',	(1 << 15));
define('PSE_BODY_MINOR',(1 << 16));
define('PSE_BODY_MAJOR',(1 << 17));
define('PSE_SETUP',		(1 << 18));
define('PSE_REFUEL',	(1 << 19));
define('PSE_NUM',		20);
$PSE = array(PSE_NOTHING => 'PSE_NOTHING', PSE_STOP => 'PSE_STOP', PSE_FR_DAM => 'PSE_FR_DAM', PSE_FR_WHL => 'PSE_FR_WHL', PSE_LE_FR_DAM => 'PSE_LE_FR_DAM', PSE_LE_FR_WHL => 'PSE_LE_FR_WHL', PSE_RI_FR_DAM => 'PSE_RI_FR_DAM', PSE_RI_FR_WHL => 'PSE_RI_FR_WHL', PSE_RE_DAM => 'PSE_RE_DAM', PSE_RE_WHL => 'PSE_RE_WHL', PSE_LE_RE_DAM => 'PSE_LE_RE_DAM', PSE_LE_RE_WHL => 'PSE_LE_RE_WHL', PSE_RI_RE_DAM => 'PSE_RI_RE_DAM', PSE_RI_RE_WHL => 'PSE_RI_RE_WHL', PSE_BODY_MINOR => 'PSE_BODY_MINOR', PSE_BODY_MAJOR => 'PSE_BODY_MAJOR', PSE_SETUP => 'PSE_SETUP', PSE_REFUEL => 'PSE_REFUEL', PSE_NUM => 'PSE_NUM');

// View identifiers

define('VIEW_FOLLOW',	0);	// 0 - arcade
define('VIEW_HELI',		1);	// 1 - helicopter
define('VIEW_CAM',		2);	// 2 - tv camera
define('VIEW_DRIVER',	3);	// 3 - cockpit
define('VIEW_CUSTOM',	4);	// 4 - custom
define('VIEW_MAX',		5);
define('VIEW_ANOTHER',255); // viewing another car
$VIEW = array(VIEW_FOLLOW => 'VIEW_FOLLOW', VIEW_HELI => 'VIEW_HELI', VIEW_CAM => 'VIEW_CAM', VIEW_DRIVER => 'VIEW_DRIVER', VIEW_CUSTOM => 'VIEW_CUSTOM', VIEW_MAX => 'VIEW_MAX', VIEW_ANOTHER => 'VIEW_ANOTHER');

// Leave reasons

define('LEAVR_DISCO',	0);	// 0 - disconnect
define('LEAVR_TIMEOUT',	1);	// 1 - timed out
define('LEAVR_LOSTCONN',2);	// 2 - lost connection
define('LEAVR_KICKED',	3);	// 3 - kicked
define('LEAVR_BANNED',	4);	// 4 - banned
define('LEAVR_SECURITY',5);	// 5 - OOS or cheat protection
define('LEAVR_NUM',		6);
$LEAVR = array(LEAVR_DISCO => 'LEAVR_DISCO', LEAVR_TIMEOUT => 'LEAVR_TIMEOUT', LEAVR_LOSTCONN => 'LEAVR_LOSTCONN', LEAVR_KICKED => 'LEAVR_KICKED', LEAVR_BANNED => 'LEAVR_BANNED', LEAVR_SECURITY => 'LEAVR_SECURITY', LEAVR_NUM => 'LEAVR_NUM');

// Penalty values (VALID means the penalty can now be cleared)

define('PENALTY_NONE',		0);	// 0
define('PENALTY_DT',		1);	// 1
define('PENALTY_DT_VALID',	2);	// 2
define('PENALTY_SG',		3);	// 3
define('PENALTY_SG_VALID',	4);	// 4
define('PENALTY_30',		5);	// 5
define('PENALTY_45',		6);	// 6
define('PENALTY_NUM',		7);
$PENALTY = array(PENALTY_NONE => 'PENALTY_NONE', PENALTY_DT => 'PENALTY_DT', PENALTY_DT_VALID => 'PENALTY_DT_VALID', PENALTY_SG => 'PENALTY_SG', PENALTY_SG_VALID => 'PENALTY_SG_VALID', PENALTY_30 => 'PENALTY_30', PENALTY_45 => 'PENALTY_45', PENALTY_NUM => 'PENALTY_NUM');

// Penalty reasons

define('PENR_UNKNOWN',		0);	// 0 - unknown or cleared penalty
define('PENR_ADMIN',		1);	// 1 - penalty given by admin
define('PENR_WRONG_WAY',	2);	// 2 - wrong way driving
define('PENR_FALSE_START',	3);	// 3 - starting before green light
define('PENR_SPEEDING',		4);	// 4 - speeding in pit lane
define('PENR_STOP_SHORT',	5);	// 5 - stop-go pit stop too short
define('PENR_STOP_LATE',	6);	// 6 - compulsory stop is too late
define('PENR_NUM',			7);
$PENR = array(PENR_UNKNOWN => 'PENR_UNKNOWN', PENR_ADMIN => 'PENR_ADMIN', PENR_WRONG_WAY => 'PENR_WRONG_WAY', PENR_FALSE_START => 'PENR_FALSE_START', PENR_SPEEDING => 'PENR_SPEEDING', PENR_STOP_SHORT => 'PENR_STOP_SHORT', PENR_STOP_LATE => 'PENR_STOP_LATE', PENR_NUM => 'PENR_NUM');

// Player flags

define('PIF_SWAPSIDE',		1);
define('PIF_RESERVED_2',	2);
define('PIF_RESERVED_4',	4);
define('PIF_AUTOGEARS',		8);
define('PIF_SHIFTER',		16);
define('PIF_RESERVED_32',	32);
define('PIF_HELP_B',		64);
define('PIF_AXIS_CLUTCH',	128);
define('PIF_INPITS',		256);
define('PIF_AUTOCLUTCH',	512);
define('PIF_MOUSE',			1024);
define('PIF_KB_NO_HELP',	2048);
define('PIF_KB_STABILISED',	4096);
define('PIF_CUSTOM_VIEW',	8192);
$PIF = array(PIF_SWAPSIDE => 'PIF_SWAPSIDE', PIF_RESERVED_2 => 'PIF_RESERVED_2', PIF_RESERVED_4 => 'PIF_RESERVED_4', PIF_AUTOGEARS => 'PIF_AUTOGEARS', PIF_SHIFTER => 'PIF_SHIFTER', PIF_RESERVED_32 => 'PIF_RESERVED_32', PIF_HELP_B => 'PIF_HELP_B', PIF_AXIS_CLUTCH => 'PIF_AXIS_CLUTCH', PIF_INPITS => 'PIF_INPITS', PIF_AUTOCLUTCH => 'PIF_AUTOCLUTCH', PIF_MOUSE => 'PIF_MOUSE', PIF_KB_NO_HELP => 'PIF_KB_NO_HELP', PIF_KB_STABILISED => 'PIF_KB_STABILISED', PIF_CUSTOM_VIEW => 'PIF_CUSTOM_VIEW');

// Tyre compounds (4 byte order : rear L, rear R, front L, front R)

define('TYRE_R1',			0);	// 0
define('TYRE_R2',			1);	// 1
define('TYRE_R3',			2);	// 2
define('TYRE_R4',			3);	// 3
define('TYRE_ROAD_SUPER',	4);	// 4
define('TYRE_ROAD_NORMAL',	5);	// 5
define('TYRE_HYBRID',		6);	// 6
define('TYRE_KNOBBLY',		7);	// 7
define('TYRE_NUM',			8);
define('NOT_CHANGED',		255);
$TYRE = array(TYRE_R1 => 'TYRE_R1', TYRE_R2 => 'TYRE_R2', TYRE_R3 => 'TYRE_R3', TYRE_R4 => 'TYRE_R4', TYRE_ROAD_SUPER => 'TYRE_ROAD_SUPER', TYRE_ROAD_NORMAL => 'TYRE_ROAD_NORMAL', TYRE_HYBRID => 'TYRE_HYBRID', TYRE_KNOBBLY => 'TYRE_KNOBBLY', TYRE_NUM => 'TYRE_NUM', NOT_CHANGED => 'NOT_CHANGED');

// Confirmation flags

define('CONF_MENTIONED',	1);
define('CONF_CONFIRMED',	2);
define('CONF_PENALTY_DT',	4);
define('CONF_PENALTY_SG',	8);
define('CONF_PENALTY_30',	16);
define('CONF_PENALTY_45',	32);
define('CONF_DID_NOT_PIT',	64);
define('CONF_DISQ',	CONF_PENALTY_DT | CONF_PENALTY_SG | CONF_DID_NOT_PIT);
define('CONF_TIME',	CONF_PENALTY_30 | CONF_PENALTY_45);
$CONF = array(CONF_MENTIONED => 'CONF_MENTIONED', CONF_CONFIRMED => 'CONF_CONFIRMED', CONF_PENALTY_DT => 'CONF_PENALTY_DT', CONF_PENALTY_SG => 'CONF_PENALTY_SG', CONF_PENALTY_30 => 'CONF_PENALTY_30', CONF_PENALTY_45 => 'CONF_PENALTY_45', CONF_DID_NOT_PIT => 'CONF_DID_NOT_PIT', CONF_DISQ => 'CONF_DISQ', CONF_TIME => 'CONF_TIME');

// Race flags

define('HOSTF_CAN_VOTE',	1);
define('HOSTF_CAN_SELECT',	2);
define('HOSTF_MID_RACE',	32);
define('HOSTF_MUST_PIT',	64);
define('HOSTF_CAN_RESET',	128);
define('HOSTF_FCV',			256);
define('HOSTF_CRUISE',		512);
$HOSTF = array(HOSTF_CAN_VOTE => 'HOSTF_CAN_VOTE', HOSTF_CAN_SELECT => 'HOSTF_CAN_SELECT', HOSTF_MID_RACE => 'HOSTF_MID_RACE', HOSTF_MUST_PIT => 'HOSTF_MUST_PIT', HOSTF_CAN_RESET => 'HOSTF_CAN_RESET', HOSTF_FCV => 'HOSTF_FCV', HOSTF_CRUISE => 'HOSTF_CRUISE');

// Passengers byte

// bit 0 female
// bit 1 front
// bit 2 female
// bit 3 rear left
// bit 4 female
// bit 5 rear middle
// bit 6 female
// bit 7 rear right


// TRACKING PACKET REQUESTS
// ========================

// To request players, connections, results or a single NLP or MCI, send an IS_TINY

// In each case, ReqI must be non-zero, and will be returned in the reply packet

// SubT : TINT_NCN - request all connections
// SubT : TINY_NPL - request all players
// SubT : TINY_RES - request all results
// SubT : TINY_NLP - request a single IS_NLP
// SubT : TINY_MCI - request a set of IS_MCI


// AUTOCROSS
// =========

// When all objects are cleared from a layout, LFS sends this IS_TINY :

// ReqI : 0
// SubT : TINY_AXC		(AutoX Cleared)

// You can request information about the current layout with this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_AXI		(AutoX Info)

// The information will be sent back in this packet (also sent when a layout is loaded) :

class IS_AXI extends Struct  // AutoX Info
{
	const PACK = 'CCCxCCva32';
	const UNPACK = 'CSize/CType/CReqI/CZero/CAXStart/CNumCP/vNumO/a32LName';

	protected $Size = 40;				# 40
	protected $Type = ISP_AXI;			# ISP_AXI
	public $ReqI;						# 0 unless this is a reply to an TINY_AXI request
	protected $Zero;

	public $AXStart;					# autocross start position
	public $NumCP;						# number of checkpoints
	public $NumO;						# number of objects

	public $LName;						# the name of the layout last loaded (if loaded locally)
}; function IS_AXI() { return new IS_AXI; }

// On false start or wrong route / restricted area, an IS_PEN packet is sent :

// False start : OldPen = 0 / NewPen = PENALTY_30 / Reason = PENR_FALSE_START
// Wrong route : OldPen = 0 / NewPen = PENALTY_45 / Reason = PENR_WRONG_WAY

// If an autocross object is hit (2 second time penalty) this packet is sent :

class IS_AXO extends Struct // AutoX Object
{
	const PACK = 'CCxC';
	const UNPACK = 'CSize/CType/CReqI/CPLID';

	protected $Size = 4;				# 4
	protected $Type = ISP_AXO;			# ISP_AXO
	protected $ReqI;					# 0
	public $PLID;						# player's unique id
}; function IS_AXO() { return new IS_AXO; }


// CAR TRACKING - car position info sent at constant intervals
// ============

// IS_NLP - compact, all cars in 1 variable sized packet
// IS_MCI - detailed, max 8 cars per variable sized packet

// To receive IS_NLP or IS_MCI packets at a specified interval :

// 1) Set the Interval field in the IS_ISI (InSimInit) packet (40, 50, 60... 8000 ms)
// 2) Set one of the flags ISF_NLP or ISF_MCI in the IS_ISI packet

// If ISF_NLP flag is set, one IS_NLP packet is sent...

class NodeLap extends Struct // Car info in 6 bytes - there is an array of these in the NLP (below)
{
	const PACK = 'vvCC';
	const UNPACK = 'vNode/vLap/CPLID/CPosition';

	public $Node;						# current path node
	public $Lap;						# current lap
	public $PLID;						# player's unique id
	public $Position;					# current race position : 0 = unknown, 1 = leader, etc...
};

class IS_NLP extends Struct // Node and Lap Packet - variable size
{
	const PACK = 'CCCC';
	const UNPACK = 'CSize/CType/CReqI/CNumP';

	protected $Size;					# 4 + NumP * 6 (PLUS 2 if needed to make it a multiple of 4)
	protected $Type = ISP_NLP;			# ISP_NLP
	public $ReqI;						# 0 unless this is a reply to an TINY_NLP request
	public $NumP;						# number of players in race

	public $Info = array();				# node and lap of each player, 1 to 32 of these (NumP)

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		for ($i = 0; $i < $this->NumP; $i++) {
			$this->Info[$i] = new NodeLap(substr($rawPacket, 4 + ($i * 6), 6));
		}

		return $this;
	}

}; function IS_NLP() { return new IS_NLP; }

// If ISF_MCI flag is set, a set of IS_MCI packets is sent...

class CompCar extends Struct // Car info in 28 bytes - there is an array of these in the MCI (below)
{
	const PACK = 'vvCCCxVVVvvvs';
	const UNPACK = 'vNode/vLap/CPLID/CPosition/CInfo/CSp3/lX/lY/lZ/vSpeed/vDirection/vHeading/sAngVel';

	public $Node;						# current path node
	public $Lap;						# current lap
	public $PLID;						# player's unique id
	public $Position;					# current race position : 0 = unknown, 1 = leader, etc...
	public $Info;						# flags and other info - see below
	protected $Sp3;
	public $X;							# X map (65536 = 1 metre)
	public $Y;							# Y map (65536 = 1 metre)
	public $Z;							# Z alt (65536 = 1 metre)
	public $Speed;						# speed (32768 = 100 m/s)
	public $Direction;					# car's motion if Speed > 0 : 0 = world y direction, 32768 = 180 deg
	public $Heading;					# direction of forward axis : 0 = world y direction, 32768 = 180 deg
	public $AngVel;						# signed, rate of change of heading : (16384 = 360 deg/s)
};

// NOTE 1) Info byte - the bits in this byte have the following meanings :

define('CCI_BLUE',		1);		// this car is in the way of a driver who is a lap ahead
define('CCI_YELLOW',	2);		// this car is slow or stopped and in a dangerous place
define('CCI_LAG',		32);	// this car is lagging (missing or delayed position packets)
define('CCI_FIRST',		64);	// this is the first compcar in this set of MCI packets
define('CCI_LAST',		128);	// this is the last compcar in this set of MCI packets
$CCI = array(CCI_BLUE => 'CCI_BLUE', CCI_YELLOW => 'CCI_YELLOW', CCI_LAG => 'CCI_LAG', CCI_FIRST => 'CCI_FIRST', CCI_LAST => 'CCI_LAST');

// NOTE 2) Heading : 0 = world y axis direction, 32768 = 180 degrees, anticlockwise from above
// NOTE 3) AngVel  : 0 = no change in heading,    8192 = 180 degrees per second anticlockwise

class IS_MCI extends Struct // Multi Car Info - if more than 8 in race then more than one of these is sent
{
	const PACK = 'CCCC';
	const UNPACK = 'CSize/CType/CReqI/CNumC';

	protected $Size;					# 4 + NumC * 28
	protected $Type = ISP_MCI;			# ISP_MCI
	public $ReqI;						# 0 unless this is a reply to an TINY_MCI request
	public $NumC;						# number of valid CompCar Structs in this packet

	public $Info = array();				# car info for each player, 1 to 8 of these (NumC)

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		for ($i = 0; $i < $this->NumC; $i++) {
			$this->Info[$i] = new CompCar(substr($rawPacket, 4 + ($i * 28), 28));
		}

		return $this;
	}
}; function IS_MCI() { return new IS_MCI; }

// You can change the rate of NLP or MCI after initialisation by sending this IS_SMALL :

// ReqI : 0
// SubT : SMALL_NLI		(Node Lap Interval)
// UVal : interval		(0 means stop, otherwise time interval : 40, 50, 60... 8000 ms)

// CONTACT - reports contacts between two cars if the closing speed is above 0.25 m/s
// =======

class CarContact extends Struct	// Info about one car in a contact - two of these in the IS_CON (below)
{
	const PACK = 'CCCcCCCCCCccss';
	const UNPACK = 'CPLID/CInfo/CSp2/cSteer/CThrBrk/CCluHan/CGearSp/CSpeed/CDirection/CHeading/cAccelF/cAccelR/sX/sY';

	public $PLID;
	public $Info;						# like Info byte in CompCar (CCI_BLUE / CCI_YELLOW / CCI_LAG)
	public $Sp2;						# spare
	public $Steer;						# front wheel steer in degrees (right positive)

	public $ThrBrk;						# high 4 bits : throttle    / low 4 bits : brake (0 to 15)
	public $CluHan;						# high 4 bits : clutch      / low 4 bits : handbrake (0 to 15)
	public $GearSp;						# high 4 bits : gear (15=R) / low 4 bits : spare
	public $Speed;						# m/s

	public $Direction;					# car's motion if Speed > 0 : 0 = world y direction, 128 = 180 deg
	public $Heading;					# direction of forward axis : 0 = world y direction, 128 = 180 deg
	public $AccelF;						# m/s^2 longitudinal acceleration (forward positive)
	public $AccelR;						# m/s^2 lateral acceleration (right positive)

	public $X;							# position (1 metre = 16)
	public $Y;							# position (1 metre = 16)

	public function getPLID()						{ return $this->PLID; }
	public function getInfO()						{ return $this->Info; }

	public function getWheelAngle()					{ return $this->Steer; }

	public function getThrottle()					{ return $this->ThrBrk >> 4; }
	public function getBrake()						{ return $this->ThrBrk & 15; }
	public function getClutch()						{ return $this->CluHan >> 4; }
	public function getHandbrake()					{ return $this->CluHan & 15; }
	public function getGear()						{ return (($Gear = $this->GearSp >> 4) == 15) ? 'R' : $Gear; }

	public function getSpeed()						{ return $this->Speed; }
	public function getDirection()					{ return $this->Direction; }
	public function getHeading()					{ return $this->Heading; }
	public function getAccelerationLongitudinal()	{ return $this->AccelF; }
	public function getAccelerationLateral()		{ return $this->AccelR; }
	public function getX()							{ return $this->X; }
	public function getY()							{ return $this->Y; }

	public function bug()
	{
		printf("%1$08b (%1$-3d)", $this->GearSp >> 4);
	}
};

class IS_CON extends Struct // CONtact - between two cars (A and B are sorted by PLID)
{
	const PACK = 'CCCCvv';
	const UNPACK = 'CSize/CType/CReqI/CZero/vSpClose/vTime';

	protected $Size = 40;				# 40
	protected $Type = ISP_CON;			# ISP_CON
	protected $ReqI = 0;				# 0
	protected $Zero;

	public $SpClose;					# high 4 bits : reserved / low 12 bits : closing speed (10 = 1 m/s)
	public $Time;						# looping time stamp (hundredths - time since reset - like TINY_GTH)

	public $A = array();
	public $B = array();

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		$this->A = new CarContact(substr($rawPacket, 8, 16));
		$this->B = new CarContact(substr($rawPacket, 24, 16));

		return $this;
	}
	
	public function getTime()			{ return $this->Time; }
#	public function getSpare()			{ return $this->SpClose >> 12; }
	public function getClosingSpeed()	{ return ($this->SpClose & 0x0fff) / 10; }
	public function getA()				{ return $this->A; }
	public function getB()				{ return $this->B; }
}; function IS_CON() { return new IS_CON; }

// Set the ISP_OBH flag in the IS_ISI to receive object contact reports

class CarContOBJ extends Struct // 8 bytes : car in a contact with an object
{
	const PACK = 'CCCxss';
	const UNPACK = 'CDirection/CHeading/CSpeed/CSp3/sX/sY';

	public $Direction;					# car's motion if Speed > 0 : 0 = world y direction, 128 = 180 deg
	public $Heading;					# direction of forward axis : 0 = world y direction, 128 = 180 deg
	public $Speed;						# m/s
	protected $Sp3;

	public $X;							# position (1 metre = 16)
	public $Y;							# position (1 metre = 16)
};

class IS_OBH extends Struct // OBject Hit - car hit an autocross object or an unknown object
{
	const PACK = 'CCCCvvx8ssxxCC';
	const UNPACK = 'CSize/CType/CReqI/CPLID/vSpClose/vTime/x8C/sX/sY/xSp0/xSp1/CIndex/COBHFlags';

	protected $Size = 24;				# 24
	protected $Type = ISP_OBH;			# ISP_OBH
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id

	public $SpClose;					# high 4 bits : reserved / low 12 bits : closing speed (10 = 1 m/s)
	public $Time;						# looping time stamp (hundredths - time since reset - like TINY_GTH)

	public $C;

	public $X;							# as in ObjectInfo
	public $Y;							# as in ObjectInfo

	private $Sp0;
	private	$Sp1;
	public $Index;						# AXO_x as in ObjectInfo or zero if it is an unknown object
	public $OBHFlags;					# see below

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		$this->C = new CarContOBJ(substr($rawPacket, 8, 8));

		return $this;
	}
}; function IS_OBH() { return new IS_OBH; }

// OBHFlags byte

define('OBH_LAYOUT',	1);// an added object
define('OBH_CAN_MOVE',	2);// a movable object
define('OBH_WAS_MOVING',4);// was moving before this hit
define('OBH_ON_SPOT',	8);// object in original position
$OBH = array(OBH_LAYOUT => 'OBH_LAYOUT', OBH_CAN_MOVE => 'OBH_CAN_MOVE', OBH_WAS_MOVING => 'OBH_WAS_MOVING', OBH_ON_SPOT => 'OBH_ON_SPOT');

// Set the ISP_HLV flag in the IS_ISI to receive reports of incidents that would violate HLVC

class IS_HLV extends Struct // Hot Lap Validity - illegal ground / hit wall / speeding in pit lane
{
	const PACK = 'CCCCCCvx8';
	const UNPACK = 'CSize/CType/CReqI/CPLID/CHLVC/xSp1/vTime/x8C';

	protected $Size = 16;				# 16
	protected $Type = ISP_HLV;			# ISP_HLV
	protected $ReqI = null;				# 0
	public $PLID;						# player's unique id

	public $HLVC;						# 0 : ground / 1 : wall / 4 : speeding
	private	$Sp1;
	public $Time;						# looping time stamp (hundredths - time since reset - like TINY_GTH)

	public $C;

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		$this->C = new CarContOBJ(substr($rawPacket, 8, 8));

		return $this;
	}
}; function IS_HLV() { return new IS_HLV; }


// AUTOCROSS OBJECTS - reporting / adding / removing
// =================

// Set the ISF_AXM_LOAD flag in the IS_ISI for info about objects when a layout is loaded.
// Set the ISF_AXM_EDIT flag in the IS_ISI for info about objects edited by user or InSim.

// You can also add or remove objects by sending IS_AXM packets.
// Some care must be taken with these - please read the notes below.

class ObjectInfo extends Struct // Info about a single object - explained in the layout file format
{
	const PACK = 'sscCCC';
	const UNPACK = 'sX/sY/CZchar/CFlags/CIndex/CHeading';

	public $X;
	public $Y;
	public $Zchar;
	public $Flags;
	public $Index;
	public $Heading;
};

class IS_AXM extends Struct // AutoX Multiple objects - variable size
{
	const PACK = 'CCCCCCCx';
	const UNPACK = 'CSize/CType/CReqI/CNumO/CUCID/CPMOAction/CPMOFlags/xSp3';

	protected $Size;					# 8 + NumO * 8
	protected $Type = ISP_AXM;			# ISP_AXM
	protected $ReqI = null;				# 0
	public $NumO;						# number of objects in this packet

	public $UCID = 0;					# unique id of the connection that sent the packet
	public $PMOAction;					# see below
	public $PMOFlags;					# see below
	private $Sp3;

	public $Info = array();				# info about each object, 0 to 30 of these

	public function pack()
	{
		$this->NumO = count($this->Info);
		$this->Size = 8 + ($this->NumO * 8);

		$Info = '';
        
		foreach ($this->Info as $ObjectInfo) {
			$Info .= $ObjectInfo->pack();
		}

		return parent::pack() . $Info;
	}

	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		for ($i = 0; $i < $this->NumO; $i++) {
			$this->Info[$i] = new ObjectInfo(substr($rawPacket, 8 + ($i * 8), 8));
		}

		return $this;
	}
}; function IS_AXM() { return new IS_AXM; }

// Values for PMOAction byte

define('PMO_LOADING_FILE',	0);// 0 - sent by the layout loading system only
define('PMO_ADD_OBJECTS',	1);// 1 - adding objects (from InSim or editor)
define('PMO_DEL_OBJECTS',	2);// 2 - delete objects (from InSim or editor)
define('PMO_CLEAR_ALL',		3);// 3 - clear all objects (NumO must be zero)
define('PMO_NUM',			4);
$PMO = array(PMO_LOADING_FILE => 'PMO_LOADING_FILE', PMO_ADD_OBJECTS => 'PMO_ADD_OBJECTS', PMO_DEL_OBJECTS => 'PMO_DEL_OBJECTS', PMO_CLEAR_ALL => 'PMO_CLEAR_ALL', PMO_NUM => 'PMO_NUM');

// Info about the PMOFlags byte (only bit 0 is currently used) :

// If PMOFlags bit 0 is set in a PMO_LOADING_FILE packet, LFS has reached the end of
// a layout file which it is loading.  The added objects will then be optimised.

// Optimised in this case means that static vertex buffers will be created for all
// objects, to greatly improve the frame rate.  The problem with this is that when
// there are many objects loaded, optimisation causes a significant glitch which can
// be long enough to cause a driver who is cornering to lose control and crash.

// PMOFlags bit 0 can also be set in an IS_AXM with PMOAction of PMO_ADD_OBJECTS.
// This causes all objects to be optimised.  It is important not to set bit 0 in
// every packet you send to add objects or you will cause severe glitches on the
// clients computers.  It is ok to have some objects on the track which are not
// optimised.  So if you have a few objects that are being removed and added
// occasionally, the best advice is not to request optimisation at all.  Only
// request optimisation (by setting bit 0) if you have added so many objects
// that it is needed to improve the frame rate.

// NOTE 1) LFS makes sure that all objects are optimised when the race restarts.
// NOTE 2) In the 'more' section of SHIFT+U there is info about optimised objects.

// If you are using InSim to send many packets of objects (for example loading an
// entire layout through InSim) then you must take care of the bandwidth and buffer
// overflows.  You must not try to send all the objects at once.  It's probably good
// to use LFS's method of doing this : send the first packet of objects then wait for
// the corresponding IS_AXM that will be output when the packet is processed.  Then
// you can send the second packet and again wait for the IS_AXM and so on.


// CAR POSITION PACKETS (Initialising OutSim from InSim - See "OutSim" below)
// ====================

// To request Car Positions from the currently viewed car, send this IS_SMALL :

// ReqI : 0
// SubT : SMALL_SSP		(Start Sending Positions)
// UVal : interval		(time between updates - zero means stop sending)

// If OutSim has not been setup in cfg.txt, the SSP packet makes LFS send UDP packets
// if in game, using the OutSim system as documented near the end of this text file.

// You do not need to set any OutSim values in LFS cfg.txt - OutSim is fully
// initialised by the SSP packet.

// The OutSim packets will be sent to the UDP port specified in the InSimInit packet.

// NOTE : OutSim packets are not InSim packets and don't have a 4-byte header.


// DASHBOARD PACKETS (Initialising OutGauge from InSim - See "OutGauge" below)
// =================

// To request Dashboard Packets from the currently viewed car, send this IS_SMALL :

// ReqI : 0
// SubT : SMALL_SSG		(Start Sending Gauges)
// UVal : interval		(time between updates - zero means stop sending)

// If OutGauge has not been setup in cfg.txt, the SSG packet makes LFS send UDP packets
// if in game, using the OutGauge system as documented near the end of this text file.

// You do not need to set any OutGauge values in LFS cfg.txt - OutGauge is fully
// initialised by the SSG packet.

// The OutGauge packets will be sent to the UDP port specified in the InSimInit packet.

// NOTE : OutGauge packets are not InSim packets and don't have a 4-byte header.


// CAMERA CONTROL
// ==============

// IN GAME camera control
// ----------------------

// You can set the viewed car and selected camera directly with a special packet
// These are the states normally set in game by using the TAB and V keys

class IS_SCC extends Struct // Set Car Camera - Simplified camera packet (not SHIFT+U mode)
{
	const PACK = 'CCxxCCxx';
	const UNPACK = 'CSize/CType/CReqI/CZero/CViewPLID/CInGameCam/CSp2/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_SCC;			# ISP_SCC
	protected $ReqI;					# 0
	protected $Zero;

	public $ViewPLID;					# Unique ID of player to view
	public $InGameCam;					# InGameCam (as reported in StatePack)
	protected $Sp2;
	protected $Sp3;
}; function IS_SCC() { return new IS_SCC; }

// NOTE : Set InGameCam or ViewPLID to 255 to leave that option unchanged.

// DIRECT camera control
// ---------------------

// A Camera Position Packet can be used for LFS to report a camera position and state.
// An InSim program can also send one to set LFS camera position in game or SHIFT+U mode.

// Type : "Vec" : 3 ints (X, Y, Z) - 65536 means 1 metre

class IS_CPP extends Struct // Cam Pos Pack - Full camera packet (in car OR SHIFT+U mode)
{
	const PACK = 'CCCxl3vvvCCfvv';
	const UNPACK = 'CSize/CType/CReqI/CZero/l3Pos/vH/vP/vR/CViewPLID/CInGameCam/fFOV/CTime/CFlags';

	protected $Size = 32;				# 32
	protected $Type = ISP_CPP;			# ISP_CPP
	public $ReqI;						# inStruction : 0 / or reply : ReqI as received in the TINY_SCP
	protected $Zero;

	public $Pos;						# Position vector

	public $H;							# heading - 0 points along Y axis
	public $P;							# pitch   - 0 means looking at horizon
	public $R;							# roll    - 0 means no roll

	public $ViewPLID;					# Unique ID of viewed player (0 = none)
	public $InGameCam;					# InGameCam (as reported in StatePack)

	public $FOV;						# 4-byte float : FOV in degrees

	public $Time;						# Time in ms to get there (0 means instant)
	public $Flags;						# ISS state flags (see below)
}; function IS_CPP() { return new IS_CPP; }

// The ISS state flags that can be set are :

// ISS_SHIFTU			- in SHIFT+U mode
// ISS_SHIFTU_FOLLOW	- FOLLOW view
// ISS_VIEW_OVERRIDE	- override user view

// On receiving this packet, LFS will set up the camera to match the values in the packet,
// including switching into or out of SHIFT+U mode depending on the ISS_SHIFTU flag.

// If ISS_VIEW_OVERRIDE is set, the in-car view Heading Pitch and Roll will be taken
// from the values in this packet.  Otherwise normal in game control will be used.

// Position vector (Vec Pos) - in SHIFT+U mode, Pos can be either relative or absolute.

// If ISS_SHIFTU_FOLLOW is set, it's a following camera, so the position is relative to
// the selected car.  Otherwise, the position is absolute, as used in normal SHIFT+U mode.

// NOTE : Set InGameCam or ViewPLID to 255 to leave that option unchanged.

// SMOOTH CAMERA POSITIONING
// --------------------------

// The "Time" value in the packet is used for camera smoothing.  A zero Time means instant
// positioning.  Any other value (milliseconds) will cause the camera to move smoothly to
// the requested position in that time.  This is most useful in SHIFT+U camera modes or
// for smooth changes of internal view when using the ISS_VIEW_OVERRIDE flag.

// NOTE : You can use frequently updated camera positions with a longer Time value than
// the update frequency.  For example, sending a camera position every 100 ms, with a
// Time value of 1000 ms.  LFS will make a smooth motion from the rough inputs.

// If the requested camera mode is different from the one LFS is already in, it cannot
// move smoothly to the new position, so in this case the "Time" value is ignored.

// GETTING A CAMERA PACKET
// -----------------------

// To GET a CamPosPack from LFS, send this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_SCP		(Send Cam Pos)

// LFS will reply with a CamPosPack as described above.  You can store this packet
// and later send back exactly the same packet to LFS and it will try to replicate
// that camera position.


// TIME CONTROL
// ============

// Request the current time at any point with this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_GTH		(Get Time in Hundredths)

// The time will be sent back in this IS_SMALL :

// ReqI : non-zero		(as received in the request packet)
// SubT : SMALL_RTP		(Race Time Packet)
// UVal	: Time			(hundredths of a second since start of race or replay)

// You can stop or start time in LFS and while it is stopped you can send packets to move
// time in steps.  Time steps are specified in hundredths of a second.
// Warning : unlike pausing, this is a "trick" to LFS and the program is unaware of time
// passing so you must not leave it stopped because LFS is unusable in that state.
// This packet is not available in live multiplayer mode.

// Stop and Start with this IS_SMALL :

// ReqI : 0
// SubT : SMALL_TMS		(TiMe Stop)
// UVal	: stop			(1 - stop / 0 - carry on)

// When STOPPED, make time step updates with this IS_SMALL :

// ReqI : 0
// SubT : SMALL_STP		(STeP)
// UVal : number		(number of hundredths of a second to update)


// REPLAY CONTROL
// ==============

// You can load a replay or set the position in a replay with an IS_RIP packet.
// Replay positions and lengths are specified in hundredths of a second.
// LFS will reply with another IS_RIP packet when the request is completed.

class IS_RIP extends Struct // Replay Information Packet
{
	const PACK = 'CCCCCCCxVVa64';
	const UNPACK = 'CSize/CType/CReqI/CError/CMPR/CPaused/COptions/CSp3/VCTime/VTTime/a64RName';

	protected $Size = 80;				# 80
	protected $Type = ISP_RIP;			# ISP_RIP
	public $ReqI;						# request : non-zero / reply : same value returned
	public $Error;						# 0 or 1 = OK / other values are listed below

	public $MPR;						# 0 = SPR / 1 = MPR
	public $Paused;						# request : pause on arrival / reply : paused state
	public $Options;					# various options - see below
	protected $Sp3;

	public $CTime;						# (hundredths) request : destination / reply : position
	public $TTime;						# (hundredths) request : zero / reply : replay length

	public $RName;						# zero or replay name - last byte must be zero
}; function IS_RIP() { return new IS_RIP; }

// NOTE about RName :
// In a request, replay RName will be loaded.  If zero then the current replay is used.
// In a reply, RName is the name of the current replay, or zero if no replay is loaded.

// You can request an IS_RIP packet at any time with this IS_TINY :

// ReqI : non-zero		(returned in the reply)
// SubT : TINY_RIP		(Replay Information Packet)

// Error codes returned in IS_RIP replies :

define('RIP_OK',			0);	//  0 - OK : completed inStruction
define('RIP_ALREADY',		1);	//  1 - OK : already at the destination
define('RIP_DEDICATED',		2);	//  2 - can't run a replay - dedicated host
define('RIP_WRONG_MODE',	3);	//  3 - can't start a replay - not in a suitable mode
define('RIP_NOT_REPLAY',	4);	//  4 - RName is zero but no replay is currently loaded
define('RIP_CORRUPTED',		5);	//  5 - IS_RIP corrupted (e.g. RName does not end with zero)
define('RIP_NOT_FOUND',		6);	//  6 - the replay file was not found
define('RIP_UNLOADABLE',	7);	//  7 - obsolete / future / corrupted
define('RIP_DEST_OOB',		8);	//  8 - destination is beyond replay length
define('RIP_UNKNOWN',		9);	//  9 - unknown error found starting replay
define('RIP_USER',			10);// 10 - replay search was terminated by user
define('RIP_OOS',			10);// 11 - can't reach destination - SPR is out of sync
$RIP = array(RIP_OK => 'RIP_OK', RIP_ALREADY => 'RIP_ALREADY', RIP_DEDICATED => 'RIP_DEDICATED', RIP_WRONG_MODE => 'RIP_WRONG_MODE', RIP_NOT_REPLAY => 'RIP_NOT_REPLAY', RIP_CORRUPTED => 'RIP_CORRUPTED', RIP_NOT_FOUND => 'RIP_NOT_FOUND', RIP_UNLOADABLE => 'RIP_UNLOADABLE', RIP_DEST_OOB => 'RIP_DEST_OOB', RIP_UNKNOWN => 'RIP_UNKNOWN', RIP_USER => 'RIP_USER', RIP_OOS => 'RIP_OOS');

// Options byte : some options

define('RIPOPT_LOOP',	1);	// replay will loop if this bit is set
define('RIPOPT_SKINS',	2);	// set this bit to download missing skins
$RIPOPT = array(RIPOPT_LOOP => 'RIPOPT_LOOP', RIPOPT_SKINS => 'RIPOPT_SKINS');

// SCREENSHOTS
// ===========

// You can instuct LFS to save a screenshot using the IS_SSH packet.
// The screenshot will be saved as an uncompressed BMP in the data\shots folder.
// BMP can be a filename (excluding .bmp) or zero - LFS will create a file name.
// LFS will reply with another IS_SSH when the request is completed.

class IS_SSH extends Struct // ScreenSHot
{
	const PACK = 'CCCCxxxxa32';
	const UNPACK = 'CSize/CType/CReqI/CError/CSp0/CSp1/CSp2/CSp3/a32BMP';

	protected $Size = 40;				# 40
	protected $Type = ISP_SSH;			# ISP_SSH
	public $ReqI;						# request : non-zero / reply : same value returned
	public $Error;						# 0 = OK / other values are listed below

	protected $Sp0;						# 0
	protected $Sp1;						# 0
	protected $Sp2;						# 0
	protected $Sp3;						# 0

	public $BMP;						# name of screenshot file - last byte must be zero
}; function IS_SSH() { return new IS_SSH; }

// Error codes returned in IS_SSH replies :

define('SSH_OK',		0);	//  0 - OK : completed inStruction
define('SSH_DEDICATED',	1);	//  1 - can't save a screenshot - dedicated host
define('SSH_CORRUPTED',	2);	//  2 - IS_SSH corrupted (e.g. BMP does not end with zero)
define('SSH_NO_SAVE',	3);	//  3 - could not save the screenshot
$SSH = array(SSH_OK => 'SSH_OK', SSH_DEDICATED => 'SSH_DEDICATED', SSH_CORRUPTED => 'SSH_CORRUPTED', SSH_NO_SAVE => 'SSH_NO_SAVE');

// BUTTONS
// =======

// You can make up to 240 buttons appear on the host or guests (ID = 0 to 239).
// You should set the ISF_LOCAL flag (in IS_ISI) if your program is not a host control
// system, to make sure your buttons do not conflict with any buttons sent by the host.

// LFS can display normal buttons in these four screens :

// - main entry screen
// - game setup screen
// - in game
// - SHIFT+U mode

// The recommended area for most buttons is defined by :

define('IS_X_MIN',	0);
define('IS_X_MAX',	110);
define('IS_Y_MIN',	30);
define('IS_Y_MAX',	170);
$IS = array(IS_X_MIN => 'IS_X_MIN', IS_X_MAX => 'IS_X_MAX', IS_Y_MIN => 'IS_Y_MIN', IS_Y_MAX => 'IS_Y_MAX');

// If you draw buttons in this area, the area will be kept clear to
// avoid overlapping LFS buttons with your InSim program's buttons.
// Buttons outside that area will not have a space kept clear.
// You can also make buttons visible in all screens - see below.

// To delete one button or clear all buttons, send this packet :

class IS_BFN extends Struct  // Button FunctioN - delete buttons / receive button requests
{
	const PACK = 'CCxCCCCx';
	const UNPACK = 'CSize/CType/CReqI/CSubT/CUCID/CClickID/CInst/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_BFN;			# ISP_BFN
	protected $ReqI;					# 0
	public $SubT;						# subtype, from BFN_ enumeration (see below)

	public $UCID;						# connection to send to or from (0 = local / 255 = all)
	public $ClickID;					# ID of button to delete (if SubT is BFN_DEL_BTN)
	public $Inst;						# used internally by InSim
	protected $Sp3;
}; function IS_BFN() { return new IS_BFN; }

// the fourth byte of IS_BFN packets is one of these
define('BFN_DEL_BTN',	0);	//  0 - inStruction     : delete one button (must set ClickID)
define('BFN_CLEAR',		1);	//  1 - inStruction	    : clear all buttons made by this insim instance
define('BFN_USER_CLEAR',2);	//  2 - info            : user cleared this insim instance's buttons
define('BFN_REQUEST',	3);	//  3 - user request    : SHIFT+B or SHIFT+I - request for buttons
$BFN = array(BFN_DEL_BTN => 'BFN_DEL_BTN', BFN_CLEAR => 'BFN_CLEAR', BFN_USER_CLEAR => 'BFN_USER_CLEAR', BFN_REQUEST => 'BFN_REQUEST');

// NOTE : BFN_REQUEST allows the user to bring up buttons with SHIFT+B or SHIFT+I

// SHIFT+I clears all host buttons if any - or sends a BFN_REQUEST to host instances
// SHIFT+B is the same but for local buttons and local instances

// To send a button to LFS, send this variable sized packet

class IS_BTN extends Struct // BuTtoN - button header - followed by 0 to 240 characters
{
	const PACK = 'CCCCCCCCCCCCa240';
	const UNPACK = 'CSize/CType/CReqI/CUCID/CClickID/CInst/CBStyle/CTypeIn/CL/CT/CW/CH/a240Text';

	protected $Size = 252;				# 12 + TEXT_SIZE (a multiple of 4)
	protected $Type = ISP_BTN;			# ISP_BTN
	public $ReqI = 255;					# non-zero (returned in IS_BTC and IS_BTT packets)
	public $UCID = 255;					# connection to display the button (0 = local / 255 = all)

	public $ClickID = 0;				# button ID (0 to 239)
	public $Inst = null;				# some extra flags - see below
	public $BStyle = null;				# button style flags - see below
	public $TypeIn = null;				# max chars to type in - see below

	public $L = IS_X_MIN;				# left   : 0 - 200
	public $T = IS_Y_MIN;				# top    : 0 - 200
	public $W = 0;						# width  : 0 - 200
	public $H = 0;						# height : 0 - 200

	public $Text = '';					# 0 to 240 characters of text

	public function pack()
	{
		if (strLen($this->Text) > 239) {
			$this->Text = subStr($this->Msg, 0, 239);
		}
        
		return parent::pack();
	}
}; function IS_BTN() { return new IS_BTN; }

// ClickID byte : this value is returned in IS_BTC and IS_BTT packets.

// Host buttons and local buttons are stored separately, so there is no chance of a conflict between
// a host control system and a local system (although the buttons could overlap on screen).

// Programmers of local InSim programs may wish to consider using a configurable button range and
// possibly screen position, in case their users will use more than one local InSim program at once.

// TypeIn byte : if set, the user can click this button to type in text.

// Lowest 7 bits are the maximum number of characters to type in (0 to 95)
// Highest bit (128) can be set to initialise dialog with the button's text

// On clicking the button, a text entry dialog will be opened, allowing the specified number of
// characters to be typed in.  The caption on the text entry dialog is optionally customisable using
// Text in the IS_BTN packet.  If the first character of IS_BTN's Text field is zero, LFS will read
// the caption up to the second zero.  The visible button text then follows that second zero.

// Text : 65-66-67-0 would display button text "ABC" and no caption

// Text : 0-65-66-67-0-68-69-70-71-0-0-0 would display button text "DEFG" and caption "ABC"

// Inst byte : mainly used internally by InSim but also provides some extra user flags

define('INST_ALWAYS_ON',	128);// if this bit is set the button is visible in all screens
$INST = array(INST_ALWAYS_ON => 'INST_ALWAYS_ON');

// NOTE : You should not use INST_ALWAYS_ON for most buttons.  This is a special flag for buttons
// that really must be on in all screens (including the garage and options screens).  You will
// probably need to confine these buttons to the top or bottom edge of the screen, to avoid
// overwriting LFS buttons.  Most buttons should be defined without this flag, and positioned
// in the recommended area so LFS can keep a space clear in the main screens.

// BStyle byte : style flags for the button

define('ISB_C1',		1);			// you can choose a standard
define('ISB_C2',		2);			// interface colour using
define('ISB_C4',		4);			// these 3 lowest bits - see below
define('ISB_CLICK',		8);			// click this button to send IS_BTC
define('ISB_LIGHT',		16);		// light button
define('ISB_DARK',		32);		// dark button
define('ISB_LEFT',		64);		// align text to left
define('ISB_RIGHT',		128);		// align text to right

// colour 0 : light grey		(not user editable)
// colour 1 : title colour		(default:yellow)
// colour 2 : unselected text	(default:black)
// colour 3 : selected text		(default:white)
// colour 4 : ok				(default:green)
// colour 5 : cancel			(default:red)
// colour 6 : text string		(default:pale blue)
// colour 7 : unavailable		(default:grey)

// NOTE : If width or height are zero, this would normally be an invalid button.  But in that case if
// there is an existing button with the same ClickID, all the packet contents are ignored except the
// Text field.  This can be useful for updating the text in a button without knowing its position.
// For example, you might reply to an IS_BTT using an IS_BTN with zero W and H to update the text.

// Replies : If the user clicks on a clickable button, this packet will be sent :

class IS_BTC extends Struct // BuTton Click - sent back when user clicks a button
{
	const PACK = 'CCCCCCCx';
	const UNPACK = 'CSize/CType/CReqI/CUCID/CClickID/CInst/CCFlags/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_BTC;			# ISP_BTC
	public $ReqI;						# ReqI as received in the IS_BTN
	public $UCID;						# connection that clicked the button (zero if local)

	public $ClickID;					# button identifier originally sent in IS_BTN
	public $Inst;						# used internally by InSim
	public $CFlags;						# button click flags - see below
	protected $Sp3;
}; function IS_BTC() { return new IS_BTC; }

// CFlags byte : click flags

define('ISB_LMB',		1);		// left click
define('ISB_RMB',		2);		// right click
define('ISB_CTRL',		4);		// ctrl + click
define('ISB_SHIFT',		8);		// shift + click
$ISB = array(ISB_LMB => 'ISB_LMB', ISB_RMB => 'ISB_RMB', ISB_CTRL => 'ISB_CTRL', ISB_SHIFT => 'ISB_SHIFT');

// If the TypeIn byte is set in IS_BTN the user can type text into the button
// In that case no IS_BTC is sent - an IS_BTT is sent when the user presses ENTER

class IS_BTT extends Struct // BuTton Type - sent back when user types into a text entry button
{
	const PACK = 'CCCCCCCxa96';
	const UNPACK = 'CSize/CType/CReqI/CUCID/CClickID/CInst/CTypeIn/CSp3/a96Text';

	protected $Size = 104;				# 104
	protected $Type = ISP_BTT;			# ISP_BTT
	public $ReqI;						# ReqI as received in the IS_BTN
	public $UCID;						# connection that typed into the button (zero if local)

	public $ClickID;					# button identifier originally sent in IS_BTN
	public $Inst;						# used internally by InSim
	public $TypeIn;						# from original button specification
	protected $Sp3;

	public $Text;						# typed text, zero to TypeIn specified in IS_BTN
}; function IS_BTT() { return new IS_BTT; }


// OutSim - MOTION SIMULATOR SUPPORT
// ======

// The user's car in multiplayer or the viewed car in single player or
// single player replay can output information to a motion system while
// viewed from an internal view.

// This can be controlled by 5 lines in the cfg.txt file :

// OutSim Mode 0        :0-off 1-driving 2-driving+replay
// OutSim Delay 1       :minimum delay between packets (100ths of a sec)
// OutSim IP 0.0.0.0    :IP address to send the UDP packet
// OutSim Port 0        :IP port
// OutSim ID 0          :if not zero, adds an identifier to the packet

// Each update sends the following UDP packet :
class OutSimPack extends Struct
{
	const PACK = 'Vf12V3';
	const UNPACK = 'VTime/f3AngVel/fHeading/fPitch/fRoll/f3Accel/f3Vel/V3Pos';
	const LENGTH = 64;

	public $Time;						# time in milliseconds (to check order)

	public $AngVel;						# 3 floats, angular velocity vector
	public $Heading;					# anticlockwise from above (Z)
	public $Pitch;						# anticlockwise from right (X)
	public $Roll;						# anticlockwise from front (Y)
	public $Accel;						# 3 floats X, Y, Z
	public $Vel;						# 3 floats X, Y, Z
	public $Pos;						# 3 ints   X, Y, Z (1m = 65536)

	public $ID;							# optional - only if OutSim ID is specified

	public function unpack($rawPacket)
	{
		$unpack = (strlen($rawPacket) == self::LENGTH) ? $this::UNPACK : $this::UNPACK . '/VID';
		
		foreach (unpack($unpack, $rawPacket) as $property => $value) {
			$this->$property = $value;
		}

		return $this;
	}
};

// NOTE 1) X and Y axes are on the ground, Z is up.

// NOTE 2) Motion simulators can be dangerous.  The Live for Speed developers do
// not support any motion systems in particular and cannot accept responsibility
// for injuries or deaths connected with the use of such machinery.


// OutGauge - EXTERNAL DASHBOARD SUPPORT
// ========

// The user's car in multiplayer or the viewed car in single player or
// single player replay can output information to a dashboard system
// while viewed from an internal view.

// This can be controlled by 5 lines in the cfg.txt file :

// OutGauge Mode 0        :0-off 1-driving 2-driving+replay
// OutGauge Delay 1       :minimum delay between packets (100ths of a sec)
// OutGauge IP 0.0.0.0    :IP address to send the UDP packet
// OutGauge Port 0        :IP port
// OutGauge ID 0          :if not zero, adds an identifier to the packet

// Each update sends the following UDP packet :

class OutGaugePack extends Struct
{
	const PACK = 'Va4vCxfffffffVVfffa16a16';
	const UNPACK = 'VTime/a4Car/vFlags/CGear/CSpareB/fSpeed/fRPM/fTurbo/fEngTemp/fFuel/fOilPressure/fOilTemp/VDashLights/VShowLights/fThrottle/fBrake/fClutch/a16Display1/a16Display2';
	const LENGTH = 92; # Or 96 with ID.

	public $Time;						# time in milliseconds (to check order)

	public $Car;						# Car name
	public $Flags;						# Info (see OG_x below)
	public $Gear;						# Reverse:0, Neutral:1, First:2...
	protected $SpareB;
	public $Speed;						# M/S
	public $RPM;						# RPM
	public $Turbo;						# BAR
	public $EngTemp;					# C
	public $Fuel;						# 0 to 1
	public $OilPressure;				# BAR
	public $OilTemp;					# C
	public $DashLights;					# Dash lights available (see DL_x below)
	public $ShowLights;					# Dash lights currently switched on
	public $Throttle;					# 0 to 1
	public $Brake;						# 0 to 1
	public $Clutch;						# 0 to 1
	public $Display1;					# Usually Fuel
	public $Display2;					# Usually Settings

	public $ID;							# optional - only if OutGauge ID is specified
	
	public function unpack($rawPacket)
	{
		$unpack = (strlen($rawPacket) == self::LENGTH) ? $this::UNPACK : $this::UNPACK . '/VID';
		
		foreach (unpack($unpack, $rawPacket) as $property => $value) {
			$this->$property = $value;
		}

		return $this;
	}
};

// OG_x - bits for OutGaugePack Flags

define('OG_TURBO',	8192);	// show turbo gauge
define('OG_KM',		16384);	// if not set - user prefers MILES
define('OG_BAR',	32768);	// if not set - user prefers PSI
$OG = array(OG_TURBO => 'OG_TURBO', OG_KM => 'OG_KM', OG_BAR => 'OG_BAR');

// DL_x - bits for OutGaugePack DashLights and ShowLights

define('DL_SHIFT',		(1 << 1));	// bit 0	- shift light
define('DL_FULLBEAM',	(1 << 2));	// bit 1	- full beam
define('DL_HANDBRAKE',	(1 << 3));	// bit 2	- handbrake
define('DL_PITSPEED',	(1 << 4));	// bit 3	- pit speed limiter
define('DL_TC',			(1 << 5));	// bit 4	- TC active or switched off
define('DL_SIGNAL_L',	(1 << 6));	// bit 5	- left turn signal
define('DL_SIGNAL_R',	(1 << 7));	// bit 6	- right turn signal
define('DL_SIGNAL_ANY',	(1 << 8));	// bit 7	- shared turn signal
define('DL_OILWARN',	(1 << 9));	// bit 8	- oil pressure warning
define('DL_BATTERY',	(1 << 10));	// bit 9	- battery warning
define('DL_ABS',		(1 << 11));	// bit 10	- ABS active or switched off
define('DL_SPARE',		(1 << 12));	// bit 11
define('DL_NUM',		13);
$DL = array(DL_SHIFT => 'DL_SHIFT', DL_FULLBEAM => 'DL_FULLBEAM', DL_HANDBRAKE => 'DL_HANDBRAKE', DL_PITSPEED => 'DL_PITSPEED', DL_TC => 'DL_TC', DL_SIGNAL_L => 'DL_SIGNAL_L', DL_SIGNAL_R => 'DL_SIGNAL_R', DL_SIGNAL_ANY => 'DL_SIGNAL_ANY', DL_OILWARN => 'DL_OILWARN', DL_BATTERY => 'DL_BATTERY', DL_ABS => 'DL_ABS', DL_SPARE => 'DL_SPARE', DL_NUM => 'DL_NUM');

//////
#endif

// InSimRelay for LFS InSim version 4 (LFS 0.5X and up)
//
// The Relay code below can be seen as an extension to the regular
// InSim protocol, as the packets are conStructed in the same
// manner as regular InSim packets.
//
// Connect your client to isrelay.lfs.net:47474 with TCP
// After you are connected you can request a hostlist, so you can see
// which hosts you can connect to.
// Then you can send a packet to the Relay to select a host. After that
// the Relay will send you all insim data from that host.

// Some hosts require a spectator password in order to be selectable.

// You do not need to specify a spectator password if you use a valid administrator password.

// If you connect with an administrator password, you can send just about every
// regular InSim packet there is available in LFS, just like as if you were connected
// to the host directly. For a full list, see end of document.




// Packet types used for the Relay

define('IRP_ARQ',	250);	// Send : request if we are host admin (after connecting to a host)
define('IRP_ARP',	251);	// Receive : replies if you are admin (after connecting to a host)
define('IRP_HLR',	252);	// Send : To request a hostlist
define('IRP_HOS',	253);	// Receive : Hostlist info
define('IRP_SEL',	254);	// Send : To select a host
define('IRP_ERR',	255);	// Receive : An error number
$IRP = array(IRP_ARQ => 'IRP_ARQ', IRP_ARP => 'IRP_ARP', IRP_HLR => 'IRP_HLR', IRP_HOS => 'IRP_HOS', IRP_SEL => 'IRP_SEL', IRP_ERR => 'IRP_ERR');

// To request a hostlist from the Relay, send this packet :

class IR_HLR extends Struct // HostList Request
{
	const PACK = 'CCCx';
	const UNPACK = 'CSize/CType/CReqI/CSp0';

	protected $Size = 4;				# 4
	protected $Type = IRP_HLR;			# IRP_HLR
	public $ReqI;
	protected $Sp0;
}; function IR_HLR() { return new IR_HLR; }


// That will return (multiple) packets containing hostnames and some information about them

// The following Struct is a subpacket of the IR_HOS packet

class HInfo // Sub packet for IR_HOS. Contains host information
{
	const PACK = 'a32a6CC';
	const UNPACK = 'a32HName/a6Track/CFlags/CNumConns';

	public $HName;						# Name of the host

	public $Track;						# Short track name
	public $Flags;						# Info flags about the host - see NOTE 1) below
	public $NumConns;					# Number of people on the host
};

// NOTE 1)

define('HOS_SPECPASS',		1);		// Host requires a spectator password
define('HOS_LICENSED',		2);		// Bit is set if host is licensed
define('HOS_S1',			4);		// Bit is set if host is S1
define('HOS_S2',			8);		// Bit is set if host is S2
define('HOS_FIRST',			64);	// Indicates the first host in the list
define('HOS_LAST',			128);	// Indicates the last host in the list
$HOS = array(HOS_SPECPASS => 'HOS_SPECPASS', HOS_LICENSED => 'HOS_LICENSED', HOS_S1 => 'HOS_S1', HOS_S2 => 'HOS_S2', HOS_FIRST => 'HOS_FIRST', HOS_LAST => 'HOS_LAST');


class IR_HOS extends Struct // Hostlist (hosts connected to the Relay)
{
	const PACK = 'CCCCa6';
	const UNPACK = 'CSize/CType/CReqI/CNumHosts/a6Info';

	protected $Size;					# 4 + NumHosts * 40
	protected $Type = IRP_HOS;			# IRP_HOS
	public $ReqI;						# As given in IR_HLR
	public $NumHosts;					# Number of hosts described in this packet

	public $Info = array();				# Host info for every host in the Relay. 1 to 6 of these in a IR_HOS



	public function unpack($rawPacket)
	{
		parent::unpack($rawPacket);

		for ($i = 0; $i < $this->NumHosts; $i++) {
			$this->Info[$i] = new HInfo(substr($rawPacket, 4 + ($i * 40), 40));
		}

		return $this;
	}
}; function IR_HOS() { return new IR_HOS; }


// To select a host in the Relay, send this packet :

class IR_SEL extends Struct // Relay select - packet to select a host, so relay starts sending you data.
{
	const PACK = 'CCCxa32a16a16';
	const UNPACK = 'CSize/CType/CReqI/CZero/a32HName/a16Admin/a16Spec';

	protected $Size = 68;				# 68
	protected $Type = IRP_SEL;			# IRP_SEL
	public $ReqI;						# If non-zero Relay will reply with an IS_VER packet
	protected $Zero;					# 0

	public $HName;						# Hostname to receive data from - may be colourcode stripped
	public $Admin;						# Admin password (to gain admin access to host)
	public $Spec;						# Spectator password (if host requires it)

}; function IR_SEL() { return new IR_SEL; }


// To request if we are an admin send:

class IR_ARQ extends Struct // Admin Request
{
	const PACK = 'CCCx';
	const UNPACK = 'CSize/CType/CReqI/CSp0';

	protected $Size = 4;				# 4
	protected $Type = IRP_ARQ;			# IRP_ARQ
	public $ReqI;
	protected $Sp0;
}; function IR_ARQ() { return new IR_ARQ; }


// Relay will reply to admin status request :

class IR_ARP extends Struct // Admin Response
{
	const PACK = 'CCCC';
	const UNPACK = 'CSize/CType/CReqI/CAdmin';

	protected $Size = 4;				# 4
	protected $Type = IRP_ARP;			# IRP_ARP
	public $ReqI;
	public $Admin;						# 0- no admin; 1- admin
}; function IR_ARP() { return new IR_ARP; }


// If you specify a wrong value, like invalid packet / hostname / adminpass / specpass,
// the Relay returns an error packet :
class IR_ERR extends Struct
{
	const PACK = 'CCCC';
	const UNPACK = 'CSize/CType/CReqI/CErrNo';

	protected $Size = 4;				# 4
	protected $Type = IRP_ERR;			# IRP_ERR
	public $ReqI;						# As given in RL_SEL, otherwise 0
	public $ErrNo;						# Error number - see NOTE 2) below
}; function IR_ERR() { return new IR_ERR; }

// NOTE 2) Error numbers :

define('IR_ERR_PACKET',		1);	// Invalid packet sent by client (wrong Structure / length)
define('IR_ERR_PACKET2',	2);	// Invalid packet sent by client (packet was not allowed to be forwarded to host)
define('IR_ERR_HOSTNAME',	3);	// Wrong hostname given by client
define('IR_ERR_ADMIN',		4);	// Wrong admin pass given by client
define('IR_ERR_SPEC',		5);	// Wrong spec pass given by client
define('IR_ERR_NOSPEC',		6);	// Spectator pass required, but none given
$IR = array(IR_ERR_PACKET => 'IR_ERR_PACKET', IR_ERR_PACKET2 => 'IR_ERR_PACKET2', IR_ERR_HOSTNAME => 'IR_ERR_HOSTNAME', IR_ERR_ADMIN => 'IR_ERR_ADMIN', IR_ERR_SPEC => 'IR_ERR_SPEC', IR_ERR_NOSPEC => 'IR_ERR_NOSPEC');

/*
==============================================
Regular insim packets that a relay client can send to host :

For anyone
TINY_VER
TINY_PING
TINY_SCP
TINY_SST
TINY_GTH
TINY_ISM
TINY_NCN
TINY_NPL
TINY_RES
TINY_REO
TINY_RST
TINY_AXI

Admin only
TINY_VTC
ISP_MST
ISP_MSX
ISP_MSL
ISP_MTC
ISP_SCH
ISP_BFN
ISP_BTN

The relay will also accept, but not forward
TINY_NONE    // for relay-connection maintenance
*/

/* Start of PRISM PACKET FOOTER */
define('OSP',	-1);// -1 - info			: OutSimPacket - MOTION SIMULATOR SUPPORT
define('OGP',	-2);// -2 - info			: OutGauge - EXTERNAL DASHBOARD SUPPORT
$SPECIAL = array(OSP => 'OutSimPack', OGP => 'OutGaugePack');
/* Packet Handler Help */
$TYPEs = $ISP + $IRP;
foreach ($TYPEs as $Type => $Name) {
	$TYPEs[$Type] = substr_replace($Name, '', 2, 1);
}
$TYPEs = $SPECIAL + $TYPEs;
/* End of PRISM PACKET FOOTER */
