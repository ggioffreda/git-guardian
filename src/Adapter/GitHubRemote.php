<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

use Gioffreda\Component\GitGuardian\Event\GitRepositoryEvent;
use Gioffreda\Component\GitGuardian\Exception\AdapterException;
use GuzzleHttp\Psr7\Response;

class GitHubRemote extends AbstractRemote
{
    const REMOTE_CANONICAL_NAME = 'github.com';

    public function __construct()
    {
        parent::__construct();
        $this->endpoints = [
            'repositories' => 'https://api.github.com/user/repos?per_page=100',
            'public_repositories' => 'https://api.github.com/%s/repos?per_page=100'
        ];
    }

    public function getRepositories()
    {
        $repositories = [];

        $endpoint = $this->getUser() ?
            sprintf($this->endpoints['public_repositories'], $this->getUser()) :
            sprintf($this->endpoints['repositories']);
        $authenticationPart = $this->getAuthenticationPart();

        do {
            /** @var Response $response */
            $response = $this->client->get($endpoint, $authenticationPart);

            if (200 !== $response->getStatusCode()) {
                throw new AdapterException(sprintf('Response is not OK for "%s"', $endpoint));
            }

            $chunk = json_decode($response->getBody()->getContents(), true);

            foreach ($chunk as $definition) {
                $repository = new GitHubRepository(
                    $definition['full_name'],
                    $definition['description'],
                    $definition['clone_url']
                );
                $repository->setSize($definition['size']);
                $repository->setPrivate($definition['private']);
                $repository->setUpdatedAt(new \DateTime($definition['updated_at']));
                $repository->setRemote($this);

                $this->getEmitter()->emit(GitRepositoryEvent::prepare(
                    'git_remote.repository_discovery',
                    $repository,
                    'discovery',
                    null,
                    ['definition' => $definition]
                ));

                $repositories[] = $repository;
            }

            $endpoint = null;
            $headerLinks = $response->getHeader('link');
            if (count($headerLinks)) {
                $links = explode(',', $headerLinks[0]);
                foreach ($links as $link) {
                    $matches = [];
                    if (preg_match('/^<([^>]+)>; rel="next"$/', trim($link), $matches)) {
                        $endpoint = $matches[1];
                    }
                }
            }
        } while (isset($chunk) && is_array($chunk) && count($chunk) > 0 && $endpoint);

        return $repositories;
    }
    
    public function getName()
    {
        return self::REMOTE_CANONICAL_NAME;
    }

    public function getAuthenticationPart()
    {
        if (isset($this->options['username']) && isset($this->options['personal_token'])) {
            return [ 'auth' => [ $this->options['username'], $this->options['personal_token'] ] ];
        }

        return [];
    }
}
