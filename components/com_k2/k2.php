<?php
/**
 * @version    2.x (rolling release)
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2009 - 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL: https://gnu.org/licenses/gpl.html
 */

// no direct access
defined('_JEXEC') or die;

// K2 compatibility layer for Joomla! 4/5/6 (must run before any J-prefixed class is used)
require_once JPATH_ADMINISTRATOR . '/components/com_k2/helpers/compat.php';

if (K2_JVERSION != '15') {
    $user = JFactory::getUser();
    if ($user->authorise('core.admin', 'com_k2')) {
        @$user->gid = 1000; // K2 B/C: dynamic property, silenced for PHP 8.2+
    } else {
        @$user->gid = 1; // K2 B/C: dynamic property, silenced for PHP 8.2+
    }
}

JLoader::register('K2Controller', JPATH_COMPONENT.'/controllers/controller.php');
JLoader::register('K2View', JPATH_COMPONENT_ADMINISTRATOR.'/views/view.php');
JLoader::register('K2Model', JPATH_COMPONENT_ADMINISTRATOR.'/models/model.php');
JLoader::register('K2HelperHTML', JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html.php');

// Make BaseDatabaseModel::getInstance() able to resolve K2's model classes even
// when the "System - K2" plugin is disabled.
K2Model::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/models');
K2Model::addIncludePath(JPATH_COMPONENT.'/models');

JLoader::register('K2HelperRoute', JPATH_COMPONENT.'/helpers/route.php');
JLoader::register('K2HelperPermissions', JPATH_COMPONENT.'/helpers/permissions.php');
JLoader::register('K2HelperUtilities', JPATH_COMPONENT.'/helpers/utilities.php');
JLoader::register('K2GlobalHelper', JPATH_SITE.'/media/k2/assets/helpers/global.php');

K2HelperPermissions::setPermissions();
K2HelperPermissions::checkPermissions();

$controller = JRequest::getWord('view', 'itemlist');
$task = JRequest::getWord('task');

if ($controller == 'media') {
    $controller = 'item';
    if ($task != 'connector') {
        $task = 'media';
    }
}

if ($controller == 'users') {
    $controller = 'item';
    $task = 'users';
}

// Push K2's frontend CSS/JS to the document. Historically this was done by the
// "System - K2" plugin (onAfterRoute); doing it here as well means the styling
// survives when that plugin is disabled. The add/edit forms load their own set
// of assets further down the stack, so they are skipped here.
if (!K2HelperHTML::$siteHeadLoaded && $task != 'add' && $task != 'edit') {
    // K2 keeps the date() compatible formats in a separate language file that
    // used to be loaded by the "System - K2" plugin. Without it the frontend
    // falls back to com_k2.ini's legacy strftime() strings and Joomla! 4+
    // renders them literally ("%A, %d %B %Y"), so load it here too.
    K2Compat::loadLanguage('com_k2.dates', JPATH_ADMINISTRATOR, null, true);

    K2HelperHTML::loadHeadIncludes();
}

jimport('joomla.filesystem.file');
jimport('joomla.html.parameter');

if (JFile::exists(JPATH_COMPONENT.'/controllers/'.$controller.'.php')) {
    $classname = 'K2Controller'.$controller;
    if (!class_exists($classname)) {
        require_once(JPATH_COMPONENT.'/controllers/'.$controller.'.php');
    }
    $controller = new $classname();
    $controller->execute($task);
    $controller->redirect();
} else {
    JError::raiseError(404, JText::_('K2_NOT_FOUND'));
}

if (JRequest::getCmd('format') != 'json') {
    echo "\n<!-- JoomlaWorks \"K2\" (v".K2_CURRENT_VERSION.") | Learn more about K2 at https://getk2.org -->\n\n";
}
