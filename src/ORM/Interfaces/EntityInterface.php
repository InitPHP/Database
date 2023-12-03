<?php

namespace InitPHP\Database\ORM\Interfaces;

interface EntityInterface
{

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @return array
     */
    public function getAttributes(): array;

}
