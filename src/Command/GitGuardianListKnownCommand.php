<?php

namespace Gioffreda\Component\GitGuardian\Command;

use Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote;
use Gioffreda\Component\GitGuardian\Adapter\GitHubRemote;
use Gioffreda\Component\GitGuardian\GitGuardian;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitGuardianListKnownCommand extends Command
{
    public function configure()
    {
        $this
            ->setName('git:guardian:list-known')
            ->setDescription('List all known repositories')
            ->addOption('adapter', null, InputOption::VALUE_REQUIRED, 'The adapter to use', 'BitBucket')
            ->addOption('destination', 'd', InputOption::VALUE_REQUIRED, 'The destination where to clone to', '.cloned')
            ->addOption('format', 'F', InputOption::VALUE_REQUIRED, 'The format of the output', 'table')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!in_array($input->getOption('adapter'), ['BitBucket', 'GitHub'])) {
            throw new InvalidArgumentException(sprintf(
                'The given adapter "%s" is not supported (yet)',
                $input->getOption('adapter')
            ));
        }

        $guardian = new GitGuardian();
        $adapter = 'GitHub' === $input->getOption('adapter') ?
            GitHubRemote::REMOTE_CANONICAL_NAME : BitBucketRemote::REMOTE_CANONICAL_NAME;
        $destination = $input->getOption('destination').DIRECTORY_SEPARATOR.$adapter;

        $configLog = $guardian->getConfigLog($destination);
        switch ($input->getOption('format')) {
            case 'table':
                $this->dumpTableFormat($output, $configLog, $adapter);
                break;
            case 'table-compact':
                $this->dumpTableFormat($output, $configLog, $adapter, 'compact');
                break;
            case 'table-borderless':
                $this->dumpTableFormat($output, $configLog, $adapter, 'borderless');
                break;
            case 'csv':
                $this->dumpXsvFormat($configLog, $adapter);
                break;
            case 'tsv':
                $this->dumpXsvFormat($configLog, $adapter, "\t");
                break;
            case 'json':
                $this->dumpJsonFormat($configLog, $adapter);
                break;
            case 'json-pretty':
                $this->dumpJsonFormat($configLog, $adapter, JSON_PRETTY_PRINT);
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'The format "%s" is not supported',
                    $input->getOption('format')
                ));
        }
    }

    // internal stuff

    protected function dumpTableFormat(OutputInterface $output, array $configLog, $adapter, $style = null)
    {
        $table = new Table($output);
        if (null !== $style) {
            $table->setStyle($style);
        }
        $table->setHeaders($this->getHeaders())->setRows($this->getInfoFromConfigLog($configLog, $adapter));
        $table->render();
    }

    protected function dumpXsvFormat(array $configLog, $adapter, $separator = ',')
    {
        fputcsv(STDOUT, $this->getHeaders(), "\t");
        foreach ($this->getInfoFromConfigLog($configLog, $adapter) as $row) {
            fputcsv(STDOUT, $row, $separator);
        }
    }

    protected function dumpJsonFormat(array $configLog, $adapter, $flags = 0)
    {
        $data = ['repositories' => []];
        $keys = array_map(function ($key) {
            return str_replace(' ', '_', strtolower($key));
        }, $this->getHeaders());

        foreach ($this->getInfoFromConfigLog($configLog, $adapter) as $values) {
            $data['repositories'][] = array_combine($keys, $values);
        }

        fputs(STDOUT, json_encode($data, $flags)."\n");
    }

    protected function getHeaders()
    {
        return ['Repository', 'Adapter', 'Private', 'Size', 'Commits', 'Updated At'];
    }

    protected function getInfoFromConfigLog(array $configLog, $adapter)
    {
        return array_map(function ($repository) use ($adapter) {
            return [
                $repository['name'],
                $adapter,
                isset($repository['private']) ? ($repository['private'] ? 'Y' : 'N') : null,
                isset($repository['size']) ? $repository['size'] : null,
                isset($repository['commits_count']) ? $repository['commits_count'] : null,
                $repository['updated_at']
            ];
        }, $configLog);
    }
}
