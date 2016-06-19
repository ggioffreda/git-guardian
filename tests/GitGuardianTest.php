<?php

namespace Gioffreda\Component\GitGuardian\Tests;

use Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote;
use Gioffreda\Component\GitGuardian\Adapter\BitBucketRepository;
use Gioffreda\Component\GitGuardian\Event\GitRepositoryEvent;
use Gioffreda\Component\GitGuardian\GitGuardian;
use League\Event\Emitter;
use League\Event\EmitterInterface;

class GitGuardianTest extends \PHPUnit_Framework_TestCase
{
    public function testEmitter()
    {
        $guardian = new GitGuardian();
        $this->assertTrue($guardian->getEmitter() instanceof EmitterInterface);
        $emitter = new Emitter();
        $this->assertTrue($emitter !== $guardian->getEmitter());
        $guardian->setEmitter($emitter);
        $this->assertTrue($emitter === $guardian->getEmitter());
    }

    public function testDefaultOptions()
    {
        $guardian = new GitGuardian();
        $this->assertArrayHasKey('clone_config', $options = $guardian->getDefaultOptions());
        $this->assertEquals('.git-guardian.clone.config.json', $options['clone_config']);
    }

    public function testRemotes()
    {
        $guardian = new GitGuardian();
        $this->assertEmpty($guardian->getRemotes());

        $remote = $this->getMockBuilder('Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote')
            ->disableOriginalConstructor()
            ->getMock();

        $guardian->addRemote($remote);
        $this->assertNotEmpty($guardian->getRemotes());
        $this->assertCount(1, $guardian->getRemotes());
    }
    
    public function testCloneRepository()
    {
        $guardian = new GitGuardian();
        $destination = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('test-ggioffreda-git-guardian', true);
        mkdir($destination);

        $events = [];

        $guardian->getEmitter()->addListener('git_guardian.pre_clone_repository', function ($event) use (&$events) {
            $this->assertNotContains('git_guardian.pre_clone_repository', $events);
            $events[] = 'git_guardian.pre_clone_repository';
        });
        $guardian->getEmitter()->addListener('git_guardian.post_clone_repository', function ($event) use (&$events) {
            $this->assertNotContains('git_guardian.post_clone_repository', $events);
            $events[] = 'git_guardian.post_clone_repository';
        });
        $guardian->getEmitter()->addListener('git_guardian.pre_config_log_repository', function ($event) use (&$events, $destination) {
            /** @var GitRepositoryEvent $event */
            $this->assertNotContains('git_guardian.pre_config_log_repository', $events);
            $events[] = 'git_guardian.pre_config_log_repository';
            $this->assertStringStartsWith($destination, $event->getGit()->getPath());
        });

        $repository = $this->getMockBuilder('Gioffreda\Component\GitGuardian\Adapter\BitBucketRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repository->expects($this->any())->method('getName')->willReturn('test/test');
        $repository->expects($this->any())->method('getUpdatedAt')->will($this->returnValue(new \DateTime('now')));
        $repository->expects($this->any())->method('getAnonymousUri')->willReturn('https://github.com/ggioffreda/git-guardian');
        $repository->expects($this->any())->method('getUri')->willReturn('https://github.com/ggioffreda/git-guardian');
        $repository->expects($this->any())->method('getDescription')->willReturn('Test description');

        $guardian->cloneRepository($repository, $destination);

        $this->assertContains('git_guardian.pre_clone_repository', $events);
        $this->assertContains('git_guardian.post_clone_repository', $events);
        $this->assertContains('git_guardian.pre_config_log_repository', $events);
    }
}
