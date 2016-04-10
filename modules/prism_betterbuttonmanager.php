<?php
class betterButtonManager
{
    protected $UCID;
    protected $bTexts = array();
    protected $bState = array();
    protected $cData = array();

    /* public __construct($UCID) {{{ */
    /**
     * __construct
     *
     * @param mixed $UCID
     * @access public
     * @return void
     */
    public function __construct($UCID)
    {
        $this->UCID = $UCID;
    }
    /* }}} */

    /* public InitButton($Name, $Group, $T=0, $L=0, $W=1, $H=1, $BStyle=null, $Text='', $Expire = 0) {{{ */
    /**
     * InitButton
     *
     * @param mixed $Name
     * @param mixed $Group
     * @param int $T
     * @param int $L
     * @param int $W
     * @param int $H
     * @param bool $BStyle
     * @param string $Text
     * @param int $Expire
     * @access public
     * @return void
     */
    public function InitButton($Name, $Group, $T=0, $L=0, $W=1, $H=1, $BStyle=null, $Text='', $Expire = 0, $Repeat=true)
    {
        $OldButton = ButtonManager::getButtonForKey($this->UCID, $Name);
        if(get_class($OldButton) == 'Button') {
            $Button = $OldButton;
        } else {
            $Button = new Button($this->UCID, $Name, $Group);
        }
        $Button->T($T)->L($L)->W($W)->H($H);
        $Button->BStyle($BStyle);
        if(is_array($Text)) {
            $Button->Text($Text[0]);
            $this->bTexts[$Name] = $Text;
        } else {
            $Button->Text($Text);
            $this->bTexts[$Name][0] = $Text;
        }
        $Button->Send();

        $this->bState[$Name]['ID'] = 0;
        $this->bState[$Name]['timestamp'] = time() - 1;
        $this->bState[$Name]['override'] = false;
        if($Expire > 0) {
            $this->bState[$Name]['expire'] = time() + $Expire;
        } else {
            $this->bState[$Name]['expire'] = -1;
        }
        $this->bState[$Name]['repeatText'] = $Repeat;

    }
    /* }}} */

    /* public MoveGUIClusterV($Cluster, $Offset=0) {{{ */
    /**
     * MoveGUIClusterV
     *
     * @param mixed $Cluster
     * @param int $Offset
     * @access public
     * @return void
     */
    public function MoveGUIClusterV($Cluster, $Offset=0)
    {
        if(!isset($this->cData[$Cluster])) {
            return PLUGIN_CONTINUE; #Cluster Not Loaded
        }
        $this->cData[$Cluster]['Top'] += $Offset;
        $this->ReloadGUICluster($Cluster);
    }
    /* }}} */

    /* public MoveGUIClusterH($Cluster, $Offset=0) {{{ */
    /**
     * MoveGUIClusterH
     *
     * @param mixed $Cluster
     * @param int $Offset
     * @access public
     * @return void
     */
    public function MoveGUIClusterH($Cluster, $Offset=0)
    {
        if(!isset($this->cData[$Cluster])) {
            return PLUGIN_CONTINUE; #Cluster Not Loaded
        }
        $this->cData[$Cluster]['Left'] += $Offset;
        $this->ReloadGUICluster($Cluster);
    }
    /* }}} */

    /* public MoveGUIClusterD($Cluster, $OffsetT=0, $OffsetL=0) {{{ */
    /**
     * MoveGUIClusterD
     *
     * @param mixed $Cluster
     * @param int $OffsetT
     * @param int $OffsetL
     * @access public
     * @return void
     */
    public function MoveGUIClusterD($Cluster, $OffsetT=0, $OffsetL=0)
    {
        if(!isset($this->cData[$Cluster])) {
            return PLUGIN_CONTINUE; #Cluster Not Loaded
        }
        $this->cData[$Cluster]['Top'] += $OffsetT;
        $this->cData[$Cluster]['Left'] += $OffsetL;
        $this->ReloadGUICluster($Cluster);
    }
    /* }}} */

    /* public LoadGUICluster($Cluster, $Layout='default', $OffsetT=null, $OffsetL=null) {{{ */
    /**
     * LoadGUICluster
     *
     * @param mixed $Cluster
     * @param bool $Layout
     * @param bool $OffsetT
     * @param bool $OffsetL
     * @access public
     * @return void
     */
    public function LoadGUICluster($Cluster, $Layout='default', $OffsetT=null, $OffsetL=null)
    {
        $Design = parse_ini_file("data/GUI/{$Cluster}/{$Layout}.ini", true);
        if(!$Design){
            return PLUGIN_CONTINUE; // Design File Not Found
        }
        if($OffsetT == null || $OffsetL == null) {
            if(isset($Design['default'])){
                $OffsetT = $Design['default']['Top'];
                $OffsetL = $Design['default']['Left'];
            } else {
                return PLUGIN_CONTINUE; // Top or Left Not Specified; Can't draw GUI
            }
        }
        unset($Design['default']);
        $this->RemoveGroup($Cluster);
        $this->cData[$Cluster]['Layout'] = $Layout;
        $this->cData[$Cluster]['Top'] = $OffsetT;
        $this->cData[$Cluster]['Left'] = $OffsetL;
        foreach($Design as $BName => $BInfo)
        {
            $Style = 0;
            if(isset($BInfo['Style'])) {
                foreach($BInfo['Style'] as $StyleConst) {
                    $Style += $StyleConst;
                }
            }
            $this->InitButton($BName, $Cluster, $OffsetT+$BInfo['Top'], $OffsetL+$BInfo['Left'], $BInfo['Width'], $BInfo['Height'], $Style, ((isset($BInfo['Text'])) ? $BInfo['Text'] : ''));
            $this->OverRideButton($BName);
        }
    }
    /* }}} */

    /* public ReloadGUICluster($Cluster) {{{ */
    /**
     * ReloadGUICluster
     *
     * @param mixed $Cluster
     * @access public
     * @return void
     */
    public function ReloadGUICluster($Cluster){
        if(!isset($this->cData[$Cluster])) {
            return PLUGIN_CONTINUE; #Cluster Not Loaded
        }
        $Layout = $this->cData[$Cluster]['Layout'];
        $Top = $this->cData[$Cluster]['Top'];
        $Left = $this->cData[$Cluster]['Left'];
        $this->LoadGUICluster($Cluster, $Layout, $Top, $Left);
    }
    /* }}} */

    /* public GetGUIClusterInfo($Cluster) {{{ */
    /**
     * GetGUIClusterInfo
     *
     * @param mixed $Cluster
     * @access public
     * @return void
     */
    public function GetGUIClusterInfo($Cluster){
        if(!isset($this->cData[$Cluster])) {
            return PLUGIN_CONTINUE; #Cluster Not Loaded
        }
        return array($this->cData[$Cluster]);
    }
    /* }}} */

    /* public NextText($Name) {{{ */
    /**
     * NextText
     *
     * @param mixed $Name
     * @access public
     * @return void
     */
    public function NextText($Name)
    {
        $Button = ButtonManager::getButtonForKey($this->UCID, $Name);
        if(get_class($Button) != 'Button') {
            return PLUGIN_CONTINUE;
        }
        $this->bState[$Name]['ID']++;
        if($this->bState[$Name]['ID'] >= count($this->bTexts[$Name])) {
            if($this->bState[$Name]['repeatText']){
                $this->bState[$Name]['ID'] = 0;
            } else {
                $this->bState[$Name]['ID']--;
                return PLUGIN_CONTINUE;
            }
        }
        $Next = $this->bTexts[$Name][$this->bState[$Name]['ID']];
        if($Button->Text != $Next || $this->bState[$Name]['override'] == true) {
            $Button->Text($Next);
            $Button->Send();
            $this->bState[$Name]['timestamp'] = time();
            $this->bState[$Name]['override'] = false;
        }
    }
    /* }}} */
    public function HandleButtons() # Needs to be added to a timer on NCN
    {
        foreach($this->bState as $Name => $bState){
            if($bState['expire'] != -1 && $bState['expire'] <= time()) {
                $this->RemoveButton($Name);
                continue;
            }
            if($bState['timestamp'] + 1 <= time()) {
                $this->NextText($Name);
            }
        }
    }

    /* public OverRideButton($Name) {{{ */
    /**
     * OverRideButton
     *
     * @param mixed $Name
     * @access public
     * @return void
     */
    public function OverRideButton($Name)
    {
        if(!isset($this->bState[$Name])) {
            return PLUGIN_CONTINUE;
        }
        $this->bState[$Name]['override'] = true;
    }
    /* }}} */

    /* public RemoveButton($Name) {{{ */
    /**
     * RemoveButton
     *
     * @param mixed $Name
     * @access public
     * @return void
     */
    public function RemoveButton($Name)
    {
        if(!isset($this->bState[$Name])) {
            return PLUGIN_CONTINUE;
        }
        ButtonManager::removeButtonByKey($this->UCID, $Name);
        unset($this->bTexts[$Name]);
        unset($this->bState[$Name]);
    }
    /* }}} */

    /* public RemoveGroup($Group) {{{ */
    /**
     * RemoveGroup
     *
     * @param mixed $Group
     * @access public
     * @return void
     */
    public function RemoveGroup($Group)
    {
        foreach(ButtonManager::getButtonsForGroup($this->UCID, $Group) as $button)
            $this->RemoveButton($button->key());
    }
    /* }}} */

    /* public AddTextToBtn($Name, $Text) {{{ */
    /**
     * AddTextToBtn
     *
     * @param mixed $Name
     * @param mixed $Text
     * @access public
     * @return void
     */
    public function AddTextToBtn($Name, $Text)
    {
        if(!isset($this->bTexts[$Name])) {
            return PLUGIN_CONTINUE;
        }
        $this->bTexts[$Name][] = $Text;
        return count($this->bTexts[$Name]);
    }
    /* }}} */

    /* public UpdateTextOnBtn($Name, $Text='', $ID=0, $Override=false) {{{ */
    /**
     * UpdateTextOnBtn
     *
     * @param mixed $Name
     * @param string $Text
     * @param int $ID
     * @param bool $Override
     * @access public
     * @return void
     */
    public function UpdateTextOnBtn($Name, $Text='', $ID=0, $Override=false)
    {
        if(!isset($this->bState[$Name])) {
            return PLUGIN_CONTINUE;
        }
        $this->bTexts[$Name][$ID] = $Text;
        $this->bState[$Name]['override'] = $Override;
    }
    /* }}} */

    /* public AddClickEventToBtn($Name, $Class, $Event=null, $Params=null) {{{ */
    /**
     * AddClickEventToBtn
     *
     * @param mixed $Name
     * @param mixed $Class
     * @param mixed $Function
     * @param mixed $Params
     * @access public
     * @return void
     */
    public function AddClickEventToBtn($Name, $Class, $Function, $Params=null)
    {
        if($Function == null) {
            return PLUGIN_CONTINUE;
        }
        $Button = ButtonManager::getButtonForKey($this->UCID, $Name);
        $Button->registerOnClick($Class, $Function, $Params);
        $Button->Send();
    }
    /* }}} */

    /* public AddTextEventToBtn($Name, $Class, $Event=null, $Length=95) {{{ */
    /**
     * AddTextEventToBtn
     *
     * @param mixed $Name
     * @param mixed $Class
     * @param bool $Event
     * @param int $Length
     * @access public
     * @return void
     */
    public function AddTextEventToBtn($Name, $Class, $Event=null, $Length=95)
    {
        if($Event == null) {
            return PLUGIN_CONTINUE;
        }
        $Button = ButtonManager::getButtonForKey($this->UCID, $Name);
        $Button->registerOnText($Class, $Event, $Length);
        $Button->Send();
    }
    /* }}} */

    /* public __destruct() {{{ */
    /**
     * __destruct
     *
     * @access public
     * @return void
     */
    public function __destruct()
    {
        $this->bTexts = array();
        $this->bState = array();
        $this->cData = array();
        ButtonManager::clearButtonsForConn($this->UCID);
    }
    /* }}} */
}
