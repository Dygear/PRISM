<?php
/**
 * PHPInSimMod - PTH Module
 * @package PRISM
 * @subpackage PTH
*/
require_once(ROOTPATH . '/modules/prism_geometry.php');
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
			return; # trigger_error returns (bool) TRUE, so if the return is true, there was an error.

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
		if (substr($file, 0, 6) != $this->LFSPTH)
			return trigger_error('Not a LFS PTH file', E_USER_ERROR);

		if (substr($file, 6, 1) != $this->Version)
			return trigger_error('Not a LFS PTH Version Is Different Than PTH Parser', E_USER_ERROR);

		if (substr($file, 7, 1) != $this->Revision)
			return trigger_error('Not a LFS PTH Revision Is Different Than PTH Parser', E_USER_ERROR);
		
		foreach (unpack(self::UNPACK, substr($file, 0, 16)) as $property => $value)
			$this->$property = $value;

		for ($Node = 0; $Node < $this->NumNodes; $Node++)
			$this->Nodes[] = new Node(substr($file, 16 + ($Node * 40), 40));

        $this->toPoly($this->polyRoad, 'Drive');
        $this->toPoly($this->polyLimit, 'Limit');

		return $this;
	}
    public function toPoly(array &$nodePolys, $limitRoad)
    {
        array_splice($nodePolys, 0, count($nodePolys));
        $nodes =& $this->Nodes;
        $lrLeft = $limitRoad.'Left';
        $lrRight = $limitRoad.'Right';

        $i = 0;
        $pa = new Point2D($nodes[$i]->DirY * $nodes[$i]->$lrLeft + $nodes[$i]->CenterX,
                          -$nodes[$i]->DirX * $nodes[$i]->$lrLeft + $nodes[$i]->CenterY);
        $pb = new Point2D($nodes[$i]->DirY * $nodes[$i]->$lrRight + $nodes[$i]->CenterX,
                          -$nodes[$i]->DirX * $nodes[$i]->$lrRight + $nodes[$i]->CenterY);
        
        for ($i = 1; $i < $this->NumNodes; $i++)
        {
            $pc = new Point2D($nodes[$i]->DirY * $nodes[$i]->$lrLeft + $nodes[$i]->CenterX,
                              -$nodes[$i]->DirX * $nodes[$i]->$lrLeft + $nodes[$i]->CenterY);
            $pd = new Point2D($nodes[$i]->DirY * $nodes[$i]->$lrRight + $nodes[$i]->CenterX,
                              -$nodes[$i]->DirX * $nodes[$i]->$lrRight + $nodes[$i]->CenterY);
            
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
        if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0)
            return false;
        
        $p1 = $this->polyRoad[$NodeID]->points[3];
        $p2 = $this->polyRoad[$NodeID]->points[0];
        if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0)
            return false;
            
        return true;
	}
	public function isOnLimit($x, $y, $NodeID)
	{
	    $x /= 65536;
	    $y /= 65536;
	    
	    // Check if point is within the left and right lines of the path
        $p1 = $this->polyLimit[$NodeID]->points[1];
        $p2 = $this->polyLimit[$NodeID]->points[2];
        if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0)
            return false;
        
        $p1 = $this->polyLimit[$NodeID]->points[3];
        $p2 = $this->polyLimit[$NodeID]->points[0];
        if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0)
            return false;
            
        return true;
	}
	public function drawPath ($fileName) {
		$im = imagecreatetruecolor(2560, 2560);
		$bg = imagecolorallocate($im, 254, 254, 254);
		imagefill($im, 0, 0, $bg);
		imagecolortransparent($im, $bg);

		$p =& $this;

		$LeftCos = cos (90 * M_PI / 180);
		$LeftSin = sin (90 * M_PI / 180);
		$RightCos = cos (-90 * M_PI / 180);
		$RightSin = sin (-90 * M_PI / 180);

		$i = $p->NumNodes - 1;
		$dlx2 = ($p->Nodes[$i]->DirX * $LeftCos - (-$p->Nodes[$i]->DirY) * $LeftSin) * $p->Nodes[$i]->DriveLeft + ($p->Nodes[$i]->CenterX + 1024);
		$dly2 = ((-$p->Nodes[$i]->DirY) * $LeftCos + $p->Nodes[$i]->DirX * $LeftSin) * $p->Nodes[$i]->DriveLeft + ((-$p->Nodes[$i]->CenterY) + 1024);
		$drx2 = ($p->Nodes[$i]->DirX * $RightCos - (-$p->Nodes[$i]->DirY) * $RightSin) * -$p->Nodes[$i]->DriveRight + ($p->Nodes[$i]->CenterX + 1024);
		$dry2 = ((-$p->Nodes[$i]->DirY) * $RightCos + $p->Nodes[$i]->DirX * $RightSin) * -$p->Nodes[$i]->DriveRight + ((-$p->Nodes[$i]->CenterY) + 1024);

		$path_col = imagecolorallocatealpha($im, 64, 64, 64, 64);

		for ($i = 0; $i < $p->NumNodes; $i++) {
			$dlx = ($p->Nodes[$i]->DirX * $LeftCos - (-$p->Nodes[$i]->DirY) * $LeftSin) * $p->Nodes[$i]->DriveLeft + ($p->Nodes[$i]->CenterX + 1024);
			$dly = ((-$p->Nodes[$i]->DirY) * $LeftCos + $p->Nodes[$i]->DirX * $LeftSin) * $p->Nodes[$i]->DriveLeft + ((-$p->Nodes[$i]->CenterY) + 1024);
			$drx = ($p->Nodes[$i]->DirX * $RightCos - (-$p->Nodes[$i]->DirY) * $RightSin) * -$p->Nodes[$i]->DriveRight + ($p->Nodes[$i]->CenterX + 1024);
			$dry = ((-$p->Nodes[$i]->DirY) * $RightCos + $p->Nodes[$i]->DirX * $RightSin) * -$p->Nodes[$i]->DriveRight + ((-$p->Nodes[$i]->CenterY) + 1024);

			$p_array = array ($dlx2, $dly2, $dlx, $dly, $drx, $dry, $drx2, $dry2);

			imagefilledpolygon($im, $p_array, 4, $path_col);

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
	const UNPACK = 'lCenterX/lCenterY/lCenterZ/fDirX/fDirY/fDirZ/fLimitLeft/fLimitRight/fDriveLeft/fDriveRight';
	
	public $CenterX = 0;
	public $CenterY = 0;
	public $CenterZ = 0;
	public $DirX;
	public $DirY;
	public $DirZ;
	public $LimitLeft;
	public $LimitRight;
	public $DriveLeft;
	public $DriveRight;

	public function __construct($RawNode)
	{
		foreach (unpack(self::UNPACK, $RawNode) as $property => $value)
			$this->$property = $value;

	    $this->CenterX /= 65536;
	    $this->CenterY /= 65536;
	    $this->CenterZ /= 65536;
	    //$this->DirY = -$this->DirY;
	}
}
?>