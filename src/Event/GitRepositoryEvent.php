<?php

namespace Gioffreda\Component\GitGuardian\Event;

use Gioffreda\Component\Git\Git;
use Gioffreda\Component\GitGuardian\Adapter\RepositoryInterface;

class GitRepositoryEvent extends GitEvent
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var string
     */
    private $action;

    /**
     * @var Git
     */
    private $git;

    /**
     * @return RepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @return Git
     */
    public function getGit()
    {
        return $this->git;
    }

    public static function prepare($name, RepositoryInterface $repository, $action, Git $git = null, array $data = null)
    {
        $return = new static($name);
        $return->repository = $repository;
        $return->action = $action;
        $return->git = $git;
        $return->data = $data ?: [];

        return $return;
    }
}
