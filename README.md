# Prooph Event Store Client

PHP 7.4 Event Store Client Implementation.

[![Build Status](https://travis-ci.org/prooph/event-store-client.svg?branch=master)](https://travis-ci.org/prooph/event-store-client)
[![Coverage Status](https://coveralls.io/repos/github/prooph/event-store-client/badge.svg?branch=master)](https://coveralls.io/github/prooph/event-store-client?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/prooph/improoph)

## Overview

Prooph Event Store Client supports async non-blocking communication via TCP to [EventStore](https://eventstore.org/).

The `EventStoreConnection` maintains a full-duplex connection between the client and the Event Store server.

### Extensions

The `protobuf` extension from Google is recommended, however it is not required.

When this extension is missing, the client will fallback to use `google/protobuf` installable via composer.

The extension [allegro/php-protobuf](https://github.com/allegro/php-protobuf/) is not compatible.

Additional extensions are only needed if your app necessitates a high numbers of concurrent socket connections.

- [ev](https://pecl.php.net/package/ev)
- [event](https://pecl.php.net/package/event)
- [php-uv](https://github.com/bwoebi/php-uv) (experimental fork)

## Installation

### Client

You can install prooph/event-store-client via composer by adding `"prooph/event-store-client": "dev-master"` as requirement to your composer.json.

### Server

Using docker:

```bash
docker run --name eventstore-node -it -p 2113:2113 -p 1113:1113 eventstore/eventstore
```

Please refer to the documentation of [eventstore.org](https://eventstore.org).

See [server section](https://eventstore.org/docs/server/index.html).

In the docker-folder you'll find three different docker-compose setups (single node, 3-node-cluster and 3-node-dns-cluster).

## Quick Start

For a short overview please see the `examples` folder.

## Unit tests

### Plain PHP

Run the server with memory database

Note: This is the start-script of the Event Store database, not something provided by this library!

```console
./run-node.sh --run-projections=all --mem-db
```

You need to ignore the `ignore` group

```console
./vendor/bin/phpunit --exclude-group=ignore
```

Those are tests that only work against an empty database and can only be run manually.

Before next run, restart the server. This way you can always start with a clean server.

### Using Docker

See: https://github.com/prooph/event-store-client/tree/master/docker/unittest

## Documentation

Documentation is on the [prooph website](http://docs.getprooph.org/).

## Support

- Ask questions on Stack Overflow tagged with [#prooph](https://stackoverflow.com/questions/tagged/prooph).
- File issues at [https://github.com/prooph/event-store-client/issues](https://github.com/prooph/event-store-client/issues).
- Say hello in the [prooph gitter](https://gitter.im/prooph/improoph) chat.

## Contribute

Please feel free to fork and extend existing or add new plugins and send a pull request with your changes!
To establish a consistent code quality, please provide unit tests for all your changes and may adapt the documentation.

## License

Released under the [New BSD License](LICENSE).
