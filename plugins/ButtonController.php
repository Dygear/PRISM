<?php
/**
 * Module to hook the ButtonManager into the packets.
 * When converting this to a core feature, add the calls to ButtonManager in the corresponding places
 */
class ButtonController extends Plugins
{
	const NAME = 'Button controller plugin to "catch" the buttons';
	const AUTHOR = "GeForz";
	const VERSION = '51245';
	
	public function __construct()
	{
		$this->registerPacket('onButtonFunction', ISP_BFN);
		$this->registerPacket('onButtonClick', ISP_BTC);
		$this->registerPacket('onButtonText', ISP_BTT);
	}
	
	public function onButtonFunction(IS_BFN $BFN)
	{
		if ($BFN->SubT == BFN_USER_CLEAR)
		{
			// forget about these buttons in the buttonmanager as they were removed on client side
			ButtonManager::clearButtonsForConn($BFN->UCID);
		}
		else if ($BFN->SubT == BFN_REQUEST)
		{
			// the plugin should create the buttons for the user.
			// i.e. not: ButtonManager::buttonsForUser($BFN->UCID);
		}
	}
	
	public function onButtonClick(IS_BTC $BTC)
	{
		ButtonManager::onButtonClick($BTC);
	}
	
	public function onButtonText(IS_BTT $BTT)
	{
		ButtonManager::onButtonText($BTC);
	}
}


/**
 * "Singleton" class to manage buttons!
 */
class ButtonManager
{
	private static $buttons = array();
	private static $ids = array();

	/** Called by Button->send(). Assigns unique clickId. */
	public static function registerButton(Button $BTN, $hostId = null)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		// next id: may be 0 - 239
		$id = 0;
		if (isset(self::$ids[$hostId]))
		{
			$id = self::$ids[$hostId];
		}
		
		$BTN->ReqI = $id + 1; // may not be zero -_-
		$BTN->ClickID = $id;
		
		self::$ids[$hostId] = $id + 1;
	}
	
	public static function clearButtonsForConn($UCID, $hostId = NULL)
	{
		$hostButtons = self::buttonsForHost($hostId);
		unset($hostButtons[$UCID]);
	}
	
	public static function clearAllButtons($hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		unset(self::$buttons[$hostId]); // does unset trigger a not set notice?
	}
	
	private static function buttonsForConn($UCID, $hostId = NULL)
	{
		$hostButtons = self::buttonsForHost($hostId);
		
		if (!isset($hostButtons[$UCID]))
		{
			$hostButtons[$UCID] = array();
		}
		
		return $hostButtons[$UCID];
	}
	
	private static function buttonsForHost($hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		if (!isset(self::$buttons[$hostId]))
		{
			self::$buttons[$hostId] = array();
		}
		
		return self::$buttons[$hostId];
	}
	
	public static function removeButton(Button $BTN, $hostId = NULL)
	{
		if ($hostId === NULL)
		{
			global $PRISM;
			$hostId = $PRISM->hosts->curHostID;
		}
		
		self::buttonsForHost($hostId);
		
	}
	
	public function onButtonClick(IS_BTC $BTC)
	{
		var_dump($BTC);
	}

	
	
	
	private static $reservedAreas = array();
	
	public static function reserveArea($L = -1, $T = -1, $W = -1, $H = -1)
	{
		$L = $L>=0?IS_Y_MIN:$L;
		$T = $T>=0?IS_X_MIN:$T;
		$W = $W>=0?IS_Y_MAX:$W;
		$H = $H>=0?(IS_X_MAX - IS_X_MIN):$H;
		
		foreach (self::$reservedAreas as $area)
		{
			if (
				$area['L'] < ($L + $W)
				&&
				($area['L'] + $area['W']) > $L
				&&
				$area['T'] < ($T + $H)
				&&
				($area['T'] + $area['H']) > $T
			)
			{
				echo "Area already taken. L:".$area['L'].", T:".$area['T'].", W:".$area['W'].", H:".$area['H'];
				return false;
			}
		}
		
		self::$reservedAreas[] = array(
			'L' => $L,
			'T' => $T,
			'W' => $W,
			'H' => $H
		);
		
		return true;
	}
}

/**
 * Overwriting some methods. Should probably be merged into ISP_BTN.
 */
class Button extends IS_BTN
{
	private $key;
	private $onClick;
	private $onText;
	
	public static $TO_ALL = 255;
	public static $TO_LOCAL = 0;
	
	public function __construct($key, $UCID = 0, $hostID = NULL)
	{
		$this->key = $key;
		$this->UCID = $UCID;
	}
	
	public function send($hostId = NULL)
	{
		ButtonManager::registerButton($this, $hostId);
		parent::send($hostId);
	}
	
	public function registerOnClick(Plugins $plugin, $methodName)
	{
		$this->onClick = array($plugin, $methodName);
		$this->BStyle |= ISB_CLICK;
	}
	public function registerOnText(Plugins $plugin, $methodName)
	{
		$this->onClick = array($plugin, $methodName);
	}
	public function delete($hostId = NULL)
	{
		return ButtonManager::removeButton($this, $hostId);
	}
	
	
	public function UCID($val)
	{
		echo "UCID may only be set in constructor!";
		return $this;
	}
	public function ReqI($val)
	{
		echo "Do not set ClickID manually!";
		return $this;
	}
	public function ClickID($val)
	{
		echo "Do not set ClickID manually!";
		return $this;
	}
}