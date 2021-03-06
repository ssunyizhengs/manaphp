<?php
namespace ManaPHP\Serializer\Adapter;

use ManaPHP\Exception\UnexpectedValueException;
use ManaPHP\Serializer\AdapterInterface;

/**
 * Class ManaPHP\Serializer\Adapter\Php
 *
 * @package serializer\adapter
 */
class Php implements AdapterInterface
{
    /**
     * @param mixed $data
     *
     * @return string
     */
    public function serialize($data)
    {
        if (!is_array($data)) {
            $data = ['__wrapper__' => $data];
        }

        return serialize($data);
    }

    /**
     * @param string $serialized
     *
     * @return mixed
     */
    public function deserialize($serialized)
    {
        $data = unserialize($serialized);
        if ($data === false) {
            throw new UnexpectedValueException('unserialize failed: :last_error_message');
        }

        if (!is_array($data)) {
            throw new UnexpectedValueException('de serialized data is not a array maybe it has been corrupted');
        }

        if (isset($data['__wrapper__']) && count($data) === 1) {
            return $data['__wrapper__'];
        } else {
            return $data;
        }
    }
}