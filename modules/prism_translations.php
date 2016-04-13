<?php
class Translations extends Timers
{
    /* Translation Engine Stuff */
    private $lang_subdirectory;
    private $lang_fallback;
    
    protected function setTranslationSettings($directory, $fallback = 'en') {
        $this->lang_subdirectory = $directory;
        $this->lang_fallback = $fallback;
    }

    protected function clearTranslationCache()
    {
        translateEngine::clearCache($this->lang_subdirectory);
    }

    protected function translateGlobalMessage($messageID, $args = array(), $hostID = null)
    {
        if (($clients = $this->getHostState($hostID)->clients) && $clients !== null) {
            foreach ($clients as $UCID => $client) {
                $this->translatePrivateMessage($UCID, $messageID, $args, $hostID);
            }
        }
    }

    protected function translatePrivateMessage($UCID, $messageID, $args = array(), $hostID = null)
    {
        $MTC = IS_MTC()->UCID($UCID);
        $MTC->Text($this->translateText($UCID, $messageID, $args, $hostID = null));
        $MTC->send($hostID);
    }

    protected function translateText($UCID, $messageID, $args = array(), $hostID = null)
    {
        if(!isset($this->lang_subdirectory)){
            console('Calling plugin does not have it\'s language directory specifed.');
            return 'Calling plugin does not have it\'s language directory specifed.';
        }
        $languageID = $this->getClientByUCID($UCID, $hostID)->Language;
        return translateEngine::translate($this->lang_subdirectory, $languageID, $messageID, $args, $this->lang_fallback);
    }
}

class translateEngine {
    private static $langCache = array();

    public static function clearCache($lang_subdirectory){
        console('TranslationEngine: ', false, 'red');
        console("Clearing Cache for '{$lang_subdirectory}' plugin");
        unset(self::$langCache[$lang_subdirectory]);
    }

    public static function translate($lang_subdirectory, $languageID, $messageID, $args = array(), $fallback = LANG_EN)
    {
        global $LANG;

        if(!isset($LANG[$languageID])){
            if($languageID !== $fallback) {
                return self::translate($lang_subdirectory, $fallback, $messageID, $args, $fallback);
            } else {
                return "Unable to translate due to unknown language being selected. '$languageID'";
            }
        }

        if(!isset(self::$langCache[$lang_subdirectory][$languageID])) 
        {
            console('TranslationEngine: ', false, 'red');
            console("Loading {$LANG[$languageID]} for {$lang_subdirectory}");

            $lang_folder = ROOTPATH . "/data/langs/{$lang_subdirectory}";
            if(!is_readable($lang_folder)){
                console("Language Folder for {$lang_subdirectory} is missing or not readable.");
                return "Language Folder for {$lang_subdirectory} is missing or not readable.";
            }
            $lang_file = "{$lang_folder}/{$LANG[$languageID]}.ini";
            if(is_readable($lang_file)){
                self::$langCache[$lang_subdirectory][$languageID] = parse_ini_file ($lang_file);
            } else {
                $lang_file = "{$lang_folder}/{$LANG[$fallback]}.ini";
                if(is_readable($lang_file)){
                    self::$langCache[$lang_subdirectory][$languageID] = parse_ini_file ($lang_file);
                    console("Language File for {$LANG[$languageID]} in {$lang_subdirectory} is missing or not readable.");
                } else {
                    console("Language File for {$LANG[$languageID]} in {$lang_subdirectory} is missing or not readable. No Fallback Available.");
                    return "Language File for {$LANG[$languageID]} in {$lang_subdirectory} is missing or not readable. No Fallback Available.";
                }
            }
        }

        if(isset(self::$langCache[$lang_subdirectory][$languageID][$messageID])){
            return vsprintf ( self::$langCache[$lang_subdirectory][$languageID][$messageID], $args);
        } else {
            console("Missing Language Entry: {$messageID} in {$LANG[$languageID]}");
            if($languageID != $fallback) {
                return self::translate($lang_subdirectory, $fallback, $messageID, $args, $fallback);
            } else {
                return "Missing Language Entry: {$messageID} in {$LANG[$languageID]}";
            }
        }
    }
}
