<?php

namespace PRISM\Module\Path;

class Road
{
    const PACK = 'ff';
	const UNPACK = 'fLeft/fRight';

	public function __construct($rawRoad) {
		$this->unPack($rawRoad);
	}
    
	public function unPack($rawRoad) {
		foreach (unpack($this::UNPACK, $rawRoad) as $property => $value)
			$this->$property = $value;
	}
}
