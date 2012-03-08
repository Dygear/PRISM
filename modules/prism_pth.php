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
		foreach ($this->Nodes as $ID => $Node)
		{
			$toPoly[$ID] = array(
				array( # Left
					'x' => $Node->Center->X + $Node->$limitRoad->Left * cos(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536,
					'y' => $Node->Center->Y - $Node->$limitRoad->Left * sin(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536
				), array ( # Right
					'x' => $Node->Center->X + $Node->$limitRoad->Right * cos(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536,
					'y' => $Node->Center->Y - $Node->$limitRoad->Right * sin(atan2($Node->Direction->X, $Node->Direction->Y)) * 65536
				)
			);
		}
		return $toPoly;
	}
	public function isOnRoad($x, $y, $NodeID)
	{
		return $this->inPoly($x, $y, $this->polyRoad[$NodeID]);
	}
	public function isOnLimit($x, $y, $NodeID)
	{
		return $this->inPoly($x, $y, $this->polyLimit[$NodeID]);
	}
	/**
	 * @parm $x - A IS_MCI->CompCar->X
	 * @parm $y - A IS_MCI->CompCar->Y
	 * @parm $polygon - A array of X, Y points.
	 * @author PHP version by filur & Dygear
	 * @coauthor Original code by Brian J. Fox of MetaHTML.
	 * @url http://metahtml.cvs.sourceforge.net/viewvc/metahtml/metahtml/utilities/imagemap/imagemap.c?revision=1.1.1.1&view=markup
	*/
	public function inPoly($x, $y, array $polygon)
	{
		$min_x = NULL;
		$max_x = NULL;
		$min_y = NULL;
		$max_y = NULL;
		$result = 0;
		
		# Count vertices.
		$vertices = count($polygon);
		
		# Get the bounding box.
		foreach ($polygon as $point)
		{
			if ($min_x === NULL || $point['x'] < $min_x)
				$min_x = $point['x'];
			if ($min_y === NULL|| $point['y'] < $min_y)
				$min_y = $point['y'];
			if ($min_x === NULL || $point['x'] > $max_x)
				$max_x = $point['x'];
			if ($min_x === NULL || $point['y'] > $max_y)
				$max_y = $point['y'];
		}
		
		# If it's outside of the bounding box, there's no chance it's in the poly.
		if ($x < $min_x || $x > $max_x || $y < $min_y || $y > $max_y)
			return FALSE;
		
		$lines_crossed = 0;
		
		# The point falls within the bounding box. Check adjacent vertices.
		for ($i = 1; isset($polygon[$i]); $i++)
		{
			$p1 =& $polygon[$i - 1];
			$p2 =& $polygon[$i];
			
			$min_x = min ($p1['x'], $p2['x']);
			$max_x = max ($p1['x'], $p2['x']);
			$min_y = min ($p1['y'], $p2['y']);
			$max_y = max ($p1['y'], $p2['y']);
			
			# We need to know if the point falls within the rectangle defined by the maximum vertices of the vector.
			if ($x < $min_x || $x > $max_x || $y < $min_y || $y > $max_y)
			{
				# Not within the rectangle. Great! If it is to the left of the rectangle, and in between the Y coordinates, then it crosses the line.
				if ($x < $min_x && $y > $min_y && $y < $max_y)
					$lines_crossed++;
				
				continue;
			}
			
			/* Find the intersection of the line ([-inf, Y], [+inf, Y]) and ((p1[x], p1[y]), [p2[x], p2[y]]).  If the location of the intercept is to the right of Xcoord, then the line will be crossed. */
			$slope = ($p1['y'] - $p2['y']) / ($p1['x'] - $p2['x']);
			if ((($y - ($p1['y'] - ($slope * $p1['x']))) / $slope) >= $x)
				$lines_crossed++;
		}
		
		# If that number is even, then $X, $Y is "outside" of the polygon, if odd, then "inside"
		return ($lines_crossed % 2) ? FALSE : TRUE;
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