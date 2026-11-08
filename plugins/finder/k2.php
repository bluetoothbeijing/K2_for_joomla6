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

use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper as FinderHelper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;

jimport('joomla.application.component.helper');

/*
 * Joomla 4+ moved the finder indexer classes into the com_finder namespace.
 * Register the namespace explicitly so the adapter can be autoloaded even when
 * the plugin is instantiated before com_finder boots.
 */
if (!class_exists(Adapter::class)) {
    JLoader::registerNamespace('Joomla\\Component\\Finder\\Administrator', JPATH_ADMINISTRATOR . '/components/com_finder/src');
}

if (!class_exists(Adapter::class)) {
    // com_finder is not installed: register an inert plugin so the site keeps working
    class plgFinderK2 extends \Joomla\CMS\Plugin\CMSPlugin
    {
    }

    return;
}

class plgFinderK2 extends Adapter
{
    protected $context = 'K2';
    protected $extension = 'com_k2';
    protected $layout = 'item';
    protected $type_title = 'K2 Item';
    protected $table = '#__k2_items';
    protected $state_field = 'published';

    public function __construct(&$subject, $config)
    {
        // Adapter::__construct() understands the legacy (&$subject, $config)
        // signature and forwards both arguments to CMSPlugin.
        parent::__construct($subject, $config);

        if (PHP_SAPI === 'cli') {
            // JEventDispatcher was removed in Joomla 4 - trigger the system
            // plugin events through the application instead.
            try {
                \Joomla\CMS\Plugin\PluginHelper::importPlugin('system', 'k2');
                \Joomla\CMS\Factory::getApplication()->triggerEvent('onAfterInitialise');
            } catch (\Throwable $e) {
                // The system plugin is optional for CLI indexing
            }
        }

        $this->loadLanguage();
    }

    /**
     * Joomla 5+ delivers plugin events through the subscriber interface.
     *
     * The core Finder adapter only subscribes to the four indexer events, so
     * K2's own finder events have to be added explicitly - otherwise K2 items
     * would never be re-indexed when they are saved or deleted.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        $events = [];

        try {
            $events = parent::getSubscribedEvents();
        } catch (\Throwable $e) {
            // Older Adapter versions do not define it - the list below is enough
        }

        return array_merge((array) $events, [
            'onFinderAfterDelete' => 'onFinderAfterDelete',
            'onFinderAfterSave'   => 'onFinderAfterSave',
            'onFinderBeforeSave'  => 'onFinderBeforeSave',
            'onFinderChangeState' => 'onFinderChangeState',
        ]);
    }

    public function onFinderAfterDelete($context, $table)
    {
        if ($context == 'com_k2.item') {
            $id = $table->id;
        } elseif ($context == 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return true;
        }
        // Remove the items.
        return $this->remove($id);
    }

    public function onFinderAfterSave($context, $row, $isNew)
    {
        // We only want to handle items here
        if ($context == 'com_k2.item') {
            // Check if the access levels are different
            if (!$isNew && $this->old_access != $row->access) {
                // Process the change.
                $this->itemAccessChange($row);
            }

            // Reindex the item
            $this->reindex($row->id);
        }

        // Check for access changes in the category
        if ($context == 'com_k2.category') {
            // Update the state
            $this->categoryStateChange(array($row->id), $row->published);

            // Check if the access levels are different
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }

        return true;
    }

    public function onFinderBeforeSave($context, $row, $isNew)
    {
        // We only want to handle items here
        if ($context == 'com_k2.item') {
            // Query the database for the old access level if the item isn't new
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }

        // Check for access levels from the category
        if ($context == 'com_k2.category') {
            // Query the database for the old access level if the item isn't new
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }

        return true;
    }

    public function onFinderChangeState($context, $pks, $value)
    {
        // Items
        if ($context == 'com_k2.item') {
            $this->itemStateChange($pks, $value);
        }
        // Categories
        if ($context == 'com_k2.category') {
            $this->categoryStateChange($pks, $value);
        }
    }

    protected function index(Result $item)
    {
        // Check if the extension is enabled
        if (JComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        // Initialize the item parameters.
        $registry = new JRegistry;
        $registry->loadString($item->params);
        $item->params = JComponentHelper::getParams('com_k2', true);
        $item->params->merge($registry);

        $registry = new JRegistry;
        $registry->loadString($item->metadata);
        $item->metadata = $registry;

        // Trigger the onContentPrepare event.
        $item->summary = FinderHelper::prepareContent($item->summary, $item->params);
        $item->body = FinderHelper::prepareContent($item->body, $item->params);

        // Build the necessary route and path information.
        $item->url = $this->getURL($item->id, $this->extension, $this->layout);
        $item->route = K2HelperRoute::getItemRoute($item->slug, $item->catslug);
        $item->path = FinderHelper::getContentPath($item->route);

        // Get the menu title if it exists.
        $title = $this->getItemMenuTitle($item->url);

        // Adjust the title if necessary.
        if (!empty($title) && $this->params->get('use_menu_title', true)) {
            $item->title = $title;
        }

        // Add the meta-author.
        $item->metaauthor = $item->metadata->get('author');

        // Add the meta-data processing instructions.
        $item->addInstruction(Indexer::META_CONTEXT, 'metakey');
        $item->addInstruction(Indexer::META_CONTEXT, 'metadesc');
        $item->addInstruction(Indexer::META_CONTEXT, 'metaauthor');
        $item->addInstruction(Indexer::META_CONTEXT, 'author');
        $item->addInstruction(Indexer::META_CONTEXT, 'created_by_alias');
        $item->addInstruction(Indexer::META_CONTEXT, 'extra_fields_search');

        // Translate the state. Items should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);

        // Translate the trash state. Items should only be accesible if the category is accessible.
        if ($item->trash || $item->cat_trash) {
            $item->state = 0;
        }

        // Add the type taxonomy data.
        $item->addTaxonomy('Type', 'K2 Item');

        // Add the author taxonomy data.
        if (!empty($item->author) || !empty($item->created_by_alias)) {
            $item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author);
        }

        // Add the category taxonomy data.
        $item->addTaxonomy('K2 Category', $item->category, $item->cat_state, $item->cat_access);

        // Add the language taxonomy data.
        $item->addTaxonomy('Language', $item->language);

        // Add the extra_fields data.
        $item->addTaxonomy('Extra fields', $item->extra_fields);

        // Get content extras.
        FinderHelper::getContentExtras($item);

        // Index the item.
        $this->indexer->index($item);
    }

    protected function setup()
    {
        // Load dependent classes.
        include_once JPATH_SITE.'/components/com_k2/helpers/route.php';

        return true;
    }

    protected function getListQuery($sql = null)
    {
        $db = JFactory::getDbo();
        // Check if we can use the supplied SQL query.
        // "JDatabaseQuery" no longer exists in Joomla 4+ - the query object now
        // implements Joomla\Database\QueryInterface.
        $sql = is_a($sql, \Joomla\Database\QueryInterface::class) ? $sql : $db->getQuery(true);
        $sql->select('a.id, a.title, a.alias, a.introtext AS summary, a.fulltext AS body');
        $sql->select('a.published as state, a.catid, a.created AS start_date, a.created_by');
        $sql->select('a.created_by_alias, a.modified, a.modified_by, a.params');
        $sql->select('a.metakey, a.metadesc, a.metadata, a.language, a.access, a.ordering');
        $sql->select('a.publish_up AS publish_start_date, a.publish_down AS publish_end_date');
        $sql->select('a.trash, c.trash AS cat_trash');
        $sql->select('c.name AS category, c.published AS cat_state, c.access AS cat_access');
        $sql->select('a.extra_fields_search, a.extra_fields');

        // Handle the alias CASE WHEN portion of the query
        $case_when_item_alias = ' CASE WHEN ';
        $case_when_item_alias .= $sql->charLength('a.alias');
        $case_when_item_alias .= ' THEN ';
        $a_id = $sql->castAs('CHAR', 'a.id');
        $case_when_item_alias .= $sql->concatenate(array($a_id, 'a.alias'), ':');
        $case_when_item_alias .= ' ELSE ';
        $case_when_item_alias .= $a_id.' END as slug';
        $sql->select($case_when_item_alias);

        $case_when_category_alias = ' CASE WHEN ';
        $case_when_category_alias .= $sql->charLength('c.alias');
        $case_when_category_alias .= ' THEN ';
        $c_id = $sql->castAs('CHAR', 'c.id');
        $case_when_category_alias .= $sql->concatenate(array($c_id, 'c.alias'), ':');
        $case_when_category_alias .= ' ELSE ';
        $case_when_category_alias .= $c_id.' END as catslug';
        $sql->select($case_when_category_alias);

        $sql->select('u.name AS author');
        $sql->from('#__k2_items AS a');
        $sql->join('LEFT', '#__k2_categories AS c ON c.id = a.catid');
        $sql->join('LEFT', '#__users AS u ON u.id = a.created_by');

        return $sql;
    }

    protected function checkCategoryAccess($row)
    {
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName('access'));
        $query->from($this->db->quoteName('#__k2_categories'));
        $query->where($this->db->quoteName('id').' = '.(int)$row->id);
        $this->db->setQuery($query);

        // Store the access level to determine if it changes
        $this->old_cataccess = $this->db->loadResult();
    }

    protected function categoryAccessChange($row)
    {
        $sql = clone($this->getStateQuery());
        $sql->where('c.id = '.(int)$row->id);

        // Get the access level.
        $this->db->setQuery($sql);
        $items = $this->db->loadObjectList();

        // Adjust the access level for each item within the category.
        foreach ($items as $item) {
            // Set the access level.
            $temp = max($item->access, $row->access);

            // Update the item.
            $this->change((int)$item->id, 'access', $temp);

            // Reindex the item
            $this->reindex($item->id);
        }
    }
}
