<?php

namespace Gioffreda\Component\GitGuardian\Event;

use League\Event\Event;

class GitEvent extends Event
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
