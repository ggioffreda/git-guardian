<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

interface RepositoryInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getAnonymousUri();

    /**
     * @return string
     */
    public function getUri();

    /**
     * @return \DateTime
     */
    public function getUpdatedAt();

    /**
     * @return RemoteInterface
     */
    public function getRemote();
}
