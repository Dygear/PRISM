<?php
/**
 * PHPInSimMod - PTH Module
 * @package PRISM
 * @subpackage PTH
*/
// PaTH
class PTH
{
	const PACK = 'a6CCll';
	const UNPACK = 'a6LFSPTH/CVersion/CRevision/VNumNodes/VFinishLine';

	public $LFSPTH = 'LFSPTH';
	public $Version = 0;
	public $Revision = 0;
	public $NumNodes;
	public $FinishLine;
	public $Nodes = array();

	public function __construct($pthFilePath)
	{
		$file = file_get_contents($pthFilePath);

		if ($this->unPack($file) === TRUE)
			return; # trigger_error returns (bool) TRUE, so if the return is true, then was an error.

		return $this;
	}
	public function unPack($file)
	{
		if (substr($file, 0, 6) != $this->LFSPTH)
			return trigger_error('Not a LFS PTH file', E_USER_ERROR);

		if (substr($file, 6, 1) != $this->Version)
			return trigger_error('Not a LFS PTH Verion Is Different Than PTH Paser', E_USER_ERROR);

		if (substr($file, 7, 1) != $this->Revision)
			return trigger_error('Not a LFS PTH Revision Is Different Than PTH Paser', E_USER_ERROR);
		
		foreach (unpack(self::UNPACK, substr($file, 0, 16)) as $property => $value)
			$this->$property = $value;

		for ($Node = 0; $Node < $this->NumNodes; ++$Node)
			$this->Nodes[$Node] = new Node(substr($file, 16 + ($Node * 40), 40));

		print_r($this);

		return $this;
	}
	public function toPoly($limitRoad)
	{
		$toPoly = array();
		# Left side
		foreach ($this->Nodes as $ID => $Node) {
			$toPoly[] = $Node->Center->X + $Node->$limitRoad->Left * cos(atan2($Node->Direction->X, $Node->Direction->Y));
			$toPoly[] = $Node->Center->Y - $Node->$limitRoad->Left * sin(atan2($Node->Direction->X, $Node->Direction->Y));
		}
		# Right side
		foreach ($this->Nodes as $ID => $Node) {
			$toPoly[] = $Node->Center->X + $Node->$limitRoad->Right * cos(atan2($Node->Direction->X, $Node->Direction->Y));
			$toPoly[] = $Node->Center->Y - $Node->$limitRoad->Right * sin(atan2($Node->Direction->X, $Node->Direction->Y));
		}
		return $toPoly;
	}
	private function point($Node, $limitRoad, $leftRight) {
		return ;
	}
}
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
class Center
{
	const PACK = 'VVV';
	const UNPACK = 'VX/VY/VZ';
	
	public function __construct($rawData) {
		$this->unPack($rawData);
	}
	public function unPack($rawData) {
		foreach (unpack($this::UNPACK, $rawData) as $property => $value)
			$this->$property = $value;
	}
}
class Direction
{
	const PACK = 'fff';
	const UNPACK = 'fX/fY/fZ';

	public function __construct($rawData) {
		$this->unPack($rawData);
	}
	public function unPack($rawData) {
		foreach (unpack($this::UNPACK, $rawData) as $property => $value)
			$this->$property = $value;
	}
}
class Limit
{
	const PACK = 'ff';
	const UNPACK = 'fLeft/fRight';

	public function __construct($rawData) {
		$this->unPack($rawData);
	}
	public function unPack($rawData) {
		foreach (unpack($this::UNPACK, $rawData) as $property => $value)
			$this->$property = $value;
	}
}
class Road
{
	const PACK = 'ff';
	const UNPACK = 'fLeft/fRight';

	public function __construct($rawData) {
		$this->unPack($rawData);
	}
	public function unPack($rawData) {
		foreach (unpack($this::UNPACK, $rawData) as $property => $value)
			$this->$property = $value;
	}
}
?>