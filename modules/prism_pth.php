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
    public $Custom = false;
    public $NumNodes;
    public $FinishLine;
    public $Nodes = array();
    public $polyRoad = array();
    public $polyLimit = array();
    public $polyRoadBlocks = null;
    public $polyLimitBlocks = null;

    public function __construct($pthFilePath, $custom = false)
    {
        $file = file_get_contents($pthFilePath);
        $this->Custom = $custom;

        if ($this->unPack($file) === TRUE)
            return; # trigger_error returns (bool) TRUE, so if the return is true, there was an error.
        
        return $this;
    }
    public function __destruct()
    {
        array_splice($this->Nodes, 0, $this->NumNodes);
        array_splice($this->polyRoad, 0, $this->NumNodes);
        array_splice($this->polyLimit, 0, $this->NumNodes);
        $this->polyRoadBlocks = null;
        $this->polyLimitBlocks = null;
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

        if ($this->Custom)
        {
            $this->polyRoadBlocks = PolyGrid::fromArray($this->polyRoad, 16, 16, 1280);
            $this->polyLimitBlocks = PolyGrid::fromArray($this->polyLimit, 16, 16, 1280);
        }
        
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
        $nodePolys[] = new Polygon2D(array($pa, $pb, $nodePolys[0]->Points[1], $nodePolys[0]->Points[0]));
    }
    public function isOnRoad($x, $y, &$NodeID)
    {
        return $this->isOnPath($x, $y, $NodeID, 'polyRoad');
    }
    public function isOnLimit($x, $y, &$NodeID)
    {
        return $this->isOnPath($x, $y, $NodeID, 'polyLimit');
    }
    public function isOnPath($x, $y, &$NodeID, $limitRoad)
    {
        $x /= 65536;
        $y /= 65536;

        if ($this->Custom)
        {
            // Get the quadrant-sorted portion of the poly array
            $lr = $limitRoad.'Blocks';
            $polyArray = array();
            $numPolies = $this->$lr->getArrayByPoint(new Point2D($x, $y), $polyArray);
            for ($i = 0; $i < $numPolies; $i++)
            {
                if ($polyArray[$i]->Poly->contains(new Point2D($x, $y)))
                {
                    $NodeID = $polyArray[$i]->Key;
                    return true;
                }
            }
            
            return false;
        }
        else
        {
            $NodeID = ($NodeID < 0) ? 0 : $NodeID % $this->NumNodes;
            
            // Using regular nodeid to look up a polygon - $NodeID is used absolutely
            // Check if point is within the left and right sides of the polygon
            $p1 = $this->$limitRoad[$NodeID]->Points[1];
            $p2 = $this->$limitRoad[$NodeID]->Points[2];
            if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0)
                return false;
            
            $p1 = $this->$limitRoad[$NodeID]->Points[3];
            $p2 = $this->$limitRoad[$NodeID]->Points[0];
            if (($y - $p1->y) * ($p2->x - $p1->x) - ($x - $p1->x) * ($p2->y - $p1->y) < 0)
                return false;
            
            return true;
        }
            
        return false;
    }
    public function drawPath($fileName)
    {
        $im = imagecreatetruecolor(2560, 2560);
        $bgCol = imagecolorallocate($im, 254, 254, 254);
        $pathCol = imagecolorallocatealpha($im, 64, 64, 64, 64);
        imagefill($im, 0, 0, $bgCol);
        imagecolortransparent($im, $bgCol);

        for ($i = 0; $i < $this->NumNodes; $i++)
        {
            $pa = $this->polyRoad[$i]->Points[0];
            $pb = $this->polyRoad[$i]->Points[1];
            $pc = $this->polyRoad[$i]->Points[2];
            $pd = $this->polyRoad[$i]->Points[3];

            imagefilledpolygon($im,
                               array($pa->x + 1280, -$pa->y + 1280, 
                                     $pb->x + 1280, -$pb->y + 1280, 
                                     $pc->x + 1280, -$pc->y + 1280, 
                                     $pd->x + 1280, -$pd->y + 1280),
                               4,
                               $pathCol);
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
