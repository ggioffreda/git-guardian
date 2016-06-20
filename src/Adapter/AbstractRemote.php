<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

use Gioffreda\Component\GitGuardian\Emitting;
use GuzzleHttp\Client;
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
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $endpoints = [];

    /**
     * BitBucketRemote constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
    }

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

    /**
     * @return array
     */
    public function getEndpoints()
    {
        return $this->endpoints;
    }

    /**
     * @param string $name
     * @param string $endpoint
     */
    public function setEndpoint($name, $endpoint)
    {
        $this->endpoints[$name] = $endpoint;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }
}
