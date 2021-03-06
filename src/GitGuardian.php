<?php

namespace Gioffreda\Component\GitGuardian;

use Gioffreda\Component\Git\Exception\GitProcessException;
use Gioffreda\Component\Git\Git;
use Gioffreda\Component\GitGuardian\Adapter\RemoteInterface;
use Gioffreda\Component\GitGuardian\Adapter\RepositoryInterface;
use Gioffreda\Component\GitGuardian\Event\GitRemoteEvent;
use Gioffreda\Component\GitGuardian\Event\GitRepositoryEvent;
use League\Event\Emitter;
use League\Event\EmitterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
     * @param string $destination
     * @param array|null $options
     * @return array
     */
    public function getConfigLog($destination, array $options = null)
    {
        $options = array_merge($this->defaultOptions, $options ?: []);
        $configFile = DIRECTORY_SEPARATOR === $options['clone_config'][0] ?
            $options['clone_config'] : $destination.DIRECTORY_SEPARATOR.$options['clone_config'];
        $configLog = json_decode(@file_get_contents($configFile), true);

        return json_last_error() === JSON_ERROR_NONE ? $configLog : [];
    }

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

        $skip = false;
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
            $skip = true;
        }

        if (!$skip) {
            $git->remoteSetUrl('origin', $repository->getUri());
            $this->getEmitter()
                ->emit(GitRepositoryEvent::prepare('git_guardian.pre_fetch_repository', $repository, 'fetch', $git));
            $git->fetch(['--all']);
            $this->getEmitter()
                ->emit(GitRepositoryEvent::prepare('git_guardian.post_fetch_repository', $repository, 'fetch', $git));
            $git->remoteSetUrl('origin', $repository->getAnonymousUri());
        }

        $commitsCount = null;
        try {
            $commitsCount = (int) $git->run(['rev-list', '--all', '--count']);
        } catch (GitProcessException $e) {
            // do nothing about it
        }

        $finder = new Finder();
        $files = $finder->in($path)->count();

        $log[$repository->getName()] = [
            'name' => $repository->getName(),
            'description' => $repository->getDescription(),
            'uri' => $repository->getAnonymousUri(),
            'path' => $path,
            'commits' => $git->getLogs(10),
            'commits_count' => $commitsCount,
            'size' => $repository->getSize(),
            'private' => $repository->isPrivate(),
            'branches' => $git->getBranches(),
            'fetched_at' => date(DATE_ISO8601),
            'files' => $files,
            'programming_language' => $this->guessLanguages($path, $files),
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
            try {
                $this->cloneRepository($repository, $destination, $options);
            } catch (GitProcessException $e) {
                $this->getEmitter()->emit(GitRepositoryEvent::prepare(
                    'git_guardian.exception_repository',
                    $repository,
                    'error',
                    null,
                    ['destination' => $destination, 'options' => $options ?: [], 'exception' => $e ]
                ));
            }
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

    protected function guessLanguages($path, $count)
    {
        $languages = [
            'ASP' => [ 'asp' ],
            'ASP.Net' => [ 'aspx', 'axd', 'asx', 'asmx', 'ashx' ],
            'C' => [ 'c', 'h' ],
            'C++' => [ 'cpp' ],
            'CSS' => [ 'css' ],
            'Coldfusion' => [ 'cfm' ],
            'Erlang' => [ 'jaws' ],
            'Flash' => [ 'swf', 'fla' ],
            'HTML' => [ 'html', 'htm', 'xhtml', 'jhtml' ],
            'Java' => [ 'java', 'jsp', 'jar', 'jspx', 'wss', 'do', 'action' ],
            'JavaScript' => [ 'js' ],
            'PHP' => [ 'php', 'php3', 'php4', 'phtml' ],
            'Perl' => [ 'pl' ],
            'Python' => [ 'py' ],
            'Ruby' => [ 'rb', 'rhtml' ],
            'XML' => [ 'xml', 'dtd' ]
        ];

        $return = [];

        foreach ($languages as $language => $extensions) {
            $languageCount = 0;
            foreach ($extensions as $extension) {
                $finder = new Finder();
                $finder->in($path)->name("*.$extension");
                $languageCount += $finder->count();
            }
            if ($languageCount > 0) {
                $return[$language] = [
                    'files' => $languageCount,
                    'share' => 100 * $languageCount / $count
                ];
            }
        }

        return $return;
    }
}
