clean-dist:
	@rm -rf dist/ || echo "Nothing to remove"

dist: clean-dist
	@echo "Creating distribution package..."
	@mkdir -p dist/
	@cp -r . dist/private-captcha/
	@cd dist/private-captcha && rm -rf .git .gitignore Makefile composer.json composer.lock vendor/composer/installed.json
	@cd dist && zip -r private-captcha-wordpress.zip private-captcha/
	@echo "Distribution package created: dist/private-captcha-wordpress.zip"

run-docker:
	@docker compose -f docker/docker-compose.yml -f docker/docker-compose.privatecaptcha.yml up --build

run-docker-empty:
	@docker compose -f docker/docker-compose.yml up --build

clean-docker:
	@docker compose -f docker/docker-compose.yml down -v --remove-orphans

# Run any target from private-captcha/Makefile with PHP and Composer in Docker.
TARGET ?= check
.PHONY: docker-plugin
docker-plugin:
	@docker compose -f docker/docker-compose.tools.yml run --build --rm --no-deps tools make $(TARGET)

# Separate project, volumes and port from the manual-testing stack.
E2E_COMPOSE = docker compose -p private-captcha-wordpress-e2e -f docker/docker-compose.e2e.yml
.PHONY: run-e2e test-e2e stop-e2e
run-e2e:
	@test -f docker/.env.e2e || (echo "Create docker/.env.e2e from docker/.env.e2e.example and set PC_API_KEY/PC_SITEKEY first"; exit 1)
	@test -f private-captcha/vendor/autoload.php || (echo "Install plugin dependencies with make docker-plugin TARGET=install-dev first"; exit 1)
	@$(E2E_COMPOSE) up -d --wait wordpress db
	@$(E2E_COMPOSE) run --rm cli

test-e2e:
	@npm run test:e2e

stop-e2e:
	@$(E2E_COMPOSE) down -v --remove-orphans
