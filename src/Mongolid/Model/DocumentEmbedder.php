<?php

namespace Mongolid\Model;

use MongoDB\BSON\ObjectID;

/**
 * Document embeder is a service that will embed documents within each other.
 *
 * @package Mongolid
 */
class DocumentEmbedder
{
    /**
     * Embeds the given $entity into $field of $parent. This method will also
     * consider the _id of the $entity in order to update it if it is already
     * present in $field.
     *
     * @param  object       $parent   The object where the $entity will be embedded
     * @param  string       $field    Name of the field of the object where the document will be embedded
     * @param  mixed        $entity   Entity that will be embedded within $parent
     *
     * @return bool   Success
     */
    public function embed($parent, string $field, &$entity): bool
    {
        // In order to update the document if it exists inside the $parent
        $this->unembed($parent, $field, $entity);

        $fieldValue     = $parent->$field;
        $fieldValue[]   = $entity;
        $parent->$field = array_values($fieldValue);

        return true;
    }

    /**
     * Removes the given $entity from $field of $parent. This method will
     * consider the _id of the $entity in order to remove it
     *
     * @param  object       $parent   The object where the $entity will be removed
     * @param  string       $field    Name of the field of the object where the document is
     * @param  mixed        $entity   Entity that will be removed from $parent
     *
     * @return bool   Success
     */
    public function unembed($parent, string $field, &$entity): bool
    {
        $fieldValue = $this->prepareField($parent, $field);
        $id = $this->getId($entity);

        foreach ($fieldValue as $key => $document) {
            if ($id == $this->getId($document)) {
                unset($fieldValue[$key]);
            }
        }

        $parent->field = array_values($fieldValue);

        return true;
    }

    /**
     * Attach a new _id reference into $field of $parent
     *
     * @param  object $parent  The object where $entity will be referenced
     * @param  string $field   The field where the _id reference of $entity will be stored
     * @param  object|array &$entity
     *
     * @return bool          Success
     */
    public function attach($parent, string $field, &$entity): bool
    {
        $fieldValue = $this->prepareField($parent, $field);
        $newId = $this->getId($entity);

        foreach ($fieldValue as $id) {
            if ($id == $newId) {
                return true;
            }
        }

        $fieldValue[] = $newId;
        $parent->$field = $fieldValue;

        return true;
    }

    /**
     * Removes an _id reference from $field of $parent
     *
     * @param  object $parent  The object where $entity reference will be removed
     * @param  string $field   The field where the _id reference of $entity is stored
     * @param  object|array &$entity
     *
     * @return bool          Success
     */
    public function detach($parent, string $field, &$entity): bool
    {
        $fieldValue = $this->prepareField($parent, $field);
        $newId = $this->getId($entity);

        foreach ($fieldValue as $key => $id) {
            if ($id == $newId) {
                unset($fieldValue[$key]);
            }
        }

        $parent->$field = array_values($fieldValue);
    }

    /**
     * Gets the _id of the given object or array. If there is no _id in it a new
     * _id will be generated and set on the object (while still returning it)
     *
     * @param  array|object &$object The object|array that the _id will be retrieved from
     *
     * @return ObjectID|mixed
     */
    protected function getId(&$object)
    {
        if (is_array($object)) {
            if (isset($object['_id']) && $object['_id']) {
                return $object['_id'];
            }
            return $object['_id'] = new ObjectID;
        }

        if (is_object($object) && ! $object instanceof ObjectID) {
            if (isset($object->_id) && $object->_id) {
                return $object->_id;
            }
            return $object->_id = new ObjectID;
        }

        return $object;
    }

    /**
     * Will transform the given $field of the $object into an array in order
     * to be able to place new objects within it.
     *
     * @param  object $object
     * @param  string $field  Field name
     *
     * @return array
     */
    protected function prepareField($object, string $field): array
    {
        return (array)$object->$field;
    }
}
