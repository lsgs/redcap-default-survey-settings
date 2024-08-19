<?php
/**
 * REDCap External Module: Manage Default Survey Settings
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\ManageSurveyDefaults;

trait JsonSerializer {
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}

class SurveyTheme implements \JsonSerializable 
{
    use JsonSerializer; // https://stackoverflow.com/questions/28557446/json-encode-php-objects-with-their-protected-properties

    public static $ColourFields = array("theme_text_buttons","theme_bg_page","theme_text_title","theme_bg_title","theme_text_sectionheader","theme_bg_sectionheader","theme_text_question","theme_bg_question");
    protected $id;
    protected $theme_name;
    protected $active;
    protected $editable;
    protected $theme_bg_page;
    protected $theme_text_buttons;
    protected $theme_bg_title;
    protected $theme_text_title;
    protected $theme_bg_sectionheader;
    protected $theme_text_sectionheader;
    protected $theme_bg_question;
    protected $theme_text_question;

    public function __construct() {
    }

    public static function constructThemeFromId($id, ManageSurveyDefaults $module) 
    {
        $instance = new self();
        $instance->id = intval($id);

        $inactiveThemes = $module->getSystemSetting('global-theme-hidden') ?? array();
        $instance->active = !in_array((string)$instance->id, $inactiveThemes, true);

        $editableThemes = $module->getSystemSetting('global-theme-editable') ?? array();
        $instance->editable = in_array($instance->id, $editableThemes);

        $instance->setThemeProperties();
        return $instance;
    }

    public static function constructThemeFromArray(array $themeProperties) 
    {
        $instance = static::constructNewTheme();
        foreach(get_object_vars($instance) as $prop => $defaultVal) {
            if (array_key_exists($prop, $themeProperties)) {
                $instance->$prop = $themeProperties[$prop];
            }
        }
        return $instance;
    }

    public static function constructNewTheme() 
    {
        $instance = new self();
        $instance->id = null;
        $instance->setDefaultThemeProperties();
        $instance->theme_name = \RCView::tt('survey_1016',false); // Survey theme
        $instance->active = true;
        $instance->editable = true;
        return $instance;
    }

    protected function setThemeProperties()
    {
        if ($this->id==0) {
            $this->setDefaultThemeProperties();
            return;
        }
        $propArray = \Survey::getPresetThemes($this->id);
        if (empty($propArray)) {
            $this->setDefaultThemeProperties();
            return;
        }

        unset($propArray['theme_id']);
        unset($propArray['ui_id']);

        foreach ($propArray as $key => $value) {
            $this->$key = $value;
        }
    }

    protected function setDefaultThemeProperties()
    {
        $this->theme_name = \RCView::tt('survey_1017',false); // Default
        $this->editable = false; // Default theme settings are hardcoded in Survey::renderSurveyThemeDropdown()
        $this->theme_text_buttons = '#000000';
        $this->theme_bg_page = '#1A1A1A';
        $this->theme_text_title = '#000000';
        $this->theme_bg_title = '#FFFFFF';
        $this->theme_text_sectionheader = '#000000';
        $this->theme_bg_sectionheader = '#BCCFE8';
        $this->theme_text_question = '#000000';
        $this->theme_bg_question = '#F3F3F3';
    }

    public function setThemeProperty($property, $value)
    {
        if (!property_exists($this, $property)) return;
        
        if ($property === 'id') {
            $this->id = intval($value);
        } else if ($property === 'theme_name') {
            $this->theme_name = \htmlspecialchars((string)$value, ENT_QUOTES);
        } else if ($property==='active' || $property==='editable') {
            $this->$property = (bool)$value;
        } else if (starts_with($property, 'theme_')) {
			$regex_color = "/#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\b/";
			if (preg_match($regex_color, $value)) {
                $this->$property = $value;
            } else {
                $this->$property = '#FF0000'; // red, naturally
            }
        }
    }

    public function saveTheme(ManageSurveyDefaults $module)
    {
        if (is_null($this->id) || $this->id=='') {
            // Add new theme
            $sql = "insert into redcap_surveys_themes (theme_name, ui_id, theme_bg_page, theme_text_buttons, theme_text_title, theme_bg_title, theme_text_question, theme_bg_question, theme_text_sectionheader, theme_bg_sectionheader)
		            values (?, null, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array(
                $this->theme_name,
                str_replace('#','',$this->theme_bg_page),
                str_replace('#','',$this->theme_text_buttons),
                str_replace('#','',$this->theme_text_title),
                str_replace('#','',$this->theme_bg_title),
                str_replace('#','',$this->theme_text_question),
                str_replace('#','',$this->theme_bg_question),
                str_replace('#','',$this->theme_text_sectionheader),
                str_replace('#','',$this->theme_bg_sectionheader)
            );
            $q = $module->query($sql, $params);
            if($error = db_error()){
                throw new \Exception('Add new theme failed: '.$error);
            }
            $this->id = db_insert_id();
            $display = '';
            foreach(get_object_vars($this) as $prop => $val) {
                $display .= "$prop = $val \n";
            }

            if ($this->editable) {
                $editableThemes = $module->getSystemSetting('global-theme-editable') ?? array();
                $editableThemes[] = $this->id;
                $module->setSystemSetting('global-theme-editable', $editableThemes);
            }

            \Logging::logEvent("SQL: $sql \nParams: ".\json_encode($params), 'redcap_surveys_themes','OTHER',$this->id,$display,'Add new survey theme');

        } else {
            // Edit existing theme - see what's changed
            $currentTheme = self::constructThemeFromId($this->id, $module);
            $changedProperties = array();
            $display = '';

            foreach(get_object_vars($this) as $prop => $val) {
                if ($val != $currentTheme->$prop) {
                    $changedProperties[$prop] = $val;
                }
            }

            if (empty($changedProperties)) throw new \Exception('No changes to save');

            if (array_key_exists('editable', $changedProperties)) {
                $val = $changedProperties['editable'];
                $editableThemes = $module->getSystemSetting('global-theme-editable') ?? array();
                if ($val) {
                    $nullValKey = array_search(null, $editableThemes);
                    if ($nullValKey===false) {
                        $editableThemes[] = $this->id;
                    } else {
                        $editableThemes[$nullValKey] = $this->id;
                    }
                } else if (in_array($val, $editableThemes)) {
                    $key = array_search($val, $editableThemes);
                    unset($editableThemes[$key]);
                    $editableThemes = array_values($editableThemes);
                }
                $module->setSystemSetting('global-theme-editable', $editableThemes);
                $display .= "editable = $val \n";
                unset($changedProperties['editable']);
            }
            if (array_key_exists('active', $changedProperties)) {
                $val = $changedProperties['active'];
                $inactiveThemes = $module->getSystemSetting('global-theme-hidden') ?? array();
                if ($val && in_array($val, $inactiveThemes)) {
                    $key = array_search($val, $inactiveThemes);
                    unset($inactiveThemes[$key]);
                    $inactiveThemes = array_values($inactiveThemes);
                } else {
                    $nullValKey = array_search(null, $inactiveThemes);
                    if ($nullValKey===false) {
                        $inactiveThemes[] = $this->id;
                    } else {
                        $inactiveThemes[$nullValKey] = $this->id;
                    }
                }
                $module->setSystemSetting('global-theme-hidden', $inactiveThemes);
                $display .= "active = $val \n";
                unset($changedProperties['active']);
            }

            reset($changedProperties);

            if (!empty($changedProperties)) {
                $sql = "update redcap_surveys_themes set theme_id = ?";
                $params = array($this->id);
                foreach($changedProperties as $prop => $val) {
                    $val = (in_array($prop, static::$ColourFields)) ? str_replace('#','',$val) : $val;
                    $sql .= ", $prop = ?";
                    $params[] = $val;
                    $display .= "$prop = $val \n";
                }
                $sql .= " where theme_id = ? limit 1";
                $params[] = $this->id;

                $q = $module->query($sql, $params);
                if($error = db_error()){
                    throw new \Exception('Theme save failed: '.$error);
                }

                \Logging::logEvent("SQL: $sql \nParams: ".\json_encode($params),'redcap_surveys_themes','OTHER',$this->id,$display,'Edit survey theme');
            }
        }
    }
}