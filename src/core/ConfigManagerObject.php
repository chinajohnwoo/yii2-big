<?php
/**
 * @link http://www.bigbrush-agency.com/
 * @copyright Copyright (c) 2015 Big Brush Agency ApS
 * @license http://www.bigbrush-agency.com/license/
 */

namespace bigbrush\big\core;

use ArrayIterator;
use IteratorAggregate;
use Yii;
use yii\base\Object;
use yii\helpers\Html;

/**
 * ConfigManagerObject represents a section within [[ConfigManager]]. It provides methods for retrieving
 * config information from this section.
 *
 * A value in the this object can be accessed like it was an object property.
 *
 * @property array $data [[ManagerObject]]
 * @property string $id
 * @property string $value
 * @property string $section
 */
class ConfigManagerObject extends ManagerObject implements IteratorAggregate
{
    /**
     * @var string $section the section this config belongs to.
     */
    public $section;


    /**
     * Returns a config value from this section.
     * By using this method you will receive a default value if the specified name is not set in the config
     * for this section. If you retrieve a config value as a property ($config->property) an exception is thrown 
     * when the name is not set. 
     *
     * @param string $name the name of a config entry.
     * @param mixed $defaultValue a default value returned if $name could not be found in this config object.
     * @return mixed the config value.
     */
    public function get($name, $defaultValue = null)
    {
        return isset($this->data[$name]) ? $this->$name : $defaultValue;
    }

    /**
     * Returns this config as an zero-index array. Each array element has the entries ´id´ and value´.
     * The returned array can be used with [[yii\data\ArrayDataProvider]].
     *
     * @return array this config as an zero-indexed array.
     */
    public function asArray()
    {
        $data = [];
        foreach ($this->data as $key => $value) {
            $data[] = [
                'id' => $key,
                'value' => $value,
                'section' => $this->section,
            ];
        }
        return $data;
    }

    /**
     * Returns an iterator for traversing the attributes in the config.
     * This method is required by the interface [[\IteratorAggregate]].
     * @return ArrayIterator an iterator for traversing the items in the list.
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }
}
