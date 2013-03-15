<?php

namespace PRISM\Module\Path;

class Limit
{
    const PACK = 'ff';
	const UNPACK = 'fLeft/fRight';

	public function __construct($rawLimit) {
		$this->unPack($rawLimit);
	}
    
	public function unPack($rawLimit) {
		foreach (unpack($this::UNPACK, $rawLimit) as $property => $value)
			$this->$property = $value;
	}
}
