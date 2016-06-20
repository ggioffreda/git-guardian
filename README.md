Git Guardian Component
======================

This component helps querying remote services, like BitBucket and GitHub to get a list of repositories and helps cloning
 them locally for use or backup.

The requirements are specified in the composer.json file:

 * [PHP](http://www.php.net/) version >=5.5
 * [symfony/Console](http://symfony.com/doc/current/components/console.html) version >= 2.4
 * [symfony/Filesystem](http://symfony.com/doc/current/components/filesystem.html) version >= 2.4
 * [ggioffreda/git](https://github.com/ggioffreda/git) version >= 0.1.3
 * [guzzlehttp/guzzle](http://docs.guzzlephp.org/en/latest/) version >= 6.0
 * [league/event](https://github.com/thephpleague/event) version >= 2.0

[![Build Status](https://travis-ci.org/ggioffreda/git-guardian.svg?branch=master)](https://travis-ci.org/ggioffreda/git-guardian)

Installation
------------

This component is available for installation through [composer](https://getcomposer.org/). From command line run:

    $ composer require "ggioffreda/git-guardian" "~0.1"

Or add the following to your composer.json in the require section:

    "require": {
        "ggioffreda/git-guardian": "~0.1"
    }

For more information check the project page on [Packagist](https://packagist.org/packages/ggioffreda/git-guardian).

Usage - Command Line Interface
------------------------------

To clone all the repositories that belongs to a user, or users if you specify more than one, you can use the provided
 command `bin/git-guardian`. The command line interface is built using the
 [Symfony Console Component](http://symfony.com/doc/current/components/console/introduction.html) and can be easily
 integrated in your Symfony project.

To run the built-in command type:

    $ bin/git-guardian git:guardian:clone-all --client-id=SbAnN_example --client-secret=1JEfYU1nYhkoC_example ggioffreda

This will clone all repositories of the given user locally. You can specify the destination directory. The help looks
 like this at the moment:

```
Usage:
  git:guardian:clone-all [options] [--] [<owner>]...

Arguments:
  owner                              The owner or owners of the repositories

Options:
      --adapter=ADAPTER              The adapter to use [default: "BitBucket"]
      --client-id=CLIENT-ID          The client ID
      --client-secret=CLIENT-SECRET  The client secret
  -d, --destination=DESTINATION      The destination where to clone to [default: ".cloned"]
  -h, --help                         Display this help message
  -q, --quiet                        Do not output any message
  -V, --version                      Display this application version
      --ansi                         Force ANSI output
      --no-ansi                      Disable ANSI output
  -n, --no-interaction               Do not ask any interactive question
  -v|vv|vvv, --verbose               Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
 Fetches all the repositories for the given users
```

Usage - Remote Adapter
----------------------

You can use the `Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote` class to fetch the list of repositories owned
 by a specified user. If you provide the OAuth2 client ID and secret you can fetch the private ones as well. Here's an
 example on own to use the class:

```php
<?php

use Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote;

$bitBucketCredentials = [
    'client_id' => 'SbALNXBvnN_example',
    'client_secret' => '1JEfYU1n9mm6x4nYhkoC_example'
];

$remote = new BitBucketRemote();
$remote->setOptions($bitBucketCredentials);
$remote->setUser('acme');
```

You can listen to the discovery event of a repo to get the original raw definition fetched from the service by
 listening to the event `git_remote.repository_discovery`, for example to show a message in your console or to get
 more information about the repository than what you can get from the returned
 `Gioffreda\Component\GitGuardian\Adapter\BitBucketRepository` object.

```php
<?php

use Gioffreda\Component\GitGuardian\Adapter\BitBucketRemote;

$remote = new BitBucketRemote();
$remote->setOptions([
    'client_id' => 'SbALNXBvnN_example',
    'client_secret' => '1JEfYU1n9mm6x4nYhkoC_example'
]);
$remote->setUser('acme');
$remote->getEmitter()->addListener('git_remote.repository_discovery', function ($event) {
    // do something with it
});
```

Available events emitted by the remote adapter are:

- **git_remote.repository_discovery** is emitted every time a repository is found and added to the list;
- **git_remote.access_token** is emitted only if you provided OAuth2 credentials, every time a new access token is
   received. It could be useful if you intend to use the access token to perform custom operation on your repositories;
- **git_remote.client_request** is emitted every time an API call is sent to the remote service and exposes the endpoint
   being called.

Usage - Git Guardian
--------------------

You can use the `Gioffreda\Component\GitGuardian\GitGuardian` class to bulk clone your repositories into a destination.
 The following is an example on how to do so:

```php
<?php

use Gioffreda\Component\GitGuardian\GitGuardian;

$guardian = new GitGuardian();
$emitter = $guardian->getEmitter();

// initialise your remote adapters

foreach ($remotes as $remote) {
    $guardian->addRemote($remote);
}

$guardian->cloneAll('/var/lib/repositories');
```

The `GitGuardian` class emits a range of events that allows you to monitor the status:

- **git_guardian.pre_clone_remote** and **git_guardian.post_clone_remote** are emitted before and after the list of
   repositories for a given remote is fetched and before they are cloned, one by one. These are emitted only once per
   remote adapter.
- **git_guardian.pre_clone_repository** and **git_guardian.post_clone_repository** are emitted before and after a
   repository is cloned from a remote. If the repository has been cloned already the system will try and fetch all
   changes instead of cloning it, so these events might not be called in favour of the following events;
- **git_guardian.create_git** is emitted when the repository had been cloned already in the same destination and
   therefore had not beel cloned;
- **git_guardian.pre_fetch_repository** and **git_guardian.post_fetch_repository** are emitted when the local repository
   was already available at cloning time and the changes are fetched (`$ git fetch --all`). These events will not be
   emitted if there's nothing to fetch;
- **git_guardian.config_skip_repository** to increase performance and avoid pointless cloning or fetching the system
   uses a JSON configuration file to keep track of the updates on the repository and to save a timestamp of the last
   time a repository has been updated. If there are no changes to a repository this event is emitted;
- **git_guardian.pre_config_log_repository** is emitted right before the information about the current repository are
   saved to the configuration file.

Resources
---------

**TODO: write the tests**

You can run the unit tests with the following command (requires [phpunit](http://phpunit.de/)):

    $ cd path/to/Gioffreda/Component/Git/
    $ composer.phar install
    $ phpunit

License
-------

This software is distributed under the following licenses: [GNU GPL v2](LICENSE_GPLv2.md),
[GNU GPL v3](LICENSE_GPLv3.md) and [MIT License](LICENSE_MIT.md). You may choose the one that best suits your needs.