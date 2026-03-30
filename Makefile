PHP ?= $(shell which php 2>/dev/null || echo php)
CURL ?= $(shell which curl 2>/dev/null || echo curl)
WP_CLI_VERSION ?= 2.13.0
WP_CLI_DIR := .tools/wp-cli
WP_CLI_PHAR := $(WP_CLI_DIR)/wp-cli.phar
WP_CLI_PACKAGES_DIR := $(abspath $(WP_CLI_DIR)/packages)

$(WP_CLI_PHAR):
	@mkdir -p $(WP_CLI_DIR)
	@$(CURL) -fsSL -o $(WP_CLI_PHAR) https://github.com/wp-cli/wp-cli/releases/download/v$(WP_CLI_VERSION)/wp-cli-$(WP_CLI_VERSION).phar

install-wp-cli: $(WP_CLI_PHAR)
	@echo "WP-CLI installed at $(WP_CLI_PHAR)"

install-wp-dist-archive: install-wp-cli
	@mkdir -p $(WP_CLI_PACKAGES_DIR)
	@WP_CLI_PACKAGES_DIR=$(WP_CLI_PACKAGES_DIR) $(PHP) $(WP_CLI_PHAR) package install wp-cli/dist-archive-command:@stable

clean-dist:
	@rm -rf dist/ || echo "Nothing to remove"

dist: clean-dist
	@echo "Creating distribution package..."
	@mkdir -p dist/
	@cp -r private-captcha dist/private-captcha/
	@cd dist/private-captcha && rm -rf .git .gitignore Makefile composer.json composer.lock vendor/composer/installed.json
	@cd dist && zip -r private-captcha-wordpress.zip private-captcha/
	@echo "Distribution package created: dist/private-captcha-wordpress.zip"

dist-archive: install-wp-dist-archive
	@WP_CLI_PACKAGES_DIR=$(WP_CLI_PACKAGES_DIR) $(PHP) $(WP_CLI_PHAR) dist-archive ./private-captcha ./private-captcha.zip
	@echo "Distribution package created: private-captcha.zip"

run-docker:
	@docker compose -f docker/docker-compose.yml -f docker/docker-compose.privatecaptcha.yml up --build

run-docker-empty:
	@docker compose -f docker/docker-compose.yml up --build

clean-docker:
	@docker compose -f docker/docker-compose.yml down -v --remove-orphans
