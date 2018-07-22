# Prooph Event Store Client

PHP 7.2 Event Store Client Implementation.

[![Build Status](https://travis-ci.org/prooph/event-store-cliet.svg?branch=master)](https://travis-ci.org/prooph/event-store-client)
[![Coverage Status](https://coveralls.io/repos/prooph/event-store-client/badge.svg?branch=master&service=github)](https://coveralls.io/github/prooph/event-store-client?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/prooph/improoph)

## Overview

Prooph Event Store Client supports async non-blocking communication via TCP to [EventStore](https://eventstore.org/).
In the future, there will be also a server implementation written in pure PHP using Postgres (maybe also MySQL) as backend.

Asynchronous operations are done via [Amp](https://amphp.org/) and sync operation are also supported.

You don't need any extra extensions, however it's recommended to install [uv](https://pecl.php.net/package/uv) and [protobuf](https://pecl.php.net/package/protobuf). 

## Installation

You can install prooph/event-store-client via composer by adding `"prooph/event-store-client": "dev-master"` as requirement to your composer.json.

To install EventStore Server, check the manual at [https://eventstore.org/docs/getting-started/index.html](https://eventstore.org/docs/getting-started/index.html)

In the docker-folder you'll find three different docker-compose setups (single node, 3-node-cluster and 3-node-dns-cluster).

## Quick Start

For a short overview please see the annotated Quickstart in the `examples` folder.

## Documentation

Documentation is [in the doc tree](docs/), and can be compiled using [bookdown](http://bookdown.io).

```console
$ php ./vendor/bin/bookdown docs/bookdown.json
$ php -S 0.0.0.0:8080 -t docs/html/
```

Then browse to [http://localhost:8080/](http://localhost:8080/)

## Support

- Ask questions on Stack Overflow tagged with [#prooph](https://stackoverflow.com/questions/tagged/prooph).
- File issues at [https://github.com/prooph/event-store-client/issues](https://github.com/prooph/event-store-client/issues).
- Say hello in the [prooph gitter](https://gitter.im/prooph/improoph) chat.

## Contribute

Please feel free to fork and extend existing or add new plugins and send a pull request with your changes!
To establish a consistent code quality, please provide unit tests for all your changes and may adapt the documentation.

## License

Released under the [New BSD License](LICENSE).
