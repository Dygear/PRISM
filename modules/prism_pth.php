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
	
	private $LastNode = 0xFFFFFF;

	public function __construct($pthFilePath)
	{
		$file = file_get_contents($pthFilePath);

		if ($this->unPack($file) === TRUE)
			return; # trigger_error returns (bool) TRUE, so if the return is true, there was an error.

		return $this;
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
        $pa = new Point2D($nodes[$i]->CenterX + $nodes[$i]->$lrLeft * cos(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)),
                          $nodes[$i]->CenterY - $nodes[$i]->$lrLeft * sin(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)));
        $pb = new Point2D($nodes[$i]->CenterX + $nodes[$i]->$lrRight * cos(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)),
                          $nodes[$i]->CenterY - $nodes[$i]->$lrRight * sin(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)));

        for ($i = 1; $i < $this->NumNodes; $i++)
        {
            $pc = new Point2D($nodes[$i]->CenterX + $nodes[$i]->$lrLeft * cos(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)),
                              $nodes[$i]->CenterY - $nodes[$i]->$lrLeft * sin(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)));
            $pd = new Point2D($nodes[$i]->CenterX + $nodes[$i]->$lrRight * cos(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)),
                              $nodes[$i]->CenterY - $nodes[$i]->$lrRight * sin(atan2($nodes[$i]->DirX, $nodes[$i]->DirY)));
            
            $nodePolys[] = new Polygon2D(array($pa, $pb, $pd, $pc));

            $pa = $pc;
            $pb = $pd;
        }
        
        // Close the path
        $nodePolys[] = new Polygon2D(array($pa, $pb, $nodePolys[0]->points[1], $nodePolys[0]->points[0]));
    }
	public function isOnRoad($x, $y, $NodeID)
	{
	    if ($this->LastNode == 0xFFFFFF) $this->LastNode = $NodeID;
	    $res = ($this->polyRoad[$NodeID]->contains(new Point2D($x, $y)) ||
	            $this->polyRoad[$this->LastNode]->contains(new Point2D($x, $y)));
	    $this->LastNode = $NodeID;
	    return $res;
	}
	public function isOnLimit($x, $y, $NodeID)
	{
	    if ($this->LastNode == 0xFFFFFF) $this->LastNode = $NodeID;
	    $res = ($this->polyLimit[$NodeID]->contains(new Point2D($x, $y)) ||
	            $this->polyLimit[$this->LastNode]->contains(new Point2D($x, $y)));
	    $this->LastNode = $NodeID;
	    return $res;
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

	    $this->LimitLeft  *= 65536;
	    $this->LimitRight *= 65536;
	    $this->DriveLeft  *= 65536;
	    $this->DriveRight *= 65536;
	}
}
?>