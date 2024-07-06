test:
    docker-compose run php vendor/bin/phpunit
test-coverage:
    docker-compose run php vendor/bin/phpunit --coverage-html coverage
build FILE:
    docker-compose run php src/console.php build {{FILE}} --verbose
run FILE:
    docker-compose run php src/console.php run {{FILE}} --verbose
disassemble FILE:
    docker-compose run php src/console.php disassemble {{FILE}} --verbose
build-disassemble FILE:
    docker-compose run php src/console.php build {{FILE}} --verbose
    docker-compose run php src/console.php disassemble build/program --verbose
build-run FILE:
    docker-compose run php src/console.php build {{FILE}} --verbose
    docker-compose run php src/console.php run build/program --verbose
