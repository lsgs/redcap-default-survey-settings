<?php
/**
 * REDCap External Module: Manage Default Survey Settings
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
if (!defined('SUPER_USER') || !SUPER_USER) exit;
if (is_null($module) || !($module instanceof MCRI\ManageSurveyDefaults\ManageSurveyDefaults)) exit;
include APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module->manageGlobalThemesPage();
include APP_PATH_DOCROOT . 'ControlCenter/footer.php';