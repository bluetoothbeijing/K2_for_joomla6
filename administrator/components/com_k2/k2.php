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

$params = JComponentHelper::getParams('com_k2');
$user = JFactory::getUser();

$option = JRequest::getCmd('option');
$view = JRequest::getCmd('view', 'items');
$task = JRequest::getCmd('task');
$tmpl = JRequest::getCmd('tmpl');
$context = JRequest::getCmd('context');

if (K2_JVERSION == '15') {
    if (
        ($params->get('lockTags') && $user->gid<=23 && ($view=='tags' || $view=='tag')) ||
        ($user->gid <= 23) && (
            $view=='extrafield' ||
            $view=='extrafields' ||
            $view=='extrafieldsgroup' ||
            $view=='extrafieldsgroups' ||
            $view=='user' ||
            ($view=='users' && $context != 'modalselector') ||
            $view=='usergroup' ||
            $view=='usergroups'
        )
    ) {
        JError::raiseError(403, JText::_('K2_ALERTNOTAUTH'));
    }
} else {
    JLoader::register('K2HelperPermissions', JPATH_SITE.'/administrator/components/com_k2/helpers/permissions.php');
    K2HelperPermissions::checkPermissions();

    // Compatibility for gid variable
    if ($user->authorise('core.admin', 'com_k2')) {
        @$user->gid = 1000; // K2 B/C: dynamic property, silenced for PHP 8.2+
    } else {
        @$user->gid = 1; // K2 B/C: dynamic property, silenced for PHP 8.2+
    }

    if (
        ($params->get('lockTags') && !$user->authorise('core.admin', 'com_k2') && ($view=='tags' || $view=='tag')) ||
        (!$user->authorise('core.admin', 'com_k2')) && (
            $view=='extrafield' ||
            $view=='extrafields' ||
            $view=='extrafieldsgroup' ||
            $view=='extrafieldsgroups' ||
            $view=='user' ||
            ($view=='users' && $context != 'modalselector') ||
            $view=='usergroup' ||
            $view=='usergroups'
        )
    ) {
        JError::raiseError(403, JText::_('K2_ALERTNOTAUTH'));
    }
}

// Load K2 Pagination class
require_once JPATH_COMPONENT.'/lib/k2pagination.php';

// Load K2 Global Helper
JLoader::register('K2GlobalHelper', JPATH_SITE.'/media/k2/assets/helpers/global.php');

// K2's view/model/helper classes (registered here as well so the component
// works even when the "System - K2" plugin is disabled)
JLoader::register('K2HelperHTML', JPATH_COMPONENT.'/helpers/html.php');
JLoader::register('K2HelperUtilities', JPATH_COMPONENT.'/helpers/utilities.php');

$document = JFactory::getDocument();

$document->setMetadata('theme-color', '#10223e');

K2HelperHTML::loadHeadIncludes(true, true);

// Container CSS class definition
if (K2_JVERSION == '15') {
    $k2CSSContainerClass = ' isJ15'; // oldJ isJ15
} elseif (K2_JVERSION == '25') {
    $k2CSSContainerClass = ' isJ25'; // oldJ isJ25
} elseif (K2_JVERSION == '30') {
    $k2CSSContainerClass = ' isJ30'; // isJ25 isJ30
} else {
    $k2CSSContainerClass = '';
}

if (JRequest::getCmd('context') == "modalselector" || ($view == 'media' && $tmpl == 'component') || $view == 'settings') {
    $k2CSSContainerClass .= ' inModalSelector';
    $k2FooterClass = 'inModalSelector';
} else {
    $k2FooterClass = '';
}

$editForms = array('item', 'category', 'tag', 'user', 'usergroup', 'extrafield', 'extrafieldsgroup');
if (in_array($view, $editForms)) {
    $k2CSSContainerClass .= ' isEditForm';
}

if (
    $document->getType() != 'raw' &&
    $task != 'deleteAttachment' &&
    $task != 'connector' &&
    $task != 'tag' &&
    $task != 'tags' &&
    $task != 'extraFields' &&
    $task != 'download' &&
    $task != 'saveComment' &&
    $context != 'ajax'
) {
    $k2ComponentHeader = '
    <div id="k2AdminContainer" class="K2AdminView'.ucfirst($view).$k2CSSContainerClass.'">
        <div id="k2ContentView">
    ';

    $k2ComponentFooter = '
            <div class="k2clr"></div>
        </div>
    </div>
    ';

    if ($user->gid > 24) {
        $k2ComponentFooter .= '
            <!-- K2 Update Service -->
            <script type="text/javascript">var K2_INSTALLED_VERSION = \''.K2_CURRENT_VERSION.'\';</script>
            <script type="text/javascript" src="https://updates.getk2.org/update.js?t='.date('Ymd_H').'"></script>
        ';
    }
} else {
    $k2ComponentHeader = '';
    $k2ComponentFooter = '';
}

// Output
echo $k2ComponentHeader;

JLoader::register('K2Controller', JPATH_COMPONENT.'/controllers/controller.php');
JLoader::register('K2View', JPATH_COMPONENT.'/views/view.php');
JLoader::register('K2Model', JPATH_COMPONENT.'/models/model.php');

// Resolve K2's model classes through BaseDatabaseModel::getInstance()
K2Model::addIncludePath(JPATH_COMPONENT.'/models');

$controller = strtolower($view);
require_once(JPATH_COMPONENT.'/controllers/'.$controller.'.php');
$classname = 'K2Controller'.ucfirst($controller);
$controller = new $classname();
$controller->registerTask('saveAndNew', 'save');
$controller->execute($task);
$controller->redirect();

echo $k2ComponentFooter;
