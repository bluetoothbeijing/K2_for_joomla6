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
 * Base controller for K2 (administrator).
 *
 * Joomla 4+ dropped the J-prefixed class names, so the controller extends the
 * namespaced core class directly instead of going through JControllerLegacy.
 *
 * display()/getView()/getModel() keep Joomla 3 semantics: K2's views expect the
 * controller to push the model into the view with setModel($model, true), and
 * K2's class naming (K2ViewXxx / K2ModelXxx) does not follow the Joomla 4 MVC
 * factory conventions.
 */
class K2Controller extends \Joomla\CMS\MVC\Controller\BaseController
{
    /**
     * Typical view method for MVC based architecture.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  static
     */
    public function display($cachable = false, $urlparams = [])
    {
        $document   = \Joomla\CMS\Factory::getDocument();
        $viewType   = $document->getType();
        $viewName   = $this->input->get('view', $this->default_view);
        $viewLayout = $this->input->get('layout', 'default', 'string');

        $view = $this->getView($viewName, $viewType, '', ['base_path' => $this->basePath, 'layout' => $viewLayout]);

        // Get/Create the model and push it into the view (as default)
        if ($model = $this->getModel($viewName)) {
            $view->setModel($model, true);
        }

        $view->document = $document;
        $view->display();

        return $this;
    }

    /**
     * Load a view object.
     *
     * @param   string  $name    The view name. Optional, defaults to the controller name.
     * @param   string  $type    The view type. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for the view. Optional.
     *
     * @return  \Joomla\CMS\MVC\View\ViewInterface
     */
    public function getView($name = '', $type = '', $prefix = '', $config = [])
    {
        $name = $name ?: $this->input->getCmd('view', $this->default_view);
        $type = $type ?: 'html';

        try {
            return parent::getView($name, $type, $prefix, $config);
        } catch (\Throwable $e) {
            // Fall through to the legacy resolver below
        }

        $viewName  = preg_replace('/[^A-Z0-9_]/i', '', (string) $name);
        $viewType  = preg_replace('/[^A-Z0-9_]/i', '', (string) $type);
        $viewClass = 'K2View' . ucfirst($viewName);

        if (!class_exists($viewClass)) {
            $base = !empty($config['base_path']) ? rtrim($config['base_path'], '/\\') : $this->basePath;
            $file = $base . '/views/' . strtolower($viewName) . '/view.' . strtolower($viewType) . '.php';

            if (is_file($file)) {
                JLoader::register($viewClass, $file);
            }
        }

        if (!class_exists($viewClass)) {
            throw new \Exception(\Joomla\CMS\Language\Text::sprintf('JLIB_APPLICATION_ERROR_VIEW_NOT_FOUND', $name, $type, $viewClass), 404);
        }

        return new $viewClass($config);
    }

    /**
     * Load a model object.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for the model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|boolean
     */
    public function getModel($name = '', $prefix = '', $config = [])
    {
        $name   = $name ?: $this->input->getCmd('view', $this->default_view);
        $prefix = $prefix ?: 'K2Model';

        if (class_exists('K2Model')) {
            $model = K2Model::getInstance($name, $prefix, $config);

            if ($model) {
                return $model;
            }
        }

        return parent::getModel($name, $prefix, $config);
    }
}
