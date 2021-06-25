<?php
declare(strict_types=1);

/**
 * Overwriting some methods. Should probably be merged into ISP_BTN.
 */
class Button extends IS_BTN
{
    private $key;
    private $group;

    private $onClick;
    private $onText;

    public static $TO_ALL = 255;
    public static $TO_LOCAL = 0;

				/**
					* Button constructor.
					* @param int $UCID
					* @param null $key
					* @param null $group
					*/
				public function __construct($UCID = 0, $key = NULL, $group = NULL)
    {
        $this->key = $key;
        $this->group = $group;
        $this->UCID = $UCID;
        $this->ClickID = -1;
    }

				/**
					* @param null $hostId
					* @return Button|void
					*/
				public function send($hostId = NULL)
    {
        $id = ButtonManager::registerButton($this, $hostId, $this->key, $this->group);

        if ($id !== false)
        {
            if (is_numeric($id))
            {
                $this->ReqI = $id + 1; // may not be zero -_-
                $this->ClickID = $id;
            }
            parent::send($hostId);
        }
    }

				/**
					* @param Plugins $plugin
					* @param $methodName
					* @param null $params
					*/
				public function registerOnClick(Plugins $plugin, $methodName, $params = NULL)
    {
        $this->onClick = [$plugin, $methodName];
        if($params !== null) {
            $this->onClick[] = $params;
        }
        $this->BStyle |= ISB_CLICK;
    }

				/**
					* @param IS_BTC $BTC
					*/
				public function click(IS_BTC $BTC)
    {
        if (!is_array($this->onClick))
            return;

        switch (count($this->onClick))
        {
            case 3:
                call_user_func_array([$this->onClick[0], $this->onClick[1]], $this->onClick[2]);
            break;
            case 2:
            default:
                call_user_func($this->onClick, $BTC, $this);
            break;
        }
    }

				/**
					* @param Plugins $plugin
					* @param $methodName
					* @param int $maxLength
					*/
				public function registerOnText(Plugins $plugin, $methodName, $maxLength = 95)
    {
        if ($maxLength < 0 || $maxLength > 95) {
            $this->TypeIn = 95;
        }
        else {
            $this->TypeIn = $maxLength;
        }
        $this->onText = [$plugin, $methodName];
        $this->BStyle |= ISB_CLICK;
    }

				/**
					* @param IS_BTT $BTT
					*/
				public function enterText(IS_BTT $BTT)
    {
        if (is_array($this->onText)) {
            call_user_func($this->onText, $BTT, $this);
        }
    }

				/**
					* @param null $hostId
					*/
				public function delete($hostId = NULL)
    {
        return ButtonManager::removeButton($this, $hostId);
    }

				/**
					* @return $this
					*/
				public function UCID()
    {
        console('ERROR: UCID may only be set in constructor!');
        return $this;
    }

				/**
					* @return $this
					*/
				public function ReqI()
    {
        console('ERROR: Do not set ReqI manually!');
        return $this;
    }

				/**
					* @return $this
					*/
				public function ClickID()
    {
        console('ERROR: Do not set ClickID manually!');
        return $this;
    }

				/**
					* @return null
					*/
				public function key()
    {
        return $this->key;
    }

				/**
					* @return null
					*/
				public function group()
    {
        return $this->group;
    }
}
