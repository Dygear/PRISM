<?php

namespace PRISM\Module\Path;

class Node
{
    public $Center;
	public $Direction;
	public $Limit;
	public $Road;

	public function __construct($RawNode) {
		$this->Center = new Center(substr($RawNode, 0, 12));
		$this->Direction = new Direction(substr($RawNode, 12, 12));
		$this->Limit = new Limit(substr($RawNode, 24, 8));
		$this->Road = new Road(substr($RawNode, 32, 8));
	}
}
