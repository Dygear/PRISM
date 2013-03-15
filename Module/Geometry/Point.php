<?php

namespace PRISM\Module\Geometry;

class Point
{
    public $x = 0;
    public $y = 0;
    
    public function __construct($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }
}