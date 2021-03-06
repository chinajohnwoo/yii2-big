<?php
/**
 * @link http://www.bigbrush-agency.com/
 * @copyright Copyright (c) 2015 Big Brush Agency ApS
 * @license http://www.bigbrush-agency.com/license/
 */

namespace bigbrush\big\core;

use Yii;
use yii\base\InvalidParamException;
use yii\base\InvalidConfigException;
use yii\base\Object;
use yii\helpers\Json;

/**
 * MenuManager provides an interface for managing a nested set of menu items.
 * You can grab all created menus like so:
 *
 * ~~~php
 * $menus = Yii::$app->big->menuManager->getMenus();
 * ~~~
 *
 * One menu item will always be defined as default and can not be deleted. You can grab the
 * default menu, which is the menu with the default menu item, like so:
 *
 * ~~~php
 * $items = Yii::$app->big->menuManager->getItems();
 * ~~~
 *
 * Or you can get all menu items from a specific menu by specifying a menu ID:
 *
 * ~~~php
 * $items = Yii::$app->big->menuManager->getItems('ID OF A MENU');
 * ~~~
 *
 */
class MenuManager extends Object implements ManagerInterface
{
    use NestedSetManagerTrait {
        getItems as _getItems;
        createObject as _createObject;
    }

    /**
     * @var boolean whether to load all menus automatically when the manager initializes.
     * If true this manager will not make any additional database calls when searching - see [[search()]].
     */
    public $autoload = false;
    /**
     * @var int defines an id for the menu that holds the default menu item.
     * If this property is not set it will be autoloaded in [[getDefaultMenu()]].
     * @see getDefault()
     * @see getDefaultMenu()
     */
    private $_default;
    /**
     * @var MenuManagerObject|null a menu item or null if no active menu item has been set.
     * @see setActive()
     * @see getActive()
     */
    private $_active;


    /**
     * Initializes the manager by autoloading all menus if [[autoload]] is enabled.
     * Also sets up trait properties.
     */
    public function init()
    {
        // set properties defined in trait if not set by application configuration.
        if ($this->itemClass === 'bigbrush\big\core\ManagerObject') {
            $this->itemClass = 'bigbrush\big\core\MenuManagerObject';
        }
        if ($this->modelClass === null) {
            $this->modelClass = 'bigbrush\big\models\Menu';
        }

        // check model of this manager has NestedSetsBehavior attached
        if ($this->getModel()->hasProperty('leftAttribute') === false) {
            throw new InvalidConfigException("ActiveRecord used in MenuManager must have the 'NestedSetsBehavior' attached");
        }

        // load all menus and menu items when autoload is enabled
        if ($this->autoload) {
            $this->getMenus(true);
        }
    }

    /**
     * Searches for an item where the provided property matches the provided value.
     *
     * If [[autoload]] is enabled a database call will not be made. If
     * [[autoload]] is not enabled a database call will be made if a menu is not found
     * in the currently loaded menu items.
     * False is returned if no matching menu item is found.
     *
     * @param string $property the property to compare against.
     * @param mixed $value the value to search for.
     * @return MenuManagerObject|false a menu item if found and false if not.
     */
    public function search($property, $value)
    {
        // search in loaded menu items
        if ($item = $this->searchItems($property, $value)) {
            return $item;
        }

        // query the database when autoload is not enabled to make sure whether the menu item exists
        if (!$this->autoload) {
            $menu = $this->find()->andWhere([$property => $value])->one();
            if ($menu) {
                return $this->createObject($menu);
            }
        }
        return false;
    }

    /**
     * Returns a list of all menus.
     * This method loads all menus and menu items if it is the first method called in the manager
     * or when reload is true. If [[autoload]] is enabled no reload is performed.
     *
     * @param boolean $reload indicates whether the whole tree should be reloaded regardless
     * if any trees has been loaded before.
     * @return array list of all menus.
     */
    public function getMenus($reload = false)
    {
        // no need to reload if manager has autoload enabled and has already loaded
        if ($this->autoload && $this->_default !== null) {
            $reload = false;
        }
        $menus = $this->getRoots($reload);
        if ($this->_default === null) {
            $this->identifyDefaultMenu();
        }
        return $menus;
    }

    /**
     * Returns a list of all menus.
     *
     * @param int $id the id of a menu or a menu item within the same menu.
     * @return array list of all menu items from the menu with the provided id.
     * @throws InvalidParamException if id is provided and the menu items could not be found.
     */
    public function getItems($id = 0)
    {
        if ($id) {
            return $this->_getItems($id);
        } else {
            return $this->getDefaultMenu();
        }
    }

    /**
     * Returns the default menu item.
     *
     * @return MenuManagerObject the default menu item.
     * @throws InvalidParamException if a default menu item has not been set.
     */
    public function getDefault()
    {
        $items = $this->getDefaultMenu();
        if (!empty($items)) {
            foreach ($items as $item) {
                if ($item->getIsDefault()) {
                    return $item;
                }
            }
        } else {
            throw new InvalidParamException("No default menu item has been set in bigbrush\big\core\MenuManager.");
        }
    }

    /**
     * Returns all menu items of the menu that holds the default menu item.
     *
     * @return array list of menu items. Empty array if no default menu item has been set.
     */
    public function getDefaultMenu()
    {
        if ($this->_default) {
            return $this->_getItems($this->_default);
        } elseif ($this->loadTree('is_default', 1)) {
            $this->identifyDefaultMenu();
            return $this->_getItems($this->_default);
        } else {
            return $this->_roots = []; // flags menus as loaded.
        }
    }

    /**
     * Sets the active menu item.
     *
     * @param MenuManagerObject|bigbrush\big\models\Menu $menu a menu object to register as active.
     */
    public function setActive($menu)
    {
        $this->_active = $menu;
    }

    /**
     * Returns the active menu item.
     * If [[setApplicationDefaultRoute]] is enabled a default menu will always be registered.
     * 
     * @return MenuManagerObject|null the active menu if set and null if not.
     * @throws InvalidParamException see [[getDefault()]].
     */
    public function getActive()
    {
        if ($this->_active !== null) {
            return $this->_active;
        } else {
            return $this->_active = $this->getDefault();;
        }
    }

    /**
     * Creates a drop down menu ready for [[yii\bootstrap\Nav]] and [[yii\widgets\Menu]].
     *
     * @param array $menus list of menus to nest in an array.
     * @return array nested array ready for a drop down menu.
     */
    public function createDropDownMenu(&$menus)
    {
        $items = [];
        $active = $this->getActive();
        while (list($id, $menu) = each($menus)) {
            $items[$id] = [
                'label' => $menu->title,
                'url' => $menu->getUrl(),
                'active' => $menu->id == $active->id,
                'visible' => $menu->getIsEnabled(),
            ];
            if ($menu->rgt - $menu->lft != 1) {
                $items[$id]['items'] = $this->createDropDownMenu($menus);
            }
            $next = key($menus);
            if ($next && $menus[$next]->depth != $menu->depth) {
                return $items;
            }
        }
        return $items;
    }

    /**
     * Registers menus when big triggers a search.
     * See [[bigbrush\big\core\Big::search()]] for more information about the search process.
     *
     * @param SearchEvent $event the event being triggered
     */
    public function onSearch($event)
    {
        $depthAttribute = $this->getDatabaseColumnName('depth');
        foreach ($this->getMenus(true) as $root) {
            foreach ($this->getItems($root->id) as $menu) {
                if (!empty($event->value) && strpos($menu->title, $event->value) === false) {
                    continue;
                }
                if ($menu->lft != 1) {
                    $event->addItem([
                        'title' => str_repeat('- ', $menu->$depthAttribute - 1) . $menu->title,
                        'route' => $menu->route,
                        'text' => '',
                        'date' => '',
                        'section' => Yii::t('big', 'Menus'),
                    ]);
                }
            }
        }
    }

    /**
     * Creates an item object used when creating trees.
     * Overloaded to decode the params property to an array.
     *
     * @param array $data configuration array for the object.
     * @return ManagerObject or a subclass.
     * @see [[createTree()]]
     */
    public function createObject(array $data)
    {
        if (!empty($data['params'])) {
            $data['params'] = Json::decode($data['params']);
        }
        return $this->_createObject($data);
    }

    /**
     * Used internally to find an id of the menu holding the default menu item. Only loaded menu items is searched.
     * The found menu id is stored in [[_default]].
     *
     * @return int an id of the menu holding the default menu item.
     */
    protected function identifyDefaultMenu()
    {
        foreach ($this->getRoots() as $menu) {
            foreach ($this->_getItems($menu->id) as $item) {
                if ($item->getIsDefault()) {
                    return $this->_default = $menu->id;
                }
            }
        }
    }
}
