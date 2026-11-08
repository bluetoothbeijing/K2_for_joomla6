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
 * Base view for K2.
 *
 * Extends the namespaced Joomla view class directly (JViewLegacy no longer
 * exists in Joomla 6) and keeps the YOOtheme PRO integration working through
 * the Joomla 4+ event dispatcher.
 */
#[\AllowDynamicProperties]
class K2View extends \Joomla\CMS\MVC\View\HtmlView
{
    /**
     * Assign variables to the view script via referencing.
     *
     * Removed in Joomla 4 (JViewLegacy::assignRef), but used in 180+ places
     * by K2's views - restored here with the original Joomla 3 semantics.
     *
     * @param   string  $key  The name of the variable
     * @param   mixed   $val  The referenced value
     *
     * @return  bool
     */
    public function assignRef($key, &$val)
    {
        if (is_string($key) && $key !== '' && substr($key, 0, 1) !== '_') {
            $this->$key = &$val;

            return true;
        }

        return false;
    }

    /**
     * Assign variables to the view script.
     *
     * Removed in Joomla 4 (JViewLegacy::assign), restored with the original
     * Joomla 3 semantics.
     *
     * @param   string|array  $key  The name of the variable (or an assoc array)
     * @param   mixed         $val  The value
     *
     * @return  bool
     */
    public function assign($key, $val = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if (is_string($k) && $k !== '' && substr($k, 0, 1) !== '_') {
                    $this->$k = $v;
                }
            }

            return true;
        }

        if (is_string($key) && $key !== '' && substr($key, 0, 1) !== '_') {
            $this->$key = $val;

            return true;
        }

        return false;
    }

    /**
     * Display the view.
     *
     * @param   string|null  $tpl  The name of the template file to parse
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        // Allow for YOOtheme PRO Integration
        $app = \Joomla\CMS\Factory::getApplication();

        // Only in YOOtheme PRO
        if ($app->isClient('site') && stripos((string) $app->getTemplate(), 'yootheme') === 0) {
            // Trigger the custom YOOtheme Pro event
            try {
                $dispatcher = $app->getDispatcher();
                $event      = new \Joomla\Event\Event('onLoadTemplate', [$this, $tpl]);
                $dispatcher->dispatch('onLoadTemplate', $event);
            } catch (\Throwable $e) {
                // Never let a third party integration break the page
            }

            // If the event overrode the output, print that output and don't display anything else
            if (!empty($this->_output)) {
                echo $this->_output;

                return;
            }
        }

        parent::display($tpl);
    }
}
