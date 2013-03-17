<?php

namespace PRISM\Module;

use Module\IniLoader;

abstract class SectionHandler extends \Module\IniLoader
{
	abstract public function init();
}
