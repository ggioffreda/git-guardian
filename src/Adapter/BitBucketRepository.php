<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

class BitBucketRepository extends AbstractRepository
{
    public function setRemote(RemoteInterface $remote)
    {
        if (!($remote instanceof BitBucketRemote)) {
            throw new \InvalidArgumentException('Only BitBucket remotes are supported for this repository');
        }

        parent::setRemote($remote);
    }

    /**
     * @return string
     */
    public function getUri()
    {
        $token = $this->getRemote()->getToken();

        if (isset($token['access_token'])) {
            return preg_replace(
                '/^https:\\/\\/[^@]+/',
                'https://x-token-auth:'.$token['access_token'],
                $this->getAnonymousUri()
            );
        }

        return $this->getAnonymousUri();
    }
}
