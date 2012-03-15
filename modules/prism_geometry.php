<?php
/**
 * PHPInSimMod - Geometry Module
 * @package PRISM
 * @subpackage Geometry
*/

class Point2D
{
    public $x = 0;
    public $y = 0;
    
    public function __construct($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }
}

class Polygon2D
{
    public $points      = array();
    public $numPoints   = 0;
    
    public function __construct(array $points = array())
    {
        $this->points       = $points;
        $this->numPoints    = count($points);
    }
    
    public function contains(Point2D $point)
    {
        // Simple check for convex poly
        for ($i = 0; $i < $this->numPoints; $i++)
        {
            $j = ($i + 1) % $this->numPoints;
            $p1 = $this->points[$i];
            $p2 = $this->points[$j];

            if (($point->y - $p1->y) * ($p2->x - $p1->x) - ($point->x - $p1->x) * ($p2->y - $p1->y) < 0)
                return false;
        }
        
        return true;
    }
}

?>