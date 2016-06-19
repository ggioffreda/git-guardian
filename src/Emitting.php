<?php

namespace Gioffreda\Component\GitGuardian;

use League\Event\EmitterInterface;

interface Emitting
{
    /**
     * @return EmitterInterface
     */
    public function getEmitter();
}
