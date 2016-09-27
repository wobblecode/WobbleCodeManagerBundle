<?php

namespace WobbleCode\ManagerBundle\Traits\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

trait Taggeable
{
    /**
     * @ODM\Collection
     * @Serializer\Expose
     * @Serializer\Groups({"ui"})
     */
    protected $tags = [];

    /**
     * Set tags
     *
     * @param collection $tags
     *
     * @return self
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Get tags
     *
     * @return collection $tags
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Add tag
     *
     * @return collection $tags
     *
     * @return self
     */
    public function addTag($item)
    {
        if (!in_array($item, $this->tags)) {
            $this->tags[] = $item;
        }

        return $this;
    }

    /**
     * Remove tag
     *
     * @return collection $tags
     *
     * @return self
     */
    public function removeTag($item)
    {
        if (($key = array_search($item, $this->tags)) !== false) {
            unset($this->tags[$key]);
        }

        return $this;
    }
}
