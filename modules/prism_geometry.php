<?php
declare(strict_types=1);

/**
 * PHPInSimMod - Geometry Module
 * @package PRISM
 * @subpackage Geometry
*/

class Point2D
{
    public mixed $x = 0;
    public mixed $y = 0;

    /**
     * Point2D constructor.
     * @param int $x
     * @param int $y
     */
    public function __construct($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }
}

/**
 * Class Polygon2D
 */
class Polygon2D
{
    public array $points      = [];
    public int $numPoints   = 0;

    public function __construct(array $points = [])
    {
        $this->points       = $points;
        $this->numPoints    = count($points);
    }

    public function __destruct()
    {
        array_splice($this->points, 0, $this->numPoints);
    }

    /**
     * @param Point2D $point
     * @return bool
     */
    public function contains(Point2D $point): bool
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


