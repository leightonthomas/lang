test:
    docker-compose run php vendor/bin/phpunit
test-coverage:
    docker-compose run php vendor/bin/phpunit --coverage-html coverage
build FILE:
    docker-compose run php src/console.php build {{FILE}}
