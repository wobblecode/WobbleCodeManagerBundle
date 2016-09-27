<?php

namespace WobbleCode\ManagerBundle\Traits\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

trait Attributable
{
    /**
     * Example of schema
     *
     *     {
     *       "intercom": {
     *         "id": "23"
     *       }
     *     }
     *
     * @ODM\Hash
     */
    protected $attributes = [];

    /**
     * Set attributes
     *
     * @param array $attributes
     * @return self
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set attribute
     *
     * @param string $key
     * @param mixed $attributes
     *
     * @return self
     */
    public function setAttribute($key, $data)
    {
        $this->attributes[$key] = $data;

        return $this;
    }

    /**
     * Get attribute by key
     *
     * @param string $group
     *
     * @return array
     */
    public function getAttribute($key)
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Set attributes by group and key
     *
     * @param string $group
     * @param string $key
     * @param array $attributes
     *
     * @return self
     */
    public function setAttributeInGroup($group, $key, $value)
    {
        $this->attributes[$group][$key] = $value;

        return $this;
    }

    /**
     * Get attribute by group and key. It will return null if doesn't exists
     *
     * @param array $group
     * @param string $key
     *
     * @return string|null
     */
    public function getAttributeInGroup($group, $key)
    {
        if (isset($this->attributes[$group][$key])) {
            return $this->attributes[$group][$key];
        }

        return null;
    }
}
