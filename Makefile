.PHONY: build install test shell down

build:
	docker compose build

install: build
	docker compose run --rm app composer install

test:
	docker compose run --rm app vendor/bin/phpunit

shell:
	docker compose run --rm app bash

down:
	docker compose down
