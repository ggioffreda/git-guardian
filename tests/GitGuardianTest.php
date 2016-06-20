<?php

namespace Gioffreda\Component\GitGuardian\Tests;

use Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote;
use Gioffreda\Component\GitGuardian\Adapter\BitBucketRepository;
use Gioffreda\Component\GitGuardian\Adapter\GitHubRemote;
use Gioffreda\Component\GitGuardian\Adapter\GitHubRepository;
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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function textCrossExceptionGHBB()
    {
        $remote = new GitHubRemote();
        $repository = new BitBucketRepository('test/test', 'a test', 'https://bitbucket.org/test/test.git');
        $repository->setRemote($remote);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCrossExceptionBBGH()
    {
        $remote = new BitBucketRemote();
        $repository = new GitHubRepository('test/test', 'a test', 'https://github.com/test/test.git');
        $repository->setRemote($remote);
    }

    public function testDefaultOptions()
    {
        $guardian = new GitGuardian();
        $this->assertArrayHasKey('clone_config', $options = $guardian->getDefaultOptions());
        $this->assertEquals('.git-guardian.clone.config.json', $options['clone_config']);
    }

    /**
     * @dataProvider remoteClassesProvider
     */
    public function testRemotes($class)
    {
        $guardian = new GitGuardian();
        $this->assertEmpty($guardian->getRemotes());

        $remote = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();

        $guardian->addRemote($remote);
        $this->assertNotEmpty($guardian->getRemotes());
        $this->assertCount(1, $guardian->getRemotes());
    }
    
    public function testBitBucketAuthentication()
    {
        $remote = new BitBucketRemote();
        $this->assertEquals([], $remote->getToken());
        $remote->setOptions(['client_id' => 'clientid', 'client_secret' => 'clientsecret']);

        $body = $this->getMockBuilder('GuzzleHttp\Psr7\Stream')->disableOriginalConstructor()->getMock();
        $body->expects($this->once())->method('getContents')->willReturn(json_encode($token = [
            'access_token' => 'testaccesstoken',
            'expires_in' => 3600
        ]));

        $tokenResponse = $this->getMockBuilder('GuzzleHttp\Psr7\Response')->disableOriginalConstructor()->getMock();
        $tokenResponse->expects($this->once())->method('getStatusCode')->willReturn(200);
        $tokenResponse->expects($this->once())->method('getBody')->willReturn($body);

        $client = $this->getMockBuilder('GuzzleHttp\Client')->getMock();
        $client->expects($this->once())->method('__call')->willReturn($tokenResponse);

        $remote->setClient($client);
        $token['expires_at'] =  new \DateTime('+1 hour');
        $this->assertEquals($token, $remote->getToken());
    }

    public function testGitHubAuthentication()
    {
        $remote = new GitHubRemote();
        $this->assertEquals([], $remote->getAuthenticationPart());
        $remote->setOptions(['personal_token' => 'testpersonaltoken', 'username' => 'testusername']);
        $this->assertEquals(['auth' => ['testusername', 'testpersonaltoken']], $remote->getAuthenticationPart());

        $repository = new GitHubRepository('text/test', 'just a test', $anon = 'https://github.com/test/test.git');
        $repository->setRemote($remote);
        $this->assertEquals($anon, $repository->getAnonymousUri());
        $this->assertEquals('https://testusername:testpersonaltoken@github.com/test/test.git', $repository->getUri());
    }

    /**
     * @dataProvider repositoryClassesProvider
     */
    public function testCloneRepository($class)
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

        $repository = $this->getMockBuilder($class)
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

    public function repositoryClassesProvider()
    {
        return [
            ['Gioffreda\Component\GitGuardian\Adapter\BitBucketRepository'],
            ['Gioffreda\Component\GitGuardian\Adapter\GitHubRepository']
        ];
    }

    public function remoteClassesProvider()
    {
        return [
            ['Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote'],
            ['Gioffreda\Component\GitGuardian\Adapter\GitHubRemote']
        ];
    }
}
