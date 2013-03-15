<?php

namespace PRISM\Module;

use PRISM\Module\IniLoader;

abstract class SectionHandler extends IniLoader
{
	abstract public function initialise();
}

