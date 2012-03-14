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
	public $polyRoad = array();
	public $polyLimit = array();

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

		$this->polyRoad = $this->toPoly('Road');
		$this->polyLimit = $this->toPoly('Limit');

		return $this;
	}
	public function toPoly($limitRoad)
	{
		$toPoly = array();
		forEach ($this->Nodes as $ID => $Node) {
			$toPoly[$ID] = array(
				array(
					'x' => $Node->Center->X + $Node->$limitRoad->Left * cos(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536,
					'y' => $Node->Center->Y - $Node->$limitRoad->Left * sin(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536
				), array(
					'x' => $Node->Center->X + $Node->$limitRoad->Right * cos(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536,
					'y' => $Node->Center->Y - $Node->$limitRoad->Right * sin(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536
				)
			);
		}
		return $toPoly;
	}
	public function isOnRoad($x, $y, $NodeID)
	{
		if ($NodeID == $this->NumNodes)
			return $this->inPoly($x, $y, $this->polyRoad[$NodeID], $this->polyRoad[0]);
		else
			return $this->inPoly($x, $y, $this->polyRoad[$NodeID], $this->polyRoad[$NodeID + 1]);
	}
	public function isOnLimit($x, $y, $NodeID)
	{
		if ($NodeID == $this->NumNodes)
			return $this->inPoly($x, $y, $this->polyLimit[$NodeID], $this->polyLimit[0]);
		else
			return $this->inPoly($x, $y, $this->polyLimit[$NodeID], $this->polyLimit[$NodeID + 1]);
	}
	/**
	 * @parm $Xcoord - A IS_MCI->CompCar->X
	 * @parm $Ycoord - A IS_MCI->CompCar->Y
	 * @parm $poly1 - A array of X, Y points.
	 * @parm $poly2 - A array of X, Y points.
	 * @author avetere
	 * @url http://www.lfsforum.net/showthread.php?p=1626025
	*/
	public function inPoly($x, $y, array $poly1, array $poly2)
	{
		$x12 = $poly1[1]['x'] - $poly1[0]['x'];
		$x21 = $poly1[0]['x'] - $poly1[1]['x'];
		$x13 = $poly2[1]['x'] - $poly1[0]['x'];
		$x31 = $poly1[0]['x'] - $poly2[1]['x'];
		$x23 = $poly2[1]['x'] - $poly1[1]['x'];
		$x41 = $poly1[0]['x'] - $poly2[0]['x'];
		$x34 = $poly2[0]['x'] - $poly2[1]['x'];
		$x43 = $poly2[1]['x'] - $poly2[0]['x'];
		$x1p = $x - $poly1[0]['x'];
		$x2p = $x - $poly1[1]['x'];
		$x3p = $x - $poly2[1]['x'];
		$x4p = $x - $poly2[0]['x'];

		$y12 = $poly1[1]['y'] - $poly1[0]['y'];
		$y21 = $poly1[0]['y'] - $poly1[1]['y'];
		$y13 = $poly2[1]['y'] - $poly1[0]['y'];
		$y31 = $poly1[0]['y'] - $poly2[1]['y'];
		$y23 = $poly2[1]['y'] - $poly1[1]['y'];
		$y41 = $poly1[0]['y'] - $poly2[0]['y'];
		$y34 = $poly2[0]['y'] - $poly2[1]['y'];
		$y43 = $poly2[1]['y'] - $poly2[0]['y'];
		$y1p = $y - $poly1[0]['y'];
		$y2p = $y - $poly1[1]['y'];
		$y3p = $y - $poly2[1]['y'];
		$y4p = $y - $poly2[0]['y'];

		return ((($x12*$y13 - $y12*$x13)*($x12*$y1p - $y12*$x1p) >= 0 ) && (($x23*$y21 - $y23*$x21)*($x23*$y2p - $y23*$x2p) >= 0) && (($x34*$y31 - $y34*$x31)*($x34*$y3p - $y34*$x3p) >= 0) && (($x41*$y43 - $y41*$x43)*($x41*$y4p - $y41*$x4p) >= 0)) ? TRUE : FALSE;
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