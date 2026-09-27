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
