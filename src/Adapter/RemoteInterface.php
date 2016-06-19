<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

interface RemoteInterface
{
    /**
     * @return RepositoryInterface[]
     */
    public function getRepositories();

    /**
     * @return string
     */
    public function getName();
}
