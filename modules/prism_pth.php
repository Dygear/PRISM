<?php
/**
 * PHPInSimMod - PTH Module
 * @package PRISM
 * @subpackage PTH
*/

require_once(ROOTPATH . '/modules/prism_geometry.php');

// PaTH
class Path
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

		if ($this->unPack($file) === true) {
			return; # trigger_error returns (bool) TRUE, so if the return is true, there was an error.
		}

		return $this;
	}
	public function __destruct()
	{
		array_splice($this->Nodes, 0, $this->NumNodes);
		array_splice($this->polyRoad, 0, $this->NumNodes);
		array_splice($this->polyLimit, 0, $this->NumNodes);
	}
	public function unPack($file)
	{
		if (substr($file, 0, 6) != $this->LFSPTH) {
			return trigger_error('Not a LFS PTH file', E_USER_ERROR);
		}

		if (substr($file, 6, 1) != $this->Version) {
			return trigger_error('Not a LFS PTH Version Is Different Than PTH Parser', E_USER_ERROR);
		}

		if (substr($file, 7, 1) != $this->Revision) {
			return trigger_error('Not a LFS PTH Revision Is Different Than PTH Parser', E_USER_ERROR);
		}

		foreach (unpack(self::UNPACK, substr($file, 0, 16)) as $property => $value) {
			$this->$property = $value;
		}

		for ($Node = 0; $Node < $this->NumNodes; $Node++) {
			$this->Nodes[] = new Node(substr($file, 16 + ($Node * 40), 40));
		}

		$this->toPoly($this->polyRoad, 'Road');
		$this->toPoly($this->polyLimit, 'Limit');

		return $this;
	}
	public function toPoly(array &$nodePolys, $limitRoad)
	{
		array_splice($nodePolys, 0, count($nodePolys));

		foreach ($this->Nodes as $i => $Nodes) {
			if ($i == 0) {
				$pa = new Point2D($Nodes->Direction->Y * $Nodes->$limitRoad->Left + $Nodes->Center->X,
								 -$Nodes->Direction->X * $Nodes->$limitRoad->Left + $Nodes->Center->Y);
				$pb = new Point2D($Nodes->Direction->Y * $Nodes->$limitRoad->Right + $Nodes->Center->X,
								 -$Nodes->Direction->X * $Nodes->$limitRoad->Right + $Nodes->Center->Y);
			}

			$pc = new Point2D($Nodes->Direction->Y * $Nodes->$limitRoad->Left + $Nodes->Center->X,
							 -$Nodes->Direction->X * $Nodes->$limitRoad->Left + $Nodes->Center->Y);
			$pd = new Point2D($Nodes->Direction->Y * $Nodes->$limitRoad->Right + $Nodes->Center->X,
							 -$Nodes->Direction->X * $Nodes->$limitRoad->Right + $Nodes->Center->Y);

			$nodePolys[] = new Polygon2D(array($pa, $pb, $pd, $pc));

			$pa = $pc;
			$pb = $pd;
		}

		// Close the path
		$nodePolys[] = new Polygon2D(array($pa, $pb, $nodePolys[0]->points[1], $nodePolys[0]->points[0]));
	}
	public function isOnRoad($x, $y, $NodeID)
	{
		$x /= 65536;
		$y /= 65536;

		// Check if point is within the left and right lines of the path
		$p1 = $this->polyRoad[$NodeID]->points[1];
		$p2 = $this->polyRoad[$NodeID]->points[2];
        
		if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0) {
			return false;
		}

		$p1 = $this->polyRoad[$NodeID]->points[3];
		$p2 = $this->polyRoad[$NodeID]->points[0];
        
		if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0) {
			return false;
		}

		return true;
	}
	public function isOnLimit($x, $y, $NodeID)
	{
		$x /= 65536;
		$y /= 65536;

		// Check if point is within the left and right lines of the path
		$p1 = $this->polyLimit[$NodeID]->points[1];
		$p2 = $this->polyLimit[$NodeID]->points[2];
        
		if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0) {
			return false;
		}

		$p1 = $this->polyLimit[$NodeID]->points[3];
		$p2 = $this->polyLimit[$NodeID]->points[0];
        
		if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0) {
			return false;
		}

		return true;
	}
	public function drawPath ($fileName)
	{
		$im = imagecreatetruecolor(2560, 2560);
		$bg = imagecolorallocate($im, 254, 254, 254);
		imagefill($im, 0, 0, $bg);
		imagecolortransparent($im, $bg);

		$p =& $this;

		$LeftCos = cos (90 * M_PI / 180);
		$LeftSin = sin (90 * M_PI / 180);
		$RightCos = cos (-90 * M_PI / 180);
		$RightSin = sin (-90 * M_PI / 180);

		$Node = end($p->Nodes); # Get's last node.
		$llx2 = ($Node->Direction->X * $LeftCos - (-$Node->Direction->Y) * $LeftSin) * $Node->Limit->Left + ($Node->Center->X + 1024);
		$lly2 = ((-$Node->Direction->Y) * $LeftCos + $Node->Direction->X * $LeftSin) * $Node->Limit->Left + ((-$Node->Center->Y) + 1024);
		$lrx2 = ($Node->Direction->X * $RightCos - (-$Node->Direction->Y) * $RightSin) * -$Node->Limit->Right + ($Node->Center->X + 1024);
		$lry2 = ((-$Node->Direction->Y) * $RightCos + $Node->Direction->X * $RightSin) * -$Node->Limit->Right + ((-$Node->Center->Y) + 1024);

		$dlx2 = ($Node->Direction->X * $LeftCos - (-$Node->Direction->Y) * $LeftSin) * $Node->Road->Left + ($Node->Center->X + 1024);
		$dly2 = ((-$Node->Direction->Y) * $LeftCos + $Node->Direction->X * $LeftSin) * $Node->Road->Left + ((-$Node->Center->Y) + 1024);
		$drx2 = ($Node->Direction->X * $RightCos - (-$Node->Direction->Y) * $RightSin) * -$Node->Road->Right + ($Node->Center->X + 1024);
		$dry2 = ((-$Node->Direction->Y) * $RightCos + $Node->Direction->X * $RightSin) * -$Node->Road->Right + ((-$Node->Center->Y) + 1024);

		$limit_col = imagecolorallocatealpha($im, 8, 128, 16, 64);
		$drive_col = imagecolorallocatealpha($im, 64, 64, 64, 64);

		reset($p->Nodes);	# Resets our pointer back to the start.
        
		foreach ($p->Nodes as $i => $Node)
		{
			// Limit
			$llx = ($Node->Direction->X * $LeftCos - (-$Node->Direction->Y) * $LeftSin) * $Node->Limit->Left + ($Node->Center->X + 1024);
			$lly = ((-$Node->Direction->Y) * $LeftCos + $Node->Direction->X * $LeftSin) * $Node->Limit->Left + ((-$Node->Center->Y) + 1024);
			$lrx = ($Node->Direction->X * $RightCos - (-$Node->Direction->Y) * $RightSin) * -$Node->Limit->Right + ($Node->Center->X + 1024);
			$lry = ((-$Node->Direction->Y) * $RightCos + $Node->Direction->X * $RightSin) * -$Node->Limit->Right + ((-$Node->Center->Y) + 1024);

			$l_array = array ($llx2, $lly2, $llx, $lly, $lrx, $lry, $lrx2, $lry2);
			imagefilledpolygon($im, $l_array, 4, $limit_col);

			$llx2 = $llx;
			$lly2 = $lly;
			$lrx2 = $lrx;
			$lry2 = $lry;

			// Drive
			$dlx = ($Node->Direction->X * $LeftCos - (-$Node->Direction->Y) * $LeftSin) * $Node->Road->Left + ($Node->Center->X + 1024);
			$dly = ((-$Node->Direction->Y) * $LeftCos + $Node->Direction->X * $LeftSin) * $Node->Road->Left + ((-$Node->Center->Y) + 1024);
			$drx = ($Node->Direction->X * $RightCos - (-$Node->Direction->Y) * $RightSin) * -$Node->Road->Right + ($Node->Center->X + 1024);
			$dry = ((-$Node->Direction->Y) * $RightCos + $Node->Direction->X * $RightSin) * -$Node->Road->Right + ((-$Node->Center->Y) + 1024);

			$d_array = array ($dlx2, $dly2, $dlx, $dly, $drx, $dry, $drx2, $dry2);
			imagefilledpolygon($im, $d_array, 4, $drive_col);

			$dlx2 = $dlx;
			$dly2 = $dly;
			$drx2 = $drx;
			$dry2 = $dry;
		}

		imagepng($im, $fileName);
		imagedestroy($im);
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
