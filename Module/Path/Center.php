<?php

namespace PRISM\Module\Path;

class Center
{
    const PACK = 'lll';
	const UNPACK = 'lX/lY/lZ';

	public function __construct($rawCenter) {
		$this->unPack($rawCenter);
	}
    
	public function unPack($rawCenter) {
		foreach (unpack($this::UNPACK, $rawCenter) as $property => $value)
			$this->$property = $value / 65536;
	}
}
