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
     * @return int
     */
    public function getSize();

    /**
     * @return bool
     */
    public function isPrivate();

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
