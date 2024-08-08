<?php
/**
 * REDCap External Module: Manage Default Survey Settings
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\DefaultSurveySettings;

use ExternalModules\AbstractExternalModule;

class DefaultSurveySettings extends AbstractExternalModule
{
    public function redcap_every_page_top($project_id=null) 
    {
        if (!defined('PAGE' )) return;
        if (PAGE!='Surveys/create_survey.php') return;

    }
}