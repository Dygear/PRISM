<?php

namespace PRISM\Module;

use Module\IniLoader;

abstract class SectionHandler extends IniLoader
{
	abstract public function init();
}
