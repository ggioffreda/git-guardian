<?php

namespace Gioffreda\Component\GitGuardian\Adapter;

use Gioffreda\Component\GitGuardian\Event\GitRemoteEvent;
use Gioffreda\Component\GitGuardian\Event\GitRepositoryEvent;
use Gioffreda\Component\GitGuardian\Exception\AdapterException;
use GuzzleHttp\Psr7\Response;

class BitBucketRemote extends AbstractRemote
{
    /**
     * @var array
     */
    private $token;

    public function __construct()
    {
        parent::__construct();
        $this->endpoints = [
            'oauth2' => 'https://bitbucket.org/site/oauth2/access_token',
            'repositories' => 'https://api.bitbucket.org/2.0/repositories/%s'
        ];
    }

    public function getName()
    {
        return 'bitbucket.org';
    }

    /**
     * @return RepositoryInterface[]
     */
    public function getRepositories()
    {
        $owner = $this->getUser();
        $repositories = [];

        $page = 1;
        do {
            $response = $this->sendClientRequest('get', sprintf(
                $this->endpoints['repositories'],
                $owner.'?'.http_build_query(['page' => $page, 'format' => 'json'])
            ), [], true);

            $chunk = json_decode($response->getBody()->getContents(), true);

            foreach ($chunk['values'] as $definition) {
                $clone = null;
                foreach ($definition['links']['clone'] as $link) {
                    if ('https' === $link['name']) {
                        $clone = $link['href'];
                        break;
                    }
                }

                $repository = new BitBucketRepository($definition['full_name'], $definition['description'], $clone);
                $repository->setSize($definition['size']);
                $repository->setPrivate($definition['is_private']);
                $repository->setUpdatedAt(new \DateTime($definition['updated_on']));
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

            $page++;
        } while (isset($chunk) && is_array($chunk) && isset($chunk['next']) && count($chunk['values']) > 0);

        return $repositories;
    }

    /**
     * @return array
     */
    public function getToken()
    {
        if (!isset($this->options['client_id']) || !isset($this->options['client_secret'])) {
            return [];
        }

        if (!$this->token || $this->token['expires_at'] < new \DateTime('+5 minutes')) {
            $response = $this->sendClientRequest('post', $this->endpoints['oauth2'], [
                'body' => http_build_query([
                    'grant_type'    => 'client_credentials',
                    'client_id'     => isset($this->options['client_id']) ? $this->options['client_id'] : null,
                    'client_secret' => isset($this->options['client_secret']) ? $this->options['client_secret'] : null,
                    'scope'         => 'repository'
                ]),
                'headers' => [
                    'Content-type'  => 'application/x-www-form-urlencoded'
                ]
            ]);

            $this->token = json_decode($response->getBody()->getContents(), true);
            $this->token['expires_at'] = new \DateTime(
                isset($this->token['expires_in']) ? "+{$this->token['expires_in']} seconds" : '+1 hour'
            );

            $this->getEmitter()->emit(GitRemoteEvent::prepare('git_remote.access_token', $this));
        }

        return $this->token;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array|null $options
     * @param bool $requireAuth
     * @return Response
     */
    public function sendClientRequest($method, $endpoint, array $options = null, $requireAuth = false)
    {
        if ($requireAuth) {
            $token = $this->getToken();
            if ($token) {
                $options = array_merge_recursive($options ?: [], [
                    'headers' => [
                        'Authorization' => sprintf(
                            '%s %s',
                            ucfirst(strtolower($token['token_type'])),
                            $token['access_token']
                        )
                    ]
                ]);
            }
        }

        $this->getEmitter()->emit(GitRemoteEvent::prepare('git_remote.client_request', $this, ['endpoint' => $endpoint]));

        /** @var Response $response */
        $response = $this->client->{$method}($endpoint, $options ?: []);

        if (200 !== $response->getStatusCode()) {
            throw new AdapterException(sprintf('Response is not OK for "%s"', $endpoint));
        }

        return $response;
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
}
