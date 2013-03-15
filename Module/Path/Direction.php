<?php

namespace PRISM\Module\Path;

class Direction
{
    const PACK = 'fff';
	const UNPACK = 'fX/fY/fZ';

	public function __construct($rawDirection) {
		$this->unPack($rawDirection);
	}
    
	public function unPack($rawDirection) {
		foreach (unpack($this::UNPACK, $rawDirection) as $property => $value)
			$this->$property = $value;
	}
}
