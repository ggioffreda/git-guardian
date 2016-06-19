<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

use Gioffreda\Component\GitGuardian\Emitting;
use League\Event\Emitter;
use League\Event\EmitterInterface;

abstract class AbstractRemote implements RemoteInterface, Emitting
{
    /**
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $user;

    /**
     * @param EmitterInterface $emitter
     */
    public function setEmitter($emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * @return EmitterInterface
     */
    public function getEmitter()
    {
        if (null === $this->emitter) {
            $this->emitter = new Emitter();
        }

        return $this->emitter;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options = null)
    {
        $this->options = $options ?: [];
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }
}
