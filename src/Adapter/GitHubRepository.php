<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

class GitHubRepository extends AbstractRepository
{
    public function setRemote(RemoteInterface $remote)
    {
        if (!($remote instanceof GitHubRemote)) {
            throw new \InvalidArgumentException('Only GitHub remotes are supported for this repository');
        }

        parent::setRemote($remote);
    }

    /**
     * @return string
     */
    public function getUri()
    {
        $authenticationPart = $this->getRemote()->getAuthenticationPart();

        if (isset($authenticationPart['auth'])) {
            return str_replace(
                'https://',
                'https://'.$authenticationPart['auth'][0].':'.$authenticationPart['auth'][1].'@',
                $this->getAnonymousUri()
            );
        }

        return $this->getAnonymousUri();
    }
}
