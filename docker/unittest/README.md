# Unit Tests using Docker

Run the following commands from repo root:

## Start eventstore container

The eventstore container takes a few seconds to be ready. So start it upfront.

```bash
docker-compose -f docker/unittest/docker-compose.yml up -d
```

## Run the tests

```bash
docker-compose -f docker/unittest/docker-compose.yml run php /app/vendor/bin/phpunit -c phpunit/phpunit.xml
```

## Restart eventstore container after test run

Eventstore is started with an in-memory database. It's recommended to restart the container after each run to empty the db.

```bash
docker-compose -f docker/unittest/docker-compose.yml restart
```
