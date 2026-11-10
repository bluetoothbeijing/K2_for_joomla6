<?php
/**
 * @version    2.x (rolling release)
 * @package    K2
 * @author     JoomlaWorks https://www.joomlaworks.net
 * @copyright  Copyright (c) 2009 - 2026 JoomlaWorks Ltd. All rights reserved.
 * @license    GNU/GPL: https://gnu.org/licenses/gpl.html
 *
 * K2 compatibility layer for Joomla! 5.x / 6.x
 * ------------------------------------------------------------------
 * Joomla 5 removed the "libraries/classmap.php" file and Joomla 6 no longer
 * ships the behaviour/compat class map by default. All of the J-prefixed
 * class names K2 was written against (JFactory, JText, JRequest, ...) are
 * therefore undefined on a stock Joomla 6 installation.
 *
 * This file re-creates the ones K2 needs. Classes that still exist in the
 * Joomla CMS are mapped with class_alias() so K2 talks to the real core
 * implementation. Classes that were dropped from the CMS get a small
 * compatibility implementation further down in this file.
 *
 * The file is defensive: every alias is created only when the target class
 * exists and the alias itself is not defined yet, so it stays harmless if
 * the "Behaviour - Backward Compatibility" plugin happens to be enabled.
 */

defined('_JEXEC') or die;

if (defined('K2_J6_COMPAT_LOADED')) {
    return;
}

define('K2_J6_COMPAT_LOADED', 1);

/*
 * ------------------------------------------------------------------
 * 0. Constants normally defined by the "System - K2" plugin
 * ------------------------------------------------------------------
 * The component, the modules and the plugins read K2_JVERSION and
 * K2_CURRENT_VERSION in hundreds of places. When the system plugin is
 * disabled (or while the installer runs) those would be undefined
 * constants, which is a fatal error on PHP 8. These definitions are
 * fallbacks only - the plugin defines the same values first.
 */
if (!defined('K2_JVERSION')) {
    define('K2_JVERSION', version_compare(JVERSION, '3.0', 'ge') ? '30' : '25');
}

if (!defined('K2_CURRENT_VERSION')) {
    define('K2_CURRENT_VERSION', '2.28');
}

/*
 * Legacy request / router constants
 * ------------------------------------------------------------------
 * Joomla! 4 removed these together with JRequest and the old JRouter.
 * K2 still passes JREQUEST_ALLOWRAW to JRequest::getVar() and compares
 * against JROUTER_MODE_SEF. Undefined constants are a fatal error in
 * PHP 8, so they are restored with their original values.
 */
if (!defined('JREQUEST_NOTRIM')) {
    define('JREQUEST_NOTRIM', 1);
}

if (!defined('JREQUEST_ALLOWRAW')) {
    define('JREQUEST_ALLOWRAW', 2);
}

if (!defined('JREQUEST_ALLOWHTML')) {
    define('JREQUEST_ALLOWHTML', 4);
}

if (!defined('JROUTER_MODE_RAW')) {
    define('JROUTER_MODE_RAW', 0);
}

if (!defined('JROUTER_MODE_SEF')) {
    define('JROUTER_MODE_SEF', 1);
}

/*
 * jexit() - still shipped by Joomla 4/5/6 in libraries/loader.php, but the
 * K2 controllers call it in "or jexit()" chains, so make sure a missing
 * helper can never turn a token check into a fatal error.
 */
if (!function_exists('jexit')) {
    function jexit($message = 0)
    {
        exit($message);
    }
}

/*
 * ------------------------------------------------------------------
 * 1. Aliases for classes that are still part of the Joomla CMS
 * ------------------------------------------------------------------
 */
$k2CompatAliases = [
    'JFactory'         => 'Joomla\\CMS\\Factory',
    'JText'            => 'Joomla\\CMS\\Language\\Text',
    'JHtml'            => 'Joomla\\CMS\\HTML\\HTMLHelper',
    'JTable'           => 'Joomla\\CMS\\Table\\Table',
    'JModelLegacy'     => 'Joomla\\CMS\\MVC\\Model\\BaseDatabaseModel',
    'JModel'           => 'Joomla\\CMS\\MVC\\Model\\BaseDatabaseModel',
    'JViewLegacy'      => 'Joomla\\CMS\\MVC\\View\\HtmlView',
    'JView'            => 'Joomla\\CMS\\MVC\\View\\HtmlView',
    'JControllerLegacy' => 'Joomla\\CMS\\MVC\\Controller\\BaseController',
    'JController'      => 'Joomla\\CMS\\MVC\\Controller\\BaseController',
    'JPlugin'          => 'Joomla\\CMS\\Plugin\\CMSPlugin',
    'JPluginHelper'    => 'Joomla\\CMS\\Plugin\\PluginHelper',
    'JRegistry'        => 'Joomla\\Registry\\Registry',
    'JForm'            => 'Joomla\\CMS\\Form\\Form',
    'JFormField'       => 'Joomla\\CMS\\Form\\FormField',
    'JUser'            => 'Joomla\\CMS\\User\\User',
    'JAccess'          => 'Joomla\\CMS\\Access\\Access',
    'JComponentHelper' => 'Joomla\\CMS\\Component\\ComponentHelper',
    'JLanguage'        => 'Joomla\\CMS\\Language\\Language',
    'JLanguageHelper'  => 'Joomla\\CMS\\Language\\LanguageHelper',
    'JSession'         => 'Joomla\\CMS\\Session\\Session',
    'JDocument'        => 'Joomla\\CMS\\Document\\Document',
    'JRoute'           => 'Joomla\\CMS\\Router\\Route',
    'JUri'             => 'Joomla\\CMS\\Uri\\Uri',
    'JDate'            => 'Joomla\\CMS\\Date\\Date',
    'JPagination'      => 'Joomla\\CMS\\Pagination\\Pagination',
    'JToolBarHelper'   => 'Joomla\\CMS\\Toolbar\\ToolbarHelper',
    'JToolBar'         => 'Joomla\\CMS\\Toolbar\\Toolbar',
    'JInstaller'       => 'Joomla\\CMS\\Installer\\Installer',
    'JHelperTags'      => 'Joomla\\CMS\\Helper\\TagsHelper',
    'JModuleHelper'    => 'Joomla\\CMS\\Helper\\ModuleHelper',
    'JString'          => 'Joomla\\String\\StringHelper',
    'JArrayHelper'     => 'Joomla\\Utilities\\ArrayHelper',
    // JFilterOutput is NOT aliased on purpose - see the shim class below.
    'JFilterInput'     => 'Joomla\\CMS\\Filter\\InputFilter',
    'JFile'            => 'Joomla\\Filesystem\\File',
    'JFolder'          => 'Joomla\\Filesystem\\Folder',
    'JPath'            => 'Joomla\\Filesystem\\Path',
    'JMailer'          => 'Joomla\\CMS\\Mail\\Mail',
    'JCacheController' => 'Joomla\\CMS\\Cache\\Controller\\CallbackController',
];

foreach ($k2CompatAliases as $k2Alias => $k2Target) {
    // Autoload the target FIRST: core files (and template framework overrides
    // such as T3/T4) register the J-prefixed alias themselves while loading,
    // so checking the alias afterwards avoids "Cannot redeclare class JText"
    // warnings on every page.
    if (!class_exists($k2Target)) {
        continue;
    }

    if (class_exists($k2Alias, false)) {
        continue;
    }

    class_alias($k2Target, $k2Alias);
}

unset($k2CompatAliases, $k2Alias, $k2Target);

/*
 * Make sure Table::getInstance('K2Item', 'Table') can find K2's table classes
 * even when it is called outside of a model context.
 */
if (class_exists('Joomla\\CMS\\Table\\Table') && defined('JPATH_ADMINISTRATOR')) {
    $k2TablesPath = JPATH_ADMINISTRATOR . '/components/com_k2/tables';

    if (is_dir($k2TablesPath)) {
        \Joomla\CMS\Table\Table::addIncludePath($k2TablesPath);

        // Table::getInstance() includes "k2item.php" and friends directly, and
        // those files extend K2Table - so the base class must be loaded first.
        if (!class_exists('K2Table', false) && is_file($k2TablesPath . '/table.php')) {
            require_once $k2TablesPath . '/table.php';
        }
    }
}

/*
 * Register K2's helper classes as autoload fallbacks. In Joomla 3 the
 * "System - K2" plugin did this during onAfterInitialise - but a freshly
 * installed plugin is DISABLED, and backend modules (mod_k2_stats,
 * mod_k2_quickicons) render on the control panel before the user ever gets
 * the chance to enable it. Registering here (compat.php is loaded by every
 * K2 entry point) removes the dependency on the plugin state.
 */
if (class_exists('JLoader') && defined('JPATH_SITE')) {
    // MVC base classes (JPATH_BASE picks the front/back controller like the
    // "System - K2" plugin used to)
    \JLoader::register('K2Controller', JPATH_BASE . '/components/com_k2/controllers/controller.php');
    \JLoader::register('K2Model', JPATH_ADMINISTRATOR . '/components/com_k2/models/model.php');
    \JLoader::register('K2View', JPATH_ADMINISTRATOR . '/components/com_k2/views/view.php');

    // Helpers
    \JLoader::register('K2HelperHTML', JPATH_ADMINISTRATOR . '/components/com_k2/helpers/html.php');
    \JLoader::register('K2HelperUtilities', JPATH_SITE . '/components/com_k2/helpers/utilities.php');
    \JLoader::register('K2HelperRoute', JPATH_SITE . '/components/com_k2/helpers/route.php');
    \JLoader::register('K2HelperPermissions', JPATH_SITE . '/components/com_k2/helpers/permissions.php');
    \JLoader::register('K2GlobalHelper', JPATH_SITE . '/media/k2/assets/helpers/global.php');
    \JLoader::register('K2String', JPATH_SITE . '/media/k2/assets/helpers/string.php');
}

/*
 * Register K2's custom form field types (k2category, k2tags, k2users,
 * k2modalselector, ...) globally. K2's manifests declare addfieldpath, which
 * newer Joomla versions only honour for the form instance that requested it;
 * registering the folder here makes the types available everywhere (component
 * options, module parameters, K2 item form).
 */
if (class_exists('Joomla\\CMS\\Form\\Form') && method_exists('Joomla\\CMS\\Form\\Form', 'addFieldPath')) {
    $k2ElementsPath = JPATH_ADMINISTRATOR . '/components/com_k2/elements';

    if (is_dir($k2ElementsPath)) {
        try {
            \Joomla\CMS\Form\Form::addFieldPath($k2ElementsPath);
        } catch (\Throwable $e) {
            // Ignore - the manifests still declare addfieldpath themselves
        }
    }

    unset($k2ElementsPath);
}

/*
 * ------------------------------------------------------------------
 * 2. K2Compat - small helpers used by the shims below
 * ------------------------------------------------------------------
 */
if (!class_exists('K2Compat')) {
    final class K2Compat
    {
        /**
         * Terminate the request the way JApplication::close() used to.
         *
         * @param   integer  $code  Exit code
         *
         * @return  void
         */
        public static function close($code = 0)
        {
            // Flush any output buffers that belong to the component so the
            // JSON/HTML we produced is actually sent before we stop.
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            exit((int) $code);
        }

        /**
         * Read the parameters of a menu item the way Joomla! 3/4/5/6 all accept.
         *
         * Joomla! 4+ made MenuItem::$params protected (it must be read through
         * the getParams() getter), so any leftover `$item->params` in K2 code
         * now fails with "Cannot access protected property ...::$params".
         *
         * @param   mixed  $item  A menu item (Joomla\CMS\Menu\MenuItem) or similar object
         *
         * @return  \Joomla\Registry\Registry
         */
        public static function menuItemParams($item)
        {
            if (is_object($item) && method_exists($item, 'getParams')) {
                try {
                    $params = $item->getParams();

                    if ($params instanceof \Joomla\Registry\Registry) {
                        return $params;
                    }

                    return new \Joomla\Registry\Registry($params);
                } catch (\Throwable $e) {
                    return new \Joomla\Registry\Registry();
                }
            }

            // Joomla! 3 stores the raw JSON string in a public property.
            $raw = '';

            if (is_object($item) && isset($item->params)) {
                $raw = $item->params;
            } elseif (is_array($item) && isset($item['params'])) {
                $raw = $item['params'];
            }

            return new \Joomla\Registry\Registry($raw);
        }

        /**
         * Load jQuery so K2's own (classic, synchronous) scripts can use it.
         *
         * Joomla! 4+ serves jQuery through the WebAssetManager with the
         * "defer" attribute. Deferred scripts are executed only after the
         * document has been parsed, but K2's scripts are printed as classic
         * <script> tags by JDocument::addScript() and run while the document
         * is still being parsed - they would therefore hit "jQuery is not
         * defined". Loading the very same jQuery build synchronously keeps
         * the Joomla! 3 execution order intact. The asset manager version is
         * used as a fallback when the local file is missing.
         *
         * @return  void
         */
        public static function loadJquery()
        {
            static $done = false;

            if ($done) {
                return;
            }

            $done = true;

            try {
                $document = \Joomla\CMS\Factory::getApplication()->getDocument();
            } catch (\Throwable $e) {
                return;
            }

            $local = JPATH_ROOT . '/media/vendor/jquery/js/jquery.min.js';

            if (is_file($local)) {
                // addScript() de-duplicates by src, so calling it from several
                // K2 entry points is safe.
                $document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/media/vendor/jquery/js/jquery.min.js');

                return;
            }

            try {
                $document->getWebAssetManager()->useScript('jquery');
            } catch (\Throwable $e) {
                // Nothing else we can do - K2 scripts will degrade gracefully
            }
        }

        /**
         * Load Joomla's core JavaScript (Joomla.submitbutton, Joomla.tableOrdering, ...).
         *
         * Joomla 4's JHtml::_('behavior.framework') loaded jQuery *and* core.js.
         * K2's list views rely on Joomla.tableOrdering() for column sorting and
         * on Joomla.submitbutton() for the toolbar buttons, so the shim has to
         * keep loading the core assets as well.
         *
         * @return  void
         */
        public static function loadCoreAssets()
        {
            static $done = false;

            if ($done) {
                return;
            }

            $done = true;

            try {
                $wa = \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager();
            } catch (\Throwable $e) {
                return;
            }

            foreach (['core', 'tableordering'] as $asset) {
                try {
                    $wa->useScript($asset);
                } catch (\Throwable $e) {
                    // Asset not available in this Joomla version - ignore
                }
            }
        }

        /**
         * Load a Bootstrap based modal implementation.
         *
         * @return  void
         */
        public static function loadModal()
        {
            try {
                $wa = \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager();
                $wa->useScript('bootstrap.modal');
            } catch (\Throwable $e) {
                // Ignore - the shim script ships its own lightweight modal
            }
        }

        /**
         * Verify an e-mail address (replacement for JMailHelper::isEmailAddress()).
         *
         * @param   string  $email  The address to check
         *
         * @return  boolean
         */
        public static function isEmailAddress($email)
        {
            if (!is_string($email) || $email === '') {
                return false;
            }

            return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        }

        /**
         * Return a Joomla 3 compatible editor object.
         *
         * Joomla! 4 dropped Factory::getEditor() together with the JEditor
         * class. K2 only needs display() and save(), which K2Editor provides
         * on top of the current \Joomla\CMS\Editor\Editor.
         *
         * @param   string  $editor  Optional editor name
         *
         * @return  K2Editor
         */
        public static function getEditor($editor = null)
        {
            if (!class_exists('K2Editor')) {
                return null;
            }

            return new K2Editor($editor);
        }

        /**
         * Build the proper event object for a legacy trigger() call.
         *
         * Joomla! 4+ core plugins declare TYPED event signatures, e.g.
         * Fields::onContentPrepare(ContentPrepareEvent $event). Dispatching a
         * generic \Joomla\Event\Event for a core event name therefore throws
         * "Argument #1 must be of type ...Event, Joomla\Event\Event given".
         * This method maps the legacy positional arguments to the concrete
         * event class Joomla core uses for each event name.
         *
         * @param   string  $eventName  Event name
         * @param   array   $arguments  Legacy positional arguments
         *
         * @return  \Joomla\Event\EventInterface
         */
        public static function createEvent($eventName, $arguments)
        {
            $arguments = array_values((array) $arguments);

            static $map = [
                // Content events (Joomla\CMS\Event\Content)
                'onContentPrepare'       => ['Joomla\\CMS\\Event\\Content\\ContentPrepareEvent', ['context', 'subject', 'params']],
                'onContentAfterTitle'    => ['Joomla\\CMS\\Event\\Content\\AfterTitleEvent', ['context', 'subject', 'params']],
                'onContentBeforeDisplay' => ['Joomla\\CMS\\Event\\Content\\BeforeDisplayEvent', ['context', 'subject', 'params']],
                'onContentAfterDisplay'  => ['Joomla\\CMS\\Event\\Content\\AfterDisplayEvent', ['context', 'subject', 'params']],
                // Model events (Joomla\CMS\Event\Model)
                'onContentBeforeSave'    => ['Joomla\\CMS\\Event\\Model\\BeforeSaveEvent', ['context', 'subject', 'isNew']],
                'onContentAfterSave'     => ['Joomla\\CMS\\Event\\Model\\AfterSaveEvent', ['context', 'subject', 'isNew']],
                'onContentBeforeDelete'  => ['Joomla\\CMS\\Event\\Model\\BeforeDeleteEvent', ['context', 'subject']],
                'onContentAfterDelete'   => ['Joomla\\CMS\\Event\\Model\\AfterDeleteEvent', ['context', 'subject']],
                'onContentChangeState'   => ['Joomla\\CMS\\Event\\Model\\AfterChangeStateEvent', ['context', 'pks', 'value']],
                'onContentPrepareForm'   => ['Joomla\\CMS\\Event\\Model\\PrepareFormEvent', ['subject', 'data']],
                'onContentPrepareData'   => ['Joomla\\CMS\\Event\\Model\\PrepareDataEvent', ['context', 'subject']],
                // User events
                'onUserBeforeDelete'     => ['Joomla\\CMS\\Event\\User\\BeforeDeleteEvent', ['subject']],
                'onUserAfterDelete'      => ['Joomla\\CMS\\Event\\User\\AfterDeleteEvent', ['subject']],
                // Finder events
                'onFinderBeforeSave'     => ['Joomla\\CMS\\Event\\Finder\\BeforeSaveEvent', ['context', 'subject', 'isNew']],
                'onFinderAfterSave'      => ['Joomla\\CMS\\Event\\Finder\\AfterSaveEvent', ['context', 'subject', 'isNew']],
                'onFinderAfterDelete'    => ['Joomla\\CMS\\Event\\Finder\\AfterDeleteEvent', ['context', 'subject']],
                'onFinderChangeState'    => ['Joomla\\CMS\\Event\\Finder\\AfterChangeStateEvent', ['context', 'pks', 'value']],
            ];

            if (isset($map[$eventName])) {
                list($class, $keys) = $map[$eventName];

                if (class_exists($class)) {
                    // K2 often passes MORE legacy positional arguments than the
                    // typed event accepts (e.g. onContentPrepare with a 4th
                    // $limitstart argument, onUserAfterDelete with 3). In PHP 8
                    // array_combine() throws a ValueError on mismatched counts,
                    // so map positionally and silently drop any surplus args -
                    // mirroring what the Joomla! core itself passes for these
                    // events. If a required named argument is missing the
                    // constructor throws and we fall through to the generic
                    // event below.
                    $named = [];

                    foreach ($keys as $index => $key) {
                        if (array_key_exists($index, $arguments)) {
                            $named[$key] = $arguments[$index];
                        }
                    }

                    if (!empty($named)) {
                        try {
                            return new $class($eventName, $named);
                        } catch (\Throwable $e) {
                            // Malformed arguments - fall through to a generic event
                        }
                    }
                }
            }

            $payload = [];

            foreach ($arguments as $index => $value) {
                $payload[$index] = $value;
            }

            return new \Joomla\Event\Event($eventName, $payload);
        }

        /**
         * Return the last database error message.
         *
         * Joomla! 4 removed DatabaseDriver::getErrorMsg(). The underlying
         * native connection still knows the message, so read it from there.
         * K2 only uses it for error reporting, never for control flow.
         *
         * @param   object  $db  A database driver instance
         *
         * @return  string
         */
        public static function dbError($db)
        {
            if (!is_object($db)) {
                return '';
            }

            if (method_exists($db, 'getErrorMsg')) {
                return (string) $db->getErrorMsg();
            }

            try {
                $connection = method_exists($db, 'getConnection') ? $db->getConnection() : null;

                if ($connection instanceof \mysqli) {
                    return (string) $connection->error;
                }

                if ($connection instanceof \PDO) {
                    $info = $connection->errorInfo();

                    return isset($info[2]) ? (string) $info[2] : '';
                }
            } catch (\Throwable $e) {
                // Fall through to the empty string
            }

            return '';
        }

        /**
         * Return the last database error number (0 when there was no error).
         *
         * Joomla! 4 removed DatabaseDriver::getErrorNum().
         *
         * @param   object  $db  A database driver instance
         *
         * @return  integer
         */
        public static function dbErrno($db)
        {
            if (!is_object($db)) {
                return 0;
            }

            if (method_exists($db, 'getErrorNum')) {
                return (int) $db->getErrorNum();
            }

            try {
                $connection = method_exists($db, 'getConnection') ? $db->getConnection() : null;

                if ($connection instanceof \mysqli) {
                    return (int) $connection->errno;
                }

                if ($connection instanceof \PDO) {
                    $info = $connection->errorInfo();

                    return isset($info[1]) ? (int) $info[1] : 0;
                }
            } catch (\Throwable $e) {
                // Fall through
            }

            return 0;
        }

        /**
         * Extract an archive (replacement for JArchive::extract()).
         *
         * @param   string  $archive  Archive path
         * @param   string  $dir      Destination directory
         *
         * @return  boolean
         */
        public static function extractArchive($archive, $dir)
        {
            if (is_file($archive) && class_exists('ZipArchive')) {
                $zip = new \ZipArchive();

                if ($zip->open($archive) === true) {
                    $zip->extractTo($dir);
                    $zip->close();

                    return true;
                }
            }

            if (class_exists('Joomla\\Filesystem\\Archive\\Archive')) {
                return \Joomla\Filesystem\Archive\Archive::extract($archive, $dir);
            }

            return false;
        }

        /**
         * Create the secret based hash K2 uses for download links.
         *
         * @param   string  $seed  Seed value
         *
         * @return  string
         */
        public static function getHash($seed)
        {
            return md5(\Joomla\CMS\Factory::getApplication()->get('secret') . $seed);
        }

        /**
         * Replacement for the removed DatabaseDriver::insertObject().
         *
         * @param   \Joomla\Database\DatabaseInterface  $db      Database driver
         * @param   string                              $table   Table name (may contain #__)
         * @param   object                              $object  Object to store
         * @param   string                              $key     Primary key property
         *
         * @return  boolean
         */
        public static function insertObject($db, $table, $object, $key = null)
        {
            $columns = [];
            $values  = [];

            foreach (get_object_vars($object) as $name => $value) {
                if ($name === $key && (int) $value === 0) {
                    continue;
                }

                if ($value === null) {
                    continue;
                }

                $columns[] = $db->quoteName($name);
                $values[]  = $db->quote($value);
            }

            if (!count($columns)) {
                return false;
            }

            $query = $db->getQuery(true)
                ->insert($db->quoteName($table))
                ->columns($columns)
                ->values(implode(',', $values));

            $db->setQuery($query);

            return (bool) $db->execute();
        }

        /**
         * Replacement for the static JPlugin::loadLanguage() that Joomla 4
         * turned into a protected instance method.
         *
         * @param   string   $extension  Extension name, e.g. "com_k2"
         * @param   string   $basePath   Base path of the language files
         * @param   string   $lang       Language tag (null = current)
         * @param   boolean  $reload     Force reload
         *
         * @return  boolean
         */
        public static function loadLanguage($extension = '', $basePath = JPATH_ADMINISTRATOR, $lang = null, $reload = false)
        {
            try {
                $language = \Joomla\CMS\Factory::getLanguage();

                return (bool) $language->load(strtolower((string) $extension), $basePath, $lang, (bool) $reload, true);
            } catch (\Throwable $e) {
                return false;
            }
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 3. JRequest - removed in Joomla 4
 * ------------------------------------------------------------------
 */
if (!class_exists('JRequest')) {
    class JRequest
    {
        /**
         * Returns the request source object.
         *
         * @param   string  $hash  get|post|request|files|server|cookie
         *
         * @return  \Joomla\Input\Input
         */
        protected static function source($hash = 'request')
        {
            $input = \Joomla\CMS\Factory::getApplication()->input;

            switch (strtolower((string) $hash)) {
                case 'get':
                case 'post':
                case 'request':
                case 'files':
                case 'server':
                case 'cookie':
                case 'method':
                    return $input->{strtolower($hash)};
                default:
                    return $input->request;
            }
        }

        /**
         * Fetch a variable from the request.
         *
         * Mirrors Joomla 3's JRequest::getVar() including the "no filter"
         * behaviour that K2 relies on for arrays such as cid[].
         *
         * @param   string  $name     Name of the variable
         * @param   mixed   $default  Default value
         * @param   string  $hash     Request hash
         * @param   string  $type     Filter type
         * @param   integer  $mask    Filter mask (ignored)
         *
         * @return  mixed
         */
        public static function getVar($name, $default = null, $hash = 'default', $type = 'none', $mask = 0)
        {
            $hash  = strtolower((string) $hash);
            $app   = \Joomla\CMS\Factory::getApplication();
            $value = null;
            $found = false;

            /*
             * Joomla! 4+ component routers store the variables they parsed from
             * a SEF URL in the main input ($app->input->set()), NOT in $_REQUEST
             * and not in $input->request. Joomla 3's JRequest used to see them
             * because its router wrote into $_REQUEST.
             *
             * Reading the main input first is what keeps SEF URLs working:
             * "/.../item/2-some-alias" arrives as "2-some-alias", and
             * JRequest::getInt('id') must turn it into 2 instead of 0.
             */
            if ($hash === '' || $hash === 'default' || $hash === 'request') {
                if ($app->input->exists($name)) {
                    $value = $app->input->get($name, null, 'raw');
                    $found = true;
                }
            }

            if (!$found) {
                /*
                 * Read the raw superglobals instead of $input->post->getArray()
                 * etc. Joomla's Input::getArray() treats each scalar value in
                 * the request data as a FILTER NAME, which mangles ordinary
                 * text and — fatally for file uploads — flattens $_FILES so
                 * the top-level name/tmp_name/error/size keys disappear. K2
                 * expects the plain Joomla 3 hashes ($_FILES, $_POST, ...).
                 */
                switch (strtolower((string) $hash)) {
                    case 'post':
                        $src = $_POST;
                        break;
                    case 'get':
                        $src = $_GET;
                        break;
                    case 'files':
                        $src = $_FILES;
                        break;
                    case 'cookie':
                        $src = $_COOKIE;
                        break;
                    case 'server':
                        $src = $_SERVER;
                        break;
                    default:
                        $src = $_REQUEST;
                        break;
                }

                if (!is_array($src) || !array_key_exists($name, $src)) {
                    return $default;
                }

                $value = $src[$name];
            }

            if ($value === null) {
                return $default;
            }

            $type = strtolower((string) $type);

            if ($type === '' || $type === 'none') {
                return $value;
            }

            if ($type === 'array') {
                return (array) $value;
            }

            // Arrays are cleaned item by item
            if (is_array($value)) {
                $clean = [];

                foreach ($value as $key => $item) {
                    $clean[$key] = self::clean($item, $type);
                }

                return $clean;
            }

            return self::clean($value, $type);
        }

        /**
         * Filter a single value.
         *
         * @param   mixed   $value  Value to clean
         * @param   string  $type   Filter type
         *
         * @return  mixed
         */
        protected static function clean($value, $type)
        {
            if (!is_scalar($value)) {
                return $value;
            }

            switch ($type) {
                case 'int':
                case 'integer':
                    return (int) $value;
                case 'float':
                case 'double':
                    return (float) $value;
                case 'bool':
                case 'boolean':
                    return (bool) $value;
                case 'cmd':
                    return preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $value);
                case 'word':
                    return preg_replace('/[^A-Z_]/i', '', (string) $value);
                case 'alnum':
                    return preg_replace('/[^A-Z0-9]/i', '', (string) $value);
                case 'username':
                    return preg_replace('/[<>]/i', '', (string) $value);
                case 'string':
                case 'path':
                case 'raw':
                default:
                    return (string) $value;
            }
        }

        /**
         * Shortcut to getVar() with the int filter.
         *
         * Note: the third argument is the request hash (Joomla 3 signature),
         * not a filter mask.
         */
        public static function getInt($name, $default = 0, $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'int');

            return $value === null ? $default : (int) $value;
        }

        public static function getFloat($name, $default = 0.0, $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'float');

            return $value === null ? $default : (float) $value;
        }

        public static function getBool($name, $default = false, $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'bool');

            return $value === null ? $default : (bool) $value;
        }

        public static function getWord($name, $default = '', $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'word');

            return $value === null ? $default : $value;
        }

        public static function getCmd($name, $default = '', $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'cmd');

            return $value === null ? $default : $value;
        }

        public static function getString($name, $default = '', $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'string');

            return $value === null ? $default : $value;
        }

        public static function getArray($name, $default = [], $hash = 'default')
        {
            $value = self::getVar($name, null, $hash, 'array');

            return $value === null ? $default : $value;
        }

        /**
         * Return the whole request array for a given hash.
         *
         * @param   string   $hash  get|post|request|files|server|cookie
         * @param   integer  $mask  Filter mask (ignored)
         *
         * @return  array
         */
        public static function get($hash = 'default', $mask = 0)
        {
            switch (strtolower((string) $hash)) {
                case 'post':
                    return $_POST;
                case 'get':
                    return $_GET;
                case 'files':
                    return $_FILES;
                case 'cookie':
                    return $_COOKIE;
                case 'server':
                    return $_SERVER;
                default:
                    return $_REQUEST;
            }
        }

        /**
         * Set (override) a request variable.
         */
        public static function setVar($name, $value = null, $hash = 'request', $overwrite = true)
        {
            $app    = \Joomla\CMS\Factory::getApplication();
            $source = self::source($hash);

            if (!$overwrite && $source->offsetExists($name)) {
                return;
            }

            $source->set($name, $value);

            // Keep the main input in sync: that is the source Joomla 4+ reads
            // from, and our getVar() consults it first.
            if ($overwrite || !$app->input->exists($name)) {
                $app->input->set($name, $value);
            }
        }

        /**
         * Check the form token.
         *
         * @param   string  $method  post|get|request
         *
         * @return  boolean
         */
        public static function checkToken($method = 'post')
        {
            try {
                return \Joomla\CMS\Session\Session::checkToken($method);
            } catch (\Throwable $e) {
                return false;
            }
        }

        /**
         * Deprecated in Joomla 3, kept for completeness.
         */
        public static function getMethod()
        {
            return strtoupper((string) $_SERVER['REQUEST_METHOD']);
        }

        public static function getURI()
        {
            return \Joomla\CMS\Uri\Uri::getInstance()->toString();
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 4. JError / JException - removed in Joomla 4
 * ------------------------------------------------------------------
 */
if (!class_exists('JException')) {
    class JException extends \Exception
    {
        public function __construct($message = '', $code = 0, $info = '')
        {
            parent::__construct(is_array($message) ? implode(' ', $message) : (string) $message, (int) $code);
            $this->info = $info;
        }

        public $info = '';
    }
}

if (!class_exists('JError')) {
    class JError
    {
        /**
         * Joomla 3's raiseError() aborted the request. In Joomla 4+ the same
         * effect is achieved by throwing an exception, which the core error
         * handler renders as an error page with the correct HTTP status.
         *
         * @param   integer|string  $code     Error code
         * @param   string          $message  Error message
         * @param   mixed           $info     Unused
         * @param   mixed           $params   Unused
         *
         * @return  void
         * @throws  \Exception
         */
        public static function raiseError($code, $message = '', $info = null, $params = null)
        {
            if ($code instanceof \Throwable) {
                throw $code;
            }

            $message = is_array($message) ? implode(' ', $message) : (string) $message;
            $code    = (int) $code;

            // 404/403 need a valid HTTP status so Joomla renders the right page
            if ($code < 100 || $code > 599) {
                $code = 500;
            }

            throw new \Exception($message, $code);
        }

        /**
         * @param   integer|string  $code     Error code
         * @param   string          $message  Message
         * @param   mixed           $info     Unused
         * @param   mixed           $params   Unused
         *
         * @return  JException
         */
        public static function raiseWarning($code, $message = '', $info = null, $params = null)
        {
            $message = is_array($message) ? implode(' ', $message) : (string) $message;

            return new JException($message, (int) $code, (string) $info);
        }

        public static function raiseNotice($code, $message = '', $info = null, $params = null)
        {
            return self::raiseWarning($code, $message, $info, $params);
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 5. JResponse - removed in Joomla 4
 * ------------------------------------------------------------------
 */
if (!class_exists('JResponse')) {
    class JResponse
    {
        public static function allowCache($allow = null)
        {
            $app = \Joomla\CMS\Factory::getApplication();

            if ($allow === null) {
                return $app->allowCache();
            }

            $app->allowCache((bool) $allow);

            return null;
        }

        public static function setHeader($name, $value = null, $replace = false)
        {
            \Joomla\CMS\Factory::getApplication()->setHeader($name, $value, (bool) $replace);
        }

        public static function clearHeaders()
        {
            if (!headers_sent()) {
                \Joomla\CMS\Factory::getApplication()->clearHeaders();
            }
        }

        public static function sendHeaders()
        {
            \Joomla\CMS\Factory::getApplication()->sendHeaders();
        }

        public static function getBody()
        {
            return \Joomla\CMS\Factory::getApplication()->getBody();
        }

        public static function setBody($content)
        {
            \Joomla\CMS\Factory::getApplication()->setBody($content);
        }

        public static function redirect($url, $msg = '', $msgType = 'message', $code = 303)
        {
            \Joomla\CMS\Factory::getApplication()->redirect($url, $msg, $msgType, $code);
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 6. JDispatcher - removed in Joomla 4
 * ------------------------------------------------------------------
 */
if (!class_exists('JDispatcher')) {
    class JDispatcher
    {
        protected static $instance = null;

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Trigger an event the Joomla 3 way and return the collected results.
         *
         * Two deviations from plain Dispatcher::dispatch() are essential:
         *
         * 1. Core event names are dispatched as their TYPED event objects
         *    (K2Compat::createEvent), otherwise type-hinted core plugins
         *    (e.g. plg_system_fields) abort with a TypeError.
         *
         * 2. The Joomla event dispatcher does NOT collect listener return
         *    values, but K2 relies on them (onContentAfterTitle /
         *    onContentBeforeDisplay / onContentAfterDisplay build up plugin
         *    output). So instead of dispatch() we iterate the sorted listeners
         *    directly - same order, same arguments - and collect every
         *    non-null return value, exactly like JEventDispatcher::trigger().
         *
         * @param   string  $event  Event name
         * @param   array   $args   Event arguments
         *
         * @return  array
         */
        public function trigger($event, $args = [])
        {
            $app        = \Joomla\CMS\Factory::getApplication();
            $dispatcher = $app->getDispatcher();
            $arguments  = is_array($args) ? $args : [$args];

            $eventObject = K2Compat::createEvent($event, $arguments);

            try {
                $listeners = $dispatcher->getListeners($event);
            } catch (\Throwable $e) {
                $listeners = [];
            }

            $results = [];

            foreach ($listeners as $listener) {
                try {
                    $return = call_user_func($listener, $eventObject);
                } catch (\Throwable $e) {
                    // Keep J3 semantics: a listener error is not silently swallowed
                    throw $e;
                }

                if ($return !== null) {
                    $results[] = $return;
                }
            }

            return $results;
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 7. JApplication / JSite / JUtility - removed in Joomla 4
 * ------------------------------------------------------------------
 */
if (!class_exists('JApplication')) {
    class JApplication
    {
        public static function getHash($seed)
        {
            return K2Compat::getHash($seed);
        }

        public static function stringURLSafe($string, $language = '')
        {
            return \Joomla\CMS\Filter\OutputFilter::stringURLSafe($string, $language);
        }

        public static function getRouter($name = null)
        {
            return \Joomla\CMS\Factory::getApplication()->getRouter();
        }

        public static function getInstance($client = null)
        {
            return \Joomla\CMS\Factory::getApplication();
        }

        public static function close($code = 0)
        {
            K2Compat::close($code);
        }
    }
}

if (!class_exists('JSite')) {
    class JSite
    {
        public static function getRouter($name = null)
        {
            return \Joomla\CMS\Factory::getApplication()->getRouter();
        }

        public static function getMenu($name = null)
        {
            return \Joomla\CMS\Factory::getApplication()->getMenu();
        }
    }
}

if (!class_exists('JUtility')) {
    class JUtility
    {
        public static function getHash($seed)
        {
            return K2Compat::getHash($seed);
        }

        public static function getToken($forceNew = false)
        {
            return \Joomla\CMS\Session\Session::getFormToken($forceNew);
        }

        public static function makeSafe(&$string)
        {
            $string = \Joomla\CMS\Filter\OutputFilter::stringURLSafe($string);
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 8. JMailHelper / JArchive - removed in Joomla 4
 * ------------------------------------------------------------------
 */
if (!class_exists('JMailHelper')) {
    class JMailHelper
    {
        public static function isEmailAddress($email)
        {
            return K2Compat::isEmailAddress($email);
        }

        public static function cleanText(&$text)
        {
            $text = strip_tags((string) $text);
        }
    }
}

if (!class_exists('JArchive')) {
    class JArchive
    {
        public static function extract($archive, $dir)
        {
            return K2Compat::extractArchive($archive, $dir);
        }
    }
}

/**
 * JMenu - Joomla 4 replaced JMenu::getInstance() with the menu factory.
 * Only mod_k2_content still calls it.
 */
if (!class_exists('JMenu')) {
    class JMenu
    {
        public static function getInstance($client, $config = [])
        {
            $client = strtolower((string) $client);

            try {
                if ($client === 'site') {
                    return \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Menu\SiteMenu::class);
                }

                if ($client === 'administrator') {
                    return \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Menu\AdministratorMenu::class);
                }
            } catch (\Throwable $e) {
                // Fall back to the menu of the current application
            }

            return \Joomla\CMS\Factory::getApplication()->getMenu();
        }
    }
}

/**
 * JFilterOutput - Joomla 4 kept only a small subset of this class
 * (stringURLSafe, linkXHTMLSafe, stringJSSafe). K2 also relies on
 * cleanText(), objectHTMLSafe(), ampReplace() and stringURLUnicodeSlug(),
 * so those are re-implemented here with the Joomla 3 semantics.
 *
 * NOTE: this must be a real class and not a class_alias() - see the alias
 * map above, JFilterOutput is deliberately excluded from it.
 */
if (!class_exists('JFilterOutput')) {
    class JFilterOutput
    {
        /**
         * Cleans text of all formatting and scripting code.
         *
         * @param   string  $text  Text to clean
         *
         * @return  string
         */
        public static function cleanText($text)
        {
            $text = (string) $text;

            $text = preg_replace("'<script[^>]*>.*?</script>'si", '', $text);
            $text = preg_replace('/<a\s+.*?href="([^"]+)"[^>]*>([^<]+)<\/a>/is', '\2 (\1)', $text);
            $text = preg_replace('/<!--.+?-->/', '', $text);
            $text = preg_replace('/{.+?}/', '', $text);
            $text = preg_replace('/&nbsp;/', ' ', $text);
            $text = preg_replace('/&amp;/', ' ', $text);
            $text = preg_replace('/&quot;/', ' ', $text);
            $text = strip_tags($text);
            $text = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');

            return $text;
        }

        /**
         * Escapes a string, or the string values of an object/array, for use
         * in HTML output. Works on the variable in place, like Joomla 3 did.
         *
         * @param   mixed         $mixed         The variable to escape
         * @param   integer       $quote_style   Quote style for htmlspecialchars()
         * @param   string|array  $exclude_keys  Property/key names to leave alone
         *
         * @return  void
         */
        public static function objectHTMLSafe(&$mixed, $quote_style = ENT_QUOTES, $exclude_keys = '')
        {
            if (is_object($mixed)) {
                foreach (get_object_vars($mixed) as $k => $v) {
                    if (is_array($v) || is_object($v) || $k === 'params') {
                        continue;
                    }

                    if ((is_string($exclude_keys) && $k === $exclude_keys)
                        || (is_array($exclude_keys) && in_array($k, $exclude_keys, true))) {
                        continue;
                    }

                    $mixed->$k = htmlspecialchars((string) $v, $quote_style, 'UTF-8');
                }

                return;
            }

            if (is_array($mixed)) {
                foreach ($mixed as $k => $v) {
                    if (is_array($v) || is_object($v)) {
                        continue;
                    }

                    if ((is_string($exclude_keys) && $k === $exclude_keys)
                        || (is_array($exclude_keys) && in_array($k, $exclude_keys, true))) {
                        continue;
                    }

                    $mixed[$k] = htmlspecialchars((string) $v, $quote_style, 'UTF-8');
                }

                return;
            }

            if (is_string($mixed)) {
                $mixed = htmlspecialchars($mixed, $quote_style, 'UTF-8');
            }
        }

        /**
         * Replaces & with &amp; but leaves existing entities alone.
         *
         * @param   string  $text  Text to process
         *
         * @return  string
         */
        public static function ampReplace($text)
        {
            $text = (string) $text;

            $text = str_replace('&&', '*--*', $text);
            $text = str_replace('&#', '*-*', $text);
            $text = str_replace('&amp;', '&', $text);
            $text = preg_replace('|&(?![\w]+;)|', '&amp;', $text);
            $text = str_replace('*-*', '&#', $text);
            $text = str_replace('*--*', '&&', $text);

            return $text;
        }

        /**
         * Creates a unicode aware slug (used when "Unicode aliases" is on).
         *
         * @param   string  $string  String to process
         *
         * @return  string
         */
        public static function stringURLUnicodeSlug($string)
        {
            $str = (string) $string;

            // Replace double byte whitespaces by single byte (East Asian languages)
            $str = preg_replace('/\xE3\x80\x80/', ' ', $str);

            // Remove any '-' from the string as they will be used as concatenator
            $str = str_replace('-', ' ', $str);

            // Replace forbidden characters by whitespaces
            $str = preg_replace('#[:\#\*"@+=;!><&\.%()\]\/\'\\\\|\[]#', "\x20", $str);

            // Delete all '?'
            $str = str_replace('?', '', $str);

            // Trim white spaces at beginning and end of alias and make lowercase
            $str = trim(\Joomla\String\StringHelper::strtolower($str));

            // Remove any duplicate whitespace and replace whitespaces by hyphens
            $str = preg_replace('#\x20+#', '-', $str);

            return $str;
        }

        /**
         * @see     \Joomla\CMS\Filter\OutputFilter::stringURLSafe()
         *
         * @param   string  $string    String to process
         * @param   string  $language  Language to transliterate to
         *
         * @return  string
         */
        public static function stringURLSafe($string, $language = '')
        {
            return \Joomla\CMS\Filter\OutputFilter::stringURLSafe($string, $language);
        }

        /**
         * @see     \Joomla\CMS\Filter\OutputFilter::linkXHTMLSafe()
         *
         * @param   string  $input  String to process
         *
         * @return  string
         */
        public static function linkXHTMLSafe($input)
        {
            return \Joomla\CMS\Filter\OutputFilter::linkXHTMLSafe($input);
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 9. JPane - removed in Joomla 4
 * ------------------------------------------------------------------
 * Joomla 3's JPane rendered tab sets while buffering the panel content.
 * The shim reproduces that behaviour with Bootstrap 5 markup so K2's
 * settings screen keeps working.
 */
if (!class_exists('JPane')) {
    class JPane
    {
        protected static $instances = [];

        protected static $scriptDone = false;

        protected $panes = [];

        protected $bufferLevel = null;

        protected $current = null;

        public static function getInstance($behavior = 'Tabs', $params = [])
        {
            $key = md5((string) $behavior . serialize($params));

            if (!isset(self::$instances[$key])) {
                self::$instances[$key] = new self();
            }

            return self::$instances[$key];
        }

        /**
         * Render the tab switching logic once per document. Kept dependency
         * free on purpose: Joomla 6 does not always load the Bootstrap "tab"
         * component on backend pages.
         */
        protected static function injectScript()
        {
            if (self::$scriptDone) {
                return;
            }

            self::$scriptDone = true;

            try {
                $document = \Joomla\CMS\Factory::getApplication()->getDocument();
            } catch (\Throwable $e) {
                return;
            }

            $document->addScriptDeclaration("
                if (!window.k2PanesBound) {
                    window.k2PanesBound = true;
                    document.addEventListener('click', function(event) {
                        var link = event.target && event.target.closest ? event.target.closest('[data-k2-tab]') : null;
                        if (!link) { return; }
                        var wrapper = link.closest('.k2Panes');
                        if (!wrapper) { return; }
                        event.preventDefault();
                        var links = wrapper.querySelectorAll('[data-k2-tab]');
                        for (var i = 0; i < links.length; i++) {
                            links[i].classList.remove('active');
                        }
                        var panes = wrapper.querySelectorAll('.k2PaneContent > .tab-pane');
                        for (var j = 0; j < panes.length; j++) {
                            panes[j].classList.remove('active', 'show');
                        }
                        link.classList.add('active');
                        var target = wrapper.querySelector(link.getAttribute('data-k2-tab'));
                        if (target) { target.classList.add('active', 'show'); }
                    }, false);
                }
            ");
        }

        public function startPane($id)
        {
            $this->panes       = [];
            $this->current     = null;
            $this->bufferLevel = ob_get_level();
            ob_start();

            return '';
        }

        public function startPanel($text, $id)
        {
            if ($this->current !== null) {
                $this->panes[$this->current]['html'] .= (string) ob_get_clean();
            }

            $this->current = $id;
            $this->panes[$id] = ['text' => $text, 'html' => ''];
            ob_start();

            return '';
        }

        public function endPanel()
        {
            if ($this->current !== null) {
                $this->panes[$this->current]['html'] .= (string) ob_get_clean();
                $this->current = null;
            }

            return '';
        }

        public function endPane()
        {
            $this->endPanel();

            if ($this->bufferLevel !== null) {
                while (ob_get_level() > $this->bufferLevel) {
                    ob_end_clean();
                }
            }

            if (!count($this->panes)) {
                return '';
            }

            self::injectScript();

            $nav  = '<ul class="nav nav-tabs k2PanesNav" role="tablist">';
            $body = '<div class="tab-content k2PaneContent">';
            $i    = 0;

            foreach ($this->panes as $id => $pane) {
                $paneId = 'k2pane-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $id);
                $active = $i === 0 ? ' active' : '';
                $nav .= '<li class="nav-item" role="presentation">'
                    . '<a class="nav-link' . $active . '" href="#' . htmlspecialchars($paneId, ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-k2-tab="#' . htmlspecialchars($paneId, ENT_QUOTES, 'UTF-8') . '" role="tab">'
                    . $pane['text'] . '</a></li>';
                $body .= '<div class="tab-pane' . ($i === 0 ? ' active show' : ' fade') . '"'
                    . ' id="' . htmlspecialchars($paneId, ENT_QUOTES, 'UTF-8') . '" role="tabpanel">' . $pane['html'] . '</div>';
                $i++;
            }

            $nav  .= '</ul>';
            $body .= '</div>';

            return '<div class="k2Panes">' . $nav . $body . '</div>';
        }

        public function startTab($text, $id = '')
        {
            return $this->startPanel($text, $id ?: md5($text));
        }

        public function endTab()
        {
            return $this->endPanel();
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 10. JSubMenuHelper - removed in Joomla 4 (K2 only calls it in dead code)
 * ------------------------------------------------------------------
 */
if (!class_exists('JSubMenuHelper')) {
    class JSubMenuHelper
    {
        protected static $entries = [];

        public static function addEntry($name, $link = '', $active = false)
        {
            self::$entries[] = [$name, $link, $active];
        }

        public static function getEntries()
        {
            return self::$entries;
        }

        public static function render()
        {
            return '';
        }
    }
}

/*
 * ------------------------------------------------------------------
 * 11. Feed helpers - removed in Joomla 3, still referenced by K2's RSS view
 * ------------------------------------------------------------------
 */
if (!class_exists('JFeedItem')) {
    class JFeedItem
    {
        public $title = '';
        public $link = '';
        public $description = '';
        public $author = '';
        public $authorEmail = '';
        public $category = '';
        public $date = '';
        public $comments = '';
        public $enclosure = null;

        public function setEnclosure($enclosure)
        {
            $this->enclosure = $enclosure;
        }
    }
}

if (!class_exists('JFeedEnclosure')) {
    class JFeedEnclosure
    {
        public $url = '';
        public $length = 0;
        public $type = '';
    }
}

/*
 * ------------------------------------------------------------------
 * 12. Broken HTML helpers
 * ------------------------------------------------------------------
 * Joomla 6's Behavior helper no longer ships framework(), modal() and
 * "formbehavior" has no chosen(). Joomla 6 also dropped the old "tabs.*"
 * helper. Registering our own implementations keeps the JHtml::_() calls
 * in K2 (and in third party K2 templates) alive.
 */
if (class_exists('Joomla\\CMS\\HTML\\HTMLHelper')) {
    if (!class_exists('K2CompatHtml')) {
        class K2CompatHtml
        {
            /**
             * @var JPane|null
             */
            private static $pane = null;

            public static function behaviorFramework()
            {
                // MooTools is gone in Joomla 4+. K2 itself is jQuery based, but
                // the Joomla core JS (table ordering, submitbutton) is still
                // required by K2's list views.
                K2Compat::loadJquery();
                K2Compat::loadCoreAssets();

                return '';
            }

            public static function jqueryFramework()
            {
                K2Compat::loadJquery();

                return '';
            }

            /**
             * Joomla 4 removed behavior.mootools(). K2 calls it right before
             * using jQuery, so mapping it to "load jQuery" is a faithful
             * replacement - there is no MooTools code left in K2.
             *
             * @return  string
             */
            public static function behaviorMootools()
            {
                K2Compat::loadJquery();

                return '';
            }

            /**
             * Joomla 4 removed the "script" key (JHtmlScript). K2 only uses it
             * for openid.js, so a plain addScript() is enough.
             *
             * @param   string   $file        The script file
             * @param   boolean  $framework   Unused (Joomla 3 MooTools flag)
             * @param   boolean  $relative    Prepend the media path
             * @param   boolean  $path_only   Return the path instead of adding it
             * @param   boolean  $detect_browser  Unused
             * @param   string   $media       Media attribute
             * @param   array    $attribs     Extra attributes
             *
             * @return  string|void
             */
            public static function script($file, $framework = false, $relative = false, $path_only = false, $detect_browser = true, $media = 'all', $attribs = [])
            {
                $document = \Joomla\CMS\Factory::getDocument();

                $url = ($relative ? \Joomla\CMS\Uri\Uri::root(true) . '/media/system/js/' : '') . $file;

                if ($path_only) {
                    return $url;
                }

                $document->addScript($url, ['version' => 'auto'], $attribs ?: []);

                return '';
            }

            public static function behaviorModal()
            {
                K2Compat::loadJquery();
                K2Compat::loadModal();

                return '';
            }

            public static function behaviorTooltip()
            {
                // Joomla 4+ renders tooltips with native/BS markup. K2's old
                // "hasTip" attributes simply stay plain titles.
                K2Compat::loadJquery();

                return '';
            }

            public static function behaviorChosen($selector = 'select')
            {
                // Chosen is not shipped by Joomla 6. A plain select is still
                // perfectly usable, so this is intentionally a no-op that only
                // keeps the call sites working.
                return '';
            }

            public static function tabsStart($group = 'tabs')
            {
                self::$pane = JPane::getInstance('Tabs');

                return self::$pane->startPane($group);
            }

            public static function tabsPanel($text, $id = '')
            {
                if (self::$pane === null) {
                    self::tabsStart();
                }

                return self::$pane->startPanel($text, $id ?: md5($text));
            }

            public static function tabsEnd()
            {
                if (self::$pane === null) {
                    return '';
                }

                $output     = self::$pane->endPane();
                self::$pane = null;

                return $output;
            }
        }
    }

    $k2HelperFallbacks = [
        'behavior.framework'  => ['K2CompatHtml', 'behaviorFramework'],
        'behavior.mootools'   => ['K2CompatHtml', 'behaviorMootools'],
        'jquery.framework'    => ['K2CompatHtml', 'jqueryFramework'],
        'behavior.modal'      => ['K2CompatHtml', 'behaviorModal'],
        'script'              => ['K2CompatHtml', 'script'],
        'formbehavior.chosen' => ['K2CompatHtml', 'behaviorChosen'],
        'behavior.chosen'     => ['K2CompatHtml', 'behaviorChosen'],
        'tabs.start'          => ['K2CompatHtml', 'tabsStart'],
        'tabs.panel'          => ['K2CompatHtml', 'tabsPanel'],
        'tabs.end'            => ['K2CompatHtml', 'tabsEnd'],
    ];

    // Only shim the tooltip helper when Joomla dropped it
    if (!method_exists('Joomla\\CMS\\HTML\\Helpers\\Behavior', 'tooltip')) {
        $k2HelperFallbacks['behavior.tooltip'] = ['K2CompatHtml', 'behaviorTooltip'];
    }

    foreach ($k2HelperFallbacks as $k2Key => $k2Callback) {
        try {
            @\Joomla\CMS\HTML\HTMLHelper::register($k2Key, $k2Callback);
        } catch (\Throwable $e) {
            // Already registered (for example by another extension) - fine
        }
    }

    unset($k2HelperFallbacks, $k2Key, $k2Callback);
}

/*
 * ------------------------------------------------------------------
 * 13. Editor adapter - Joomla 4 removed JEditor::save()/getContent()
 * ------------------------------------------------------------------
 * Joomla 6 still ships \Joomla\CMS\Editor\Editor, and its display() method
 * kept the untyped Joomla 3 signature, so K2's "100%"/"250px" and button
 * arrays still work. Only save()/getContent()/setContent() disappeared.
 *
 * K2 calls $wysiwyg->save($field) to build the JavaScript that pushes the
 * editor content back into the hidden textarea before the form is posted.
 * The adapter restores that behaviour on top of Joomla.editors.instances,
 * which all Joomla 4/5/6 editors populate.
 */
if (!class_exists('K2Editor')) {
    class K2Editor
    {
        /**
         * @var    \Joomla\CMS\Editor\Editor
         */
        protected $editor;

        /**
         * @param   string  $name  Editor plugin name (from the global config)
         */
        public function __construct($name = null)
        {
            if ($name === null || $name === '') {
                $name = (string) \Joomla\CMS\Factory::getApplication()->get('editor', 'none');
            }

            $this->editor = \Joomla\CMS\Editor\Editor::getInstance($name);
        }

        /**
         * Render the editor area (Joomla 3 signature).
         *
         * @return  string
         */
        public function display($name, $html, $width, $height, $col, $row, $buttons = true, $id = null, $asset = null, $author = null, $params = [])
        {
            return $this->editor->display(
                $name,
                $html,
                $width,
                $height,
                $col,
                $row,
                $buttons,
                $id ?: $name,
                $asset,
                $author,
                is_array($params) ? $params : []
            );
        }

        /**
         * Return the JavaScript that syncs the editor content into the
         * textarea - Joomla 3's JEditor::save() equivalent.
         *
         * @param   string  $id  The textarea id
         *
         * @return  string
         */
        public function save($id)
        {
            $id = (string) $id;

            return "if(window.Joomla&&Joomla.editors&&Joomla.editors.instances['" . $id . "']){"
                . "document.getElementById('" . $id . "').value=Joomla.editors.instances['" . $id . "'].getValue();"
                . "}";
        }

        /**
         * @param   string  $id  The textarea id
         *
         * @return  string
         */
        public function getContent($id)
        {
            $id = (string) $id;

            return "if(window.Joomla&&Joomla.editors&&Joomla.editors.instances['" . $id . "']){"
                . "document.getElementById('" . $id . "').value=Joomla.editors.instances['" . $id . "'].getValue();"
                . "}";
        }

        /**
         * @param   string  $id       The textarea id
         * @param   string  $content  The content to set
         *
         * @return  string
         */
        public function setContent($id, $content)
        {
            $id      = (string) $id;
            $content = json_encode((string) $content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return "if(window.Joomla&&Joomla.editors&&Joomla.editors.instances['" . $id . "']){"
                . "Joomla.editors.instances['" . $id . "'].setValue(" . $content . ");"
                . "}";
        }

        /**
         * Forward anything else to the real editor object.
         *
         * @param   string  $method     Method name
         * @param   array   $arguments  Arguments
         *
         * @return  mixed
         */
        public function __call($method, $arguments)
        {
            if (is_callable([$this->editor, $method])) {
                return call_user_func_array([$this->editor, $method], $arguments);
            }

            return null;
        }

        /**
         * @return  \Joomla\CMS\Editor\Editor
         */
        public function getEditor()
        {
            return $this->editor;
        }
    }
}
