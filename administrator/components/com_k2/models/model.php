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

require_once JPATH_ADMINISTRATOR . '/components/com_k2/helpers/compat.php';

/**
 * Base model for K2.
 *
 * Joomla 4+ loads models through the MVC factory and no longer provides
 * JModelLegacy::addIncludePath(). K2 relied on that method to make both the
 * site and the administrator model folders available, so we keep a tiny
 * implementation that registers the classes with the Joomla autoloader.
 */
class K2Model extends \Joomla\CMS\MVC\Model\BaseDatabaseModel
{
    /**
     * Model instances created through getInstance().
     *
     * @var  array
     */
    protected static $k2Instances = [];

    /**
     * Joomla 3 style model factory.
     *
     * Joomla 4 removed JModelLegacy::getInstance(), but K2 (and a lot of K2
     * extensions) rely on it. This implementation keeps the same semantics:
     * one shared instance per class/configuration combination.
     *
     * @param   string  $type    Model name, e.g. "item"
     * @param   string  $prefix  Class prefix, "K2Model" by default
     * @param   array   $config  Configuration array
     *
     * @return  K2Model|boolean
     */
    public static function getInstance($type, $prefix = 'K2Model', $config = [])
    {
        $type   = preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $type);
        $prefix = $prefix ?: 'K2Model';
        $class  = $prefix . ucfirst($type);
        $key    = strtolower($class) . '.' . md5(serialize($config));

        if (isset(self::$k2Instances[$key])) {
            return self::$k2Instances[$key];
        }

        if (!class_exists($class)) {
            $base = defined('JPATH_COMPONENT') ? JPATH_COMPONENT : JPATH_ADMINISTRATOR . '/components/com_k2';

            $candidates = [
                $base . '/models/' . strtolower($type) . '.php',
                JPATH_ADMINISTRATOR . '/components/com_k2/models/' . strtolower($type) . '.php',
                JPATH_SITE . '/components/com_k2/models/' . strtolower($type) . '.php',
            ];

            foreach ($candidates as $file) {
                if (is_file($file)) {
                    JLoader::register($class, $file);

                    if (class_exists($class)) {
                        break;
                    }
                }
            }
        }

        if (!class_exists($class)) {
            return false;
        }

        self::$k2Instances[$key] = new $class($config);

        return self::$k2Instances[$key];
    }

    /**
     * Registers the model classes found in the given folder(s).
     *
     * Mirrors the Joomla 3 behaviour closely enough: every "*.php" file in the
     * path is mapped to "{prefix}{Filename}" and lazily autoloaded.
     *
     * @param   string|array  $path    A path or a list of paths
     * @param   string        $prefix  Class prefix, "K2Model" by default
     *
     * @return  array
     */
    public static function addIncludePath($path = '', $prefix = 'K2Model')
    {
        static $registered = [];

        $parentHasPaths = method_exists('Joomla\\CMS\\MVC\\Model\\BaseDatabaseModel', 'addIncludePath');

        foreach ((array) $path as $folder) {
            if (!is_string($folder) || $folder === '' || !is_dir($folder)) {
                continue;
            }

            // Also feed the Joomla include-path list: BaseDatabaseModel::getInstance()
            // (used by the legacy MVC factory) searches it when it has to load a model.
            if ($parentHasPaths) {
                try {
                    parent::addIncludePath($folder, $prefix);
                } catch (\Throwable $e) {
                    // Ignore - the JLoader registrations below are enough
                }
            }

            foreach ((array) glob(rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . '*.php') as $file) {
                $class = $prefix . ucfirst(basename($file, '.php'));

                if (isset($registered[$class])) {
                    continue;
                }

                $registered[$class] = true;

                if (!class_exists($class, false)) {
                    JLoader::register($class, $file);
                }
            }
        }

        return [];
    }
}
