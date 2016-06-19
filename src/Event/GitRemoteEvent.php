<?php

namespace Gioffreda\Component\GitGuardian\Event;

use Gioffreda\Component\GitGuardian\Adapter\RemoteInterface;

class GitRemoteEvent extends GitEvent
{
    /**
     * @var RemoteInterface
     */
    private $remote;

    /**
     * @return RemoteInterface
     */
    public function getRemote()
    {
        return $this->remote;
    }

    public static function prepare($name, RemoteInterface $remote, array $data = null)
    {
        $return = new static($name);
        $return->remote = $remote;
        $return->data = $data ?: [];

        return $return;
    }
}
