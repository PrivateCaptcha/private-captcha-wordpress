COMPOSER ?= $(shell which composer 2>/dev/null || echo composer)
PHP ?= $(shell which php 2>/dev/null || echo php)

install:
	$(COMPOSER) install --no-dev --optimize-autoloader

install-dev:
	$(COMPOSER) install

lint:
	$(COMPOSER) run-script lint

analyze:
	$(COMPOSER) run-script analyze

wpcs:
	$(COMPOSER) run-script wpcs

# WordPress Coding Standards fix
wpcs-fix:
	$(COMPOSER) run-script wpcs:fix

check: lint wpcs analyze

clean:
	rm -rf vendor/
	rm -f .phpunit.result.cache
	rm -f .php-cs-fixer.cache

validate: clean
	$(COMPOSER) validate --strict
	$(COMPOSER) audit

update:
	$(COMPOSER) update

outdated:
	$(COMPOSER) outdated --direct
