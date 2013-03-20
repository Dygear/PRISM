<?php

/**
 * Properties are private by design.
 * You should never edit an object. Just create a new one as you need to remove the old object and add the new one.
 */
class LayoutObject {
	const UNPACK = 'sX/sY/CZ/CFlags/CIndex/CHeading';
	const PACK = 'ssCCCC';

	private $x;
	private $y;
	private $z;
	
	private $hdng;
	
	private $index = -1;
	private $type;
	private $typeData = 0; /** object index / circle radius / checkpoint half-width */
	private $colour = 0;
	
	public static $OBJ_REAL_OBJECT		= 'object';
	public static $OBJ_RESTRICTED		= 'restricted_area';
	public static $OBJ_MARSHALL			= 'marshall';
	public static $OBJ_MARSHALL_LEFT	= 'marshall_left';
	public static $OBJ_MARSHALL_RIGHT	= 'marshall_right';
	public static $OBJ_ROUTE_CHECK		= 'route_check';
	public static $OBJ_START			= 'start';
	public static $OBJ_CHECK_1			= 'check_1';
	public static $OBJ_CHECK_2			= 'check_2';
	public static $OBJ_CHECK_3			= 'check_3';
	public static $OBJ_FINISH			= 'finish';
	
	/**
	 * Constructor.
	 * Either pass an 8 Byte data struture provided by insim or in the layout file or all other params as well.
	 * @param dataOrX 8 byte data or value for X coordinate
	 * @param y Y coordinate
	 * @param z Z coordinate
	 * @param type one of LayoutObject::$OBJ_... values
	 * @param hdng heading in degree or route index
	 * @param typeData object index, circle radius or half-width of control point
	 * @param colour Colour for chalk and tyre stacks
	 */
	public function __construct($dataOrX, $y = null, $z = null, $type = null, $hdng = null, $typeData = null, $colour = null) {
		if ($type != null)
		{
			$this->x = $dataOrX;
			$this->y = $y;
			$this->z = $z;
			$this->type = $type;
			if ($this->type == self::$OBJ_ROUTE_CHECK)
			{
				$this->setRouteIndex($hdng);
			}
			else
			{
				$this->setHeading($hdng);
			}
			$this->typeData = $typeData;
			$this->colour = $colour;
		}
		else {
			$this->initFromBytes($dataOrX);
		}
	}
	
	public function __toString() {
		$retVal = $this->type;
		
		if ($this->type == self::$OBJ_REAL_OBJECT)
		{
			$retVal .= ': ' . self::$REV_AXO[$this->typeData];
			if ($this->typeData >= self::$AXO_CHALK_LINE && $this->typeData <= self::$AXO_CHALK_RIGHT3)
			{
				$retVal .= ', col:' . self::$REV_CC[$this->colour];
			}
			else if ($this->typeData >= self::$AXO_TYRE_SINGLE && $this->typeData <= self::$AXO_TYRE_STACK4_BIG)
			{
				$retVal .= ', col:' . self::$REV_TC[$this->colour];
			}
		}
		return $retVal;
	}
	
	public function pack() {
		$x = $this->rawX();
		$y = $this->rawY();
		$z = $this->z * 4;
		
		$dat = $this->dataForType();
		$flags = $dat['flags'];
		$index = $dat['index'];
		$heading = $dat['heading'];
		
		return pack(LayoutObject::PACK, $x, $y, $z, $flags, $index, $heading);
	}
	
	public function rawX()
	{
		return round($this->x * 16);
	}
	
	public function rawY()
	{
		return round($this->y * 16);
	}
	
	public function initFromBytes($data) {
	$obj = unpack(LayoutObject::UNPACK, $data);
		// scale ...
		$this->x = $obj['X'] / 16;
		$this->y = $obj['Y'] / 16;
		$this->z = $obj['Z'] / 4;
		$this->setHeading($obj['Heading'] / 256 * 360);
		
		$ix = $obj['Index'];
		$this->index = $ix;
		$flg = $obj['Flags'];
		
		if ($ix >= 192) // either a circle or an unknown object
		{
			if ($ix==255) // it's a marshall circle
			{
				if (($flg & 0x80) != 0) // highest bit set : restricted area
				{
					// HeadingByte has the same meaning as it does for all objects
					// Flags byte contains the following data :
					// bits 0 to 1 :
					switch ($flg & 0x03) {
						// 00 = no marshall
						case 0:
							$this->type = self::$OBJ_RESTRICTED;
							break;
						// 01 = standing marshall
						case 1:
							$this->type = self::$OBJ_MARSHALL;
							break;
						// 10 = marshall pointing left
						case 2:
							$this->type = self::$OBJ_MARSHALL_LEFT;
							break;
						// 11 = marshall pointing right
						case 3:
							$this->type = self::$OBJ_MARSHALL_RIGHT;
							break;
					}
					// bits 2 to 5 :
					// circle diameter in metres (shifted left by 2 bits)
					$this->typeData = ($flg >> 2) & 0x07;
				}
				else // highest bit of flags is not set : route checker
				{
					$this->type = self::$OBJ_ROUTE_CHECK;
					// HeadingByte is used not for heading, but the route index
					// bits 2 to 5 :
					// circle diameter in metres (shifted left by 2 bits)
					$this->typeData = ($flg >> 2) & 0x0F;
					$this->hdng = $obj['Heading'];
				}
			}
			else
			{
				// unknown object - ignore
				$this->type = array($flg, $ix);
			}
		}
		else // could be an actual object or a control object
		{
			if(($flg & 0x80) != 0) // highest bit set : control object
			// bit 6 : never set
			// bit 7 : always set (0x80)
			{
				// bits 2 to 5 :
				// Checkpoint width(half width!) in metres (shifted left by 2 bits)
				$this->typeData = (($flg >> 2) & 0x07);
				// Flags byte contains the following data :
				// bits 0 to 1 :
				switch ($flg & 0x03) {
					// 00 = Start position (if width = 0) or finish line (if width > 0)
					case 0:
						if ($this->typeData == 0) {
							$this->type = self::$OBJ_START;	
						}
						else {
							$this->type = self::$OBJ_FINISH;
						}
						break;
					// 01 = Checkpoint 1
					case 1:
						$this->type = self::$OBJ_CHECK_1;
						break;
					// 10 = Checkpoint 2
					case 2:
						$this->type = self::$OBJ_CHECK_2;
						break;
					// 11 = Checkpoint 3
					case 3:
						$this->type = self::$OBJ_CHECK_3;
						break;
				}
			}
			else // highest bit of flags is not set : autocross object
			{
				$this->type = self::$OBJ_REAL_OBJECT;
				// save flag as colour (even if it's just tyres and chalk)
				echo 'flag is:'.$flg;
				$this->colour = $flg;
				$this->typeData = $ix;
			}
		}
	}
	
	/**
	 * Computes the index flag and heading fields for the current object.
	 * @return array Array containing the value for 'flags', 'index' and 'heading'
	 */
	public function dataForType() {
		$dat = array();
		
		switch($this->type) {
			case self::$OBJ_RESTRICTED:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x80;
				$dat['index'] = 255;
				break;
			case self::$OBJ_MARSHALL:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x81;
				$dat['index'] = 255;
				break;
			case self::$OBJ_MARSHALL_LEFT:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x82;
				$dat['index'] = 255;
				break;
			case self::$OBJ_MARSHALL_RIGHT:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x83;
				$dat['index'] = 255;
				break;
			case self::$OBJ_ROUTE_CHECK:
				$dat['flags'] = $this->typeData << 2 & 0x3C;
				$dat['index'] = 255;
				$dat['heading'] = $this->hdng;
				break;
			
			case self::$OBJ_REAL_OBJECT:
				$dat['flags'] = $this->colour;
				$dat['index'] = $this->typeData;
				break;
			case self::$OBJ_START:
				$dat['flags'] = 0x80;
				$dat['index'] = 0;
				break;
			case self::$OBJ_CHECK_1:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x81;
				$dat['index'] = 0;
				break;
			case self::$OBJ_CHECK_2:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x82;
				$dat['index'] = 0;
				break;
			case self::$OBJ_CHECK_3:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x83;
				$dat['index'] = 0;
				break;
			case self::$OBJ_FINISH:
				$dat['flags'] = $this->typeData << 2 & 0x3C | 0x80;
				$dat['index'] = 0;
				break;
			
			default:
				// unknown objects...
				if (is_array($this->type)) {
					$dat['flags'] = $this->type[0];
					$dat['index'] = $this->type[1];
				}
		}
		
		// default heading calculation
		if (!isset($dat['heading'])) {
			$dat['heading'] = ($this->hdng/* + 180*/) * 256 / 360;
		}
		
		return $dat;
	}
	
	/**
	 * Set the heading in deg.
	 * Normalizes the heading to use the lfs notation between -180° and 180°.
	 */
	private function setHeading($val) {
		if ($this->type == self::$OBJ_ROUTE_CHECK) {
			throw new Exception('Trying to set heading on route checker object. Use setRouteIndex instead.');
		}
		
		if (!is_numeric($val)) {
			throw new Exception('Invalid heading index.');
		}
		
		// val must be between -180 and 180
		$deg = $val;
		while ($deg < -179) {
			$deg += 360;
		}
		while ($deg > 180) {
			$deg -= 360;
		}
		
		$this->hdng = $deg;
	}
	
	/**
	 * Set the route index if the object is a route check.
	 */
	private function setRouteIndex($val) {
		if ($this->type != self::$OBJ_ROUTE_CHECK) {
			throw new Exception('Trying to set route index on a non route checker object. Use setHeading instead.');
		}
		
		if (!is_int($val) || $val < 0 || $val > 90) { // TODO check what's the actual max value
			throw new Exception('Invalid route index.');
		}
		
		$this->hdng = $val;
	}
	
	public function x()
	{
		return $this->x;
	}
	public function setX($x)
	{
		$this->x = $x;
	}
	
	public function y()
	{
		return $this->y;
	}
	public function setY($y)
	{
		$this->y = $y;;
	}
	public function z()
	{
		return $this->z;
	}
	public function heading()
	{
		return $this->hdng;
	}
	public function type()
	{
		return $this->type;
	}
	public function typeData()
	{
		return $this->typeData;
	}
	public function colour()
	{
		return $this->colour;
	}
	public function index()
	{
		if ($this->index == -1)
		{
			$dat = $this->dataForType();
			$this->index = $dat['index'];
		}
		
		return $this->index;
	}

	public static $AXO_CHALK_LINE = 4;
	public static $AXO_CHALK_LINE2 = 5;
	public static $AXO_CHALK_AHEAD = 6;
	public static $AXO_CHALK_AHEAD2 = 7;
	public static $AXO_CHALK_LEFT = 8;
	public static $AXO_CHALK_LEFT2 = 9;
	public static $AXO_CHALK_LEFT3 = 10;
	public static $AXO_CHALK_RIGHT = 11;
	public static $AXO_CHALK_RIGHT2 = 12;
	public static $AXO_CHALK_RIGHT3 = 13;
	public static $AXO_14 = 14;
	public static $AXO_15 = 15;
	public static $AXO_16 = 16;
	public static $AXO_17 = 17;
	public static $AXO_18 = 18;
	public static $AXO_19 = 19;
	public static $AXO_CONE_RED = 20;
	public static $AXO_CONE_RED2 = 20;
	public static $AXO_CONE_RED3 = 22;
	public static $AXO_CONE_BLUE = 23;
	public static $AXO_CONE_BLUE2 = 24;
	public static $AXO_CONE_GREEN = 25;
	public static $AXO_CONE_GREEN2 = 26;
	public static $AXO_CONE_ORANGE = 27;
	public static $AXO_CONE_WHITE = 28;
	public static $AXO_CONE_YELLOW = 29;
	public static $AXO_CONE_YELLOW2 = 30;
	public static $AXO_31 = 31;
	public static $AXO_32 = 32;
	public static $AXO_33 = 33;
	public static $AXO_34 = 34;
	public static $AXO_35 = 35;
	public static $AXO_36 = 36;
	public static $AXO_37 = 37;
	public static $AXO_38 = 38;
	public static $AXO_39 = 39;
	public static $AXO_CONE_PTR_RED = 40;
	public static $AXO_CONE_PTR_BLUE = 41;
	public static $AXO_CONE_PTR_GREEN = 42;
	public static $AXO_CONE_PTR_YELLOW = 43;
	public static $AXO_44 = 44;
	public static $AXO_45 = 45;
	public static $AXO_46 = 46;
	public static $AXO_47 = 47;
	public static $AXO_TYRE_SINGLE = 48;
	public static $AXO_TYRE_STACK2 = 49;
	public static $AXO_TYRE_STACK3 = 50;
	public static $AXO_TYRE_STACK4 = 51;
	public static $AXO_TYRE_SINGLE_BIG = 52;
	public static $AXO_TYRE_STACK2_BIG = 53;
	public static $AXO_TYRE_STACK3_BIG = 54;
	public static $AXO_TYRE_STACK4_BIG = 55;
	public static $AXO_56 = 56;
	public static $AXO_57 = 57;
	public static $AXO_58 = 58;
	public static $AXO_59 = 59;
	public static $AXO_60 = 60;
	public static $AXO_61 = 61;
	public static $AXO_62 = 62;
	public static $AXO_63 = 63;
	public static $AXO_MARKER_CURVE_L = 64;
	public static $AXO_MARKER_CURVE_R = 65;
	public static $AXO_MARKER_L = 66;
	public static $AXO_MARKER_R = 67;
	public static $AXO_MARKER_HARD_L = 68;
	public static $AXO_MARKER_HARD_R = 69;
	public static $AXO_MARKER_L_R = 70;
	public static $AXO_MARKER_R_L = 71;
	public static $AXO_MARKER_S_L = 72;
	public static $AXO_MARKER_S_R = 73;
	public static $AXO_MARKER_S2_L = 74;
	public static $AXO_MARKER_S2_R = 75;
	public static $AXO_MARKER_U_L = 76;
	public static $AXO_MARKER_U_R = 77;
	public static $AXO_78 = 78;
	public static $AXO_79 = 79;
	public static $AXO_80 = 80;
	public static $AXO_81 = 81;
	public static $AXO_82 = 82;
	public static $AXO_83 = 83;
	public static $AXO_DIST25 = 84;
	public static $AXO_DIST50 = 85;
	public static $AXO_DIST75 = 86;
	public static $AXO_DIST100 = 87;
	public static $AXO_DIST125 = 88;
	public static $AXO_DIST150 = 89;
	public static $AXO_DIST200 = 90;
	public static $AXO_DIST250 = 91;
	public static $AXO_92 = 92;
	public static $AXO_93 = 93;
	public static $AXO_94 = 94;
	public static $AXO_95 = 95;
	public static $AXO_ARMCO1 = 96;
	public static $AXO_ARMCO3 = 97;
	public static $AXO_ARMCO5 = 98;
	public static $AXO_99 = 99;
	public static $AXO_100 = 100;
	public static $AXO_101 = 101;
	public static $AXO_102 = 102;
	public static $AXO_103 = 103;
	public static $AXO_BARRIER_LONG = 104;
	public static $AXO_BARRIER_RED = 105;
	public static $AXO_BARRIER_WHITE = 106;
	public static $AXO_107 = 107;
	public static $AXO_108 = 108;
	public static $AXO_109 = 109;
	public static $AXO_110 = 110;
	public static $AXO_111 = 111;
	public static $AXO_BANNER1 = 112;
	public static $AXO_BANNER2 = 113;
	public static $AXO_114 = 114;
	public static $AXO_115 = 115;
	public static $AXO_116 = 116;
	public static $AXO_117 = 117;
	public static $AXO_118 = 118;
	public static $AXO_119 = 119;
	public static $AXO_RAMP1 = 114;
	public static $AXO_RAMP2 = 115;
	public static $AXO_122 = 122;
	public static $AXO_123 = 123;
	public static $AXO_124 = 124;
	public static $AXO_125 = 125;
	public static $AXO_126 = 126;
	public static $AXO_127 = 127;
	public static $AXO_SPEED_HUMP_10M = 128;
	public static $AXO_SPEED_HUMP_6M = 129;
	public static $AXO_130 = 130;
	public static $AXO_131 = 131;
	public static $AXO_132 = 132;
	public static $AXO_133 = 133;
	public static $AXO_134 = 134;
	public static $AXO_135 = 135;
	public static $AXO_POST_GREEN = 136;
	public static $AXO_POST_ORANGE = 137;
	public static $AXO_POST_RED = 138;
	public static $AXO_POST_WHITE = 139;
	public static $AXO_140 = 140;
	public static $AXO_141 = 141;
	public static $AXO_142 = 142;
	public static $AXO_143 = 143;
	public static $AXO_BALE = 144;
	public static $AXO_145 = 145;
	public static $AXO_146 = 146;
	public static $AXO_147 = 147;
	public static $AXO_RAILING = 148;
	public static $AXO_149 = 149;
	public static $AXO_150 = 150;
	public static $AXO_151 = 151;
	public static $AXO_152 = 152;
	public static $AXO_153 = 153;
	public static $AXO_154 = 154;
	public static $AXO_155 = 155;
	public static $AXO_156 = 156;
	public static $AXO_157 = 157;
	public static $AXO_158 = 158;
	public static $AXO_159 = 159;
	public static $AXO_SIGN_KEEP_LEFT = 160;
	public static $AXO_SIGN_KEEP_RIGHT = 161;
	public static $AXO_162 = 162;
	public static $AXO_163 = 163;
	public static $AXO_164 = 164;
	public static $AXO_165 = 165;
	public static $AXO_166 = 166;
	public static $AXO_167 = 167;
	public static $AXO_SIGN_SPEED_80 = 168;
	public static $AXO_SIGN_SPEED_50 = 169;
	public static $AXO_170 = 170;
	public static $AXO_171 = 171;
	public static $AXO_172 = 172;
	public static $AXO_173 = 173;
	public static $AXO_174 = 174;
	public static $AXO_175 = 175;
	public static $AXO_176 = 176;
	public static $AXO_177 = 177;
	public static $AXO_178 = 178;
	public static $AXO_179 = 179;
	public static $AXO_180 = 180;
	public static $AXO_181 = 181;
	public static $AXO_182 = 182;
	public static $AXO_183 = 183;
	public static $AXO_184 = 184;
	public static $AXO_185 = 185;
	public static $AXO_186 = 186;
	public static $AXO_187 = 187;
	public static $AXO_188 = 188;
	public static $AXO_189 = 189;
	public static $AXO_190 = 190;
	public static $AXO_191 = 191;

	public static $TC_BLACK = 0;
	public static $TC_WHITE = 1;
	public static $TC_RED = 2;
	public static $TC_BLUE = 3;
	public static $TC_GREEN = 4;
	public static $TC_YELLOW = 5;

	public static $CC_WHITE = 0;
	public static $CC_RED = 1;
	public static $CC_BLUE = 2;
	public static $CC_YELLOW = 3;

	public static $REV_AXO = array('AXO_NULL','AXO_1','AXO_2','AXO_3','AXO_CHALK_LINE','AXO_CHALK_LINE2','AXO_CHALK_AHEAD','AXO_CHALK_AHEAD2','AXO_CHALK_LEFT','AXO_CHALK_LEFT2','AXO_CHALK_LEFT3','AXO_CHALK_RIGHT','AXO_CHALK_RIGHT2','AXO_CHALK_RIGHT3','AXO_14','AXO_15','AXO_16','AXO_17','AXO_18','AXO_19','AXO_CONE_RED','AXO_CONE_RED2','AXO_CONE_RED3','AXO_CONE_BLUE','AXO_CONE_BLUE2','AXO_CONE_GREEN','AXO_CONE_GREEN2','AXO_CONE_ORANGE','AXO_CONE_WHITE','AXO_CONE_YELLOW','AXO_CONE_YELLOW2','AXO_31','AXO_32','AXO_33','AXO_34','AXO_35','AXO_36','AXO_37','AXO_38','AXO_39','AXO_CONE_PTR_RED','AXO_CONE_PTR_BLUE','AXO_CONE_PTR_GREEN','AXO_CONE_PTR_YELLOW','AXO_44','AXO_45','AXO_46','AXO_47','AXO_TYRE_SINGLE','AXO_TYRE_STACK2','AXO_TYRE_STACK3','AXO_TYRE_STACK4','AXO_TYRE_SINGLE_BIG','AXO_TYRE_STACK2_BIG','AXO_TYRE_STACK3_BIG','AXO_TYRE_STACK4_BIG','AXO_56','AXO_57','AXO_58','AXO_59','AXO_60','AXO_61','AXO_62','AXO_63','AXO_MARKER_CURVE_L','AXO_MARKER_CURVE_R','AXO_MARKER_L','AXO_MARKER_R','AXO_MARKER_HARD_L','AXO_MARKER_HARD_R','AXO_MARKER_L_R','AXO_MARKER_R_L','AXO_MARKER_S_L','AXO_MARKER_S_R','AXO_MARKER_S2_L','AXO_MARKER_S2_R','AXO_MARKER_U_L','AXO_MARKER_U_R','AXO_78','AXO_79','AXO_80','AXO_81','AXO_82','AXO_83','AXO_DIST25','AXO_DIST50','AXO_DIST75','AXO_DIST100','AXO_DIST125','AXO_DIST150','AXO_DIST200','AXO_DIST250','AXO_92','AXO_93','AXO_94','AXO_95','AXO_ARMCO1','AXO_ARMCO3','AXO_ARMCO5','AXO_99','AXO_100','AXO_101','AXO_102','AXO_103','AXO_BARRIER_LONG','AXO_BARRIER_RED','AXO_BARRIER_WHITE','AXO_107','AXO_108','AXO_109','AXO_110','AXO_111','AXO_BANNER1','AXO_BANNER2','AXO_114','AXO_115','AXO_116','AXO_117','AXO_118','AXO_119','AXO_RAMP1','AXO_RAMP2','AXO_122','AXO_123','AXO_124','AXO_125','AXO_126','AXO_127','AXO_SPEED_HUMP_10M','AXO_SPEED_HUMP_6M','AXO_130','AXO_131','AXO_132','AXO_133','AXO_134','AXO_135','AXO_POST_GREEN','AXO_POST_ORANGE','AXO_POST_RED','AXO_POST_WHITE','AXO_140','AXO_141','AXO_142','AXO_143','AXO_BALE','AXO_145','AXO_146','AXO_147','AXO_RAILING','AXO_149','AXO_150','AXO_151','AXO_152','AXO_153','AXO_154','AXO_155','AXO_156','AXO_157','AXO_158','AXO_159','AXO_SIGN_KEEP_LEFT','AXO_SIGN_KEEP_RIGHT','AXO_162','AXO_163','AXO_164','AXO_165','AXO_166','AXO_167','AXO_SIGN_SPEED_80','AXO_SIGN_SPEED_50','AXO_170','AXO_171','AXO_172','AXO_173','AXO_174','AXO_175','AXO_176','AXO_177','AXO_178','AXO_179','AXO_180','AXO_181','AXO_182','AXO_183','AXO_184','AXO_185','AXO_186','AXO_187','AXO_188','AXO_189','AXO_190','AXO_191');
	public static $REV_TC = array('TC_BLACK','TC_WHITE','TC_RED','TC_BLUE','TC_GREEN','TC_YELLOW');
	public static $REV_CC = array('CC_WHITE','CC_RED','CC_BLUE','CC_YELLOW');

	public static $Objects = array(
		0 => 'Scenery Object',
		4 => 'Long Chalk Line',
		5 => 'Short Chalk Line',
		6 => 'Short Ahead Arrow',
		7 => 'Long Ahead Arrow',
		8 => 'Short Left Curve Arrow',
		9 => 'Left Turn Arrow',
		10 => 'Long Left Curve Arrow',
		11 => 'Short Right Curve Arrow',
		12 => 'Right Turn Arrow',
		13 => 'Long Right Curve Arrow',
		20 => 'Red+White Cone',
		21 => 'Red Cone',
		22 => 'Striped Red Cone',
		23 => 'Striped Blue Cone',
		24 => 'Blue Cone',
		25 => 'Striped Green Cone',
		26 => 'Green Cone',
		27 => 'Orange Cone',
		28 => 'White Cone',
		29 => 'Striped Yellow Cone',
		30 => 'Yellow Cone',
		40 => 'Red Directional Cone',
		41 => 'Blue Directional Cone',
		42 => 'Green Directional Cone',
		43 => 'Yellow Directional Cone',
		48 => 'Single Tire',
		49 => 'Tire Stack of 2',
		50 => 'Tire Stack of 3',
		51 => 'Tire Stack of 4',
		52 => 'Big Single Tire',
		53 => 'Big Tire Stack of 2',
		54 => 'Big Tire Stack of 3',
		55 => 'Big Tire Stack of 4',
		64 => 'Left Curve Marker',
		65 => 'Right Curve Marker',
		66 => 'Left Turn Marker',
		67 => 'Right Turn Marker',
		68 => 'Hard Left Turn Marker',
		69 => 'Hard Right Turn Marker',
		70 => 'Left->Right Road Marker',
		71 => 'Right->Left Road Marker',
		72 => 'U-Turn->Right Turn Marker',
		73 => 'U-Turn->Left Turn Marker',
		74 => 'Left Winding Turn Marker',
		75 => 'Right Winding Turn Marker',
		76 => 'Left U-Turn Marker',
		77 => 'Right U-Turn Marker',
		84 => '25m Sign',
		85 => '50m Sign',
		86 => '75m Sign',
		87 => '100m Sign',
		88 => '125m Sign',
		89 => '150m Sign',
		90 => '200m Sign',
		91 => '250m Sign',
		96 => 'Short Railing',
		97 => 'Medium Railing', 98 => 'Long Railing',
		104 => 'Long Barrier',
		105 => 'Red Barrier', 106 => 'White Barrier',
		112 => 'Banner',
		113 => 'Banner',
		120 => 'Ramp',
		121 => 'Wide Ramp',
		128 => '10m Speed Bump',
		129 => '6m Speed Bump',
		136 => 'Green Post',
		137 => 'Orange Post',
		138 => 'Red Post',
		139 => 'White Post',
		144 => 'Hay Bale',
		148 => 'Railing',
		160 => 'Keep Left Sign',
		161 => 'Keep Right Sign',
		168 => '80 KM/H Sign',
		169 => '50 KM/H Sign'
	);
}

?>