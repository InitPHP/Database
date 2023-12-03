<?php

namespace InitPHP\Database\ORM\Exceptions;

use Exception;

class EntityNotMethod extends Exception
{
    public function __construct(string $name)
    {
        parent::__construct('There is no "' . $name . '" method.');
    }

}
