<?php

namespace PRISM\Module\Packet;

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