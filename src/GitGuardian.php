<?php

namespace Gioffreda\Component\GitGuardian;

use Gioffreda\Component\Git\Git;
use Gioffreda\Component\GitGuardian\Adapter\RemoteInterface;
use Gioffreda\Component\GitGuardian\Adapter\RepositoryInterface;
use Gioffreda\Component\GitGuardian\Event\GitRemoteEvent;
use Gioffreda\Component\GitGuardian\Event\GitRepositoryEvent;
use League\Event\Emitter;
use League\Event\EmitterInterface;
use Symfony\Component\Filesystem\Filesystem;

class GitGuardian implements Emitting
{
    /**
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * @var RemoteInterface[]
     */
    private $remotes = [];

    /**
     * @var array
     */
    private $defaultOptions = [
        'clone_config' => '.git-guardian.clone.config.json'
    ];

    /**
     * @param RepositoryInterface $repository
     * @param $destination
     * @param array|null $options
     */
    public function cloneRepository(RepositoryInterface $repository, $destination, array $options = null)
    {
        $options = array_merge($this->defaultOptions, $options ?: []);
        $configFile = DIRECTORY_SEPARATOR === $options['clone_config'][0] ?
            $options['clone_config'] : $destination.DIRECTORY_SEPARATOR.$options['clone_config'];

        $fs = new Filesystem();
        if (!$fs->exists($configFile)) {
            $fs->touch($configFile);
        }

        /** @var RepositoryInterface $repository */
        $path = $destination.DIRECTORY_SEPARATOR.$repository->getName();

        if (!$fs->exists($path)) {
            $fs->mkdir($path);
        }

        if (!Git::isInitialized($path)) {
            $this->getEmitter()
                ->emit(GitRepositoryEvent::prepare(
                    'git_guardian.pre_clone_repository',
                    $repository,
                    'clone',
                    null,
                    ['path' => $path]
                ));
            $git = Git::cloneRemote($repository->getUri(), $path);
            $this->getEmitter()
                ->emit(GitRepositoryEvent::prepare(
                    'git_guardian.post_clone_repository',
                    $repository,
                    'clone',
                    $git
                ));
        }

        if (!isset($git)) {
            $git = Git::create($path);
            $this->getEmitter()
                ->emit(GitRepositoryEvent::prepare(
                    'git_guardian.create_git',
                    $repository,
                    'create',
                    $git
                ));
        }

        $log = json_decode(file_get_contents($configFile) ?: '[]', true);
        if (isset($log[$repository->getName()]) &&
            new \DateTime($log[$repository->getName()]['fetched_at']) > $repository->getUpdatedAt()) {
            $git->remoteSetUrl('origin', $repository->getAnonymousUri());
            $this->getEmitter()
                ->emit(GitRepositoryEvent::prepare(
                    'git_guardian.config_skip_repository',
                    $repository,
                    'config_skip',
                    $git
                ));
            return;
        }

        $git->remoteSetUrl('origin', $repository->getUri());
        $this->getEmitter()
            ->emit(GitRepositoryEvent::prepare('git_guardian.pre_fetch_repository', $repository, 'fetch', $git));
        $git->fetch(['--all']);
        $this->getEmitter()
            ->emit(GitRepositoryEvent::prepare('git_guardian.post_fetch_repository', $repository, 'fetch', $git));
        $git->remoteSetUrl('origin', $repository->getAnonymousUri());

        $log[$repository->getName()] = [
            'name' => $repository->getName(),
            'description' => $repository->getDescription(),
            'uri' => $repository->getAnonymousUri(),
            'path' => $path,
            'commits' => $git->getLogs(10),
            'branches' => $git->getBranches(),
            'fetched_at' => date(DATE_ISO8601),
            'updated_at' => $repository->getUpdatedAt() ? $repository->getUpdatedAt()->format(DATE_ISO8601) : null
        ];
        $this->getEmitter()
            ->emit(GitRepositoryEvent::prepare(
                'git_guardian.pre_config_log_repository',
                $repository,
                'config_log',
                $git,
                ['log' => $log]
            ));
        file_put_contents($configFile, json_encode($log, JSON_PRETTY_PRINT));
    }

    /**
     * @param RemoteInterface $remote
     * @param string $destination
     * @param array $options
     */
    public function cloneRemote(RemoteInterface $remote, $destination, array $options = null)
    {
        $options = array_merge($this->defaultOptions, $options ?: []);
        $this->getEmitter()->emit(GitRemoteEvent::prepare('git_guardian.pre_clone_remote', $remote, [
            'destination' => $destination,
            'options' => $options ?: []
        ]));

        $repositories = $remote->getRepositories();

        $fs = new Filesystem();
        if (!$fs->exists($destination)) {
            $fs->mkdir($destination, 0770);
        }

        foreach ($repositories as $repository) {
            $this->cloneRepository($repository, $destination, $options);
        }

        $this->getEmitter()->emit(GitRemoteEvent::prepare('git_guardian.post_clone_remote', $remote, [
            'destination' => $destination,
            'options' => $options ?: []
        ]));
    }

    /**
     * @param string $destination
     * @param array|null $options
     */
    public function cloneAll($destination, array $options = null)
    {
        $destination = rtrim($destination, DIRECTORY_SEPARATOR);

        foreach ($this->remotes as $remote) {
            $this->cloneRemote($remote, $destination.DIRECTORY_SEPARATOR.$remote->getName(), $options);
        }
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
     * @param EmitterInterface $emitter
     */
    public function setEmitter($emitter)
    {
        $this->emitter = $emitter;
    }

    /**
     * @param RemoteInterface $remote
     */
    public function addRemote(RemoteInterface $remote)
    {
        if (!in_array($remote, $this->remotes, true)) {
            $this->remotes[] = $remote;
        }
    }

    /**
     * @return RemoteInterface[]
     */
    public function getRemotes()
    {
        return $this->remotes;
    }

    /**
     * @return array
     */
    public function getDefaultOptions()
    {
        return $this->defaultOptions;
    }

    /**
     * @param array $defaultOptions
     */
    public function setDefaultOptions($defaultOptions)
    {
        $this->defaultOptions = $defaultOptions;
    }
}
