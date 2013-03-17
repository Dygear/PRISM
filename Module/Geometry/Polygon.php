<?php

namespace PRISM\Module\Geometry;

class Polygon
{
    public $points      = array();
    public $numPoints   = 0;
    
    public function __construct(array $points = array())
    {
        $this->points       = $points;
        $this->numPoints    = count($points);
    }
    
    public function __destruct()
    {
        array_splice($this->points, 0, $this->numPoints);
    }
    
    public function contains(Point2D $point)
    {
        // Simple check for convex poly
        for ($i = 0; $i < $this->numPoints; $i++) {
            $j = ($i + 1) % $this->numPoints;
            $p1 = $this->points[$i];
            $p2 = $this->points[$j];

            if (($point->y - $p1->y) * ($p2->x - $p1->x) - ($point->x - $p1->x) * ($p2->y - $p1->y) < 0) {
                return false;
            }
        }
        
        return true;
    }
}
