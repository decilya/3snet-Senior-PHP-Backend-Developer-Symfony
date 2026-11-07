# ============================================================================
# Makefile — обёртки над docker compose. Одна команда = одно действие.
# ============================================================================
DC       := docker compose
PHP      := $(DC) exec -T php
PHP_TTY  := $(DC) exec php
COMPOSER := $(PHP) composer
CONSOLE  := $(PHP) bin/console

.DEFAULT_GOAL := help

# ---------------------------------------------------------------------------
help: ## Показать список команд
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------
up: ## Поднять стек (сборка при необходимости)
	$(DC) up -d --build
	@echo "http://localhost:$${HTTP_PORT:-8080}"

down: ## Остановить стек
	$(DC) down

destroy: ## Остановить + удалить volume'ы (БД, Redis)
	$(DC) down -v

restart: down up ## Перезапустить

ps: ## Статус контейнеров
	$(DC) ps

logs: ## Логи всех сервисов
	$(DC) logs -f --tail=100

logs-php: ## Логи PHP
	$(DC) logs -f --tail=100 php

sh: ## Shell в контейнере PHP
	$(PHP_TTY) sh

# ---------------------------------------------------------------------------
install: ## composer install
	$(COMPOSER) install --no-interaction --prefer-dist

composer: ## composer $(args)
	$(COMPOSER) $(args)

# ---------------------------------------------------------------------------
migrate: ## Миграции (dev)
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

migrate-test: ## Миграции для тестовой БД
	$(PHP) sh -c 'APP_ENV=test bin/console doctrine:database:create --if-not-exists && APP_ENV=test bin/console doctrine:migrations:migrate --no-interaction'

fixtures: ## Загрузка фикстур (dev)
	$(CONSOLE) doctrine:fixtures:load --no-interaction

# ---------------------------------------------------------------------------
test: ## Все тесты (без покрытия)
	$(PHP) vendor/bin/phpunit --colors=always

test-unit: ## Только unit
	$(PHP) vendor/bin/phpunit --testsuite=unit --colors=always

test-integration: ## Только integration (БД в SQLite in-memory)
	$(PHP) vendor/bin/phpunit --testsuite=integration --colors=always

test-coverage: ## Покрытие (Xdebug mode=coverage)
	XDEBUG_MODE=coverage $(PHP) vendor/bin/phpunit --coverage-text --colors=always

# ---------------------------------------------------------------------------
stan: ## PHPStan
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## PHP-CS-Fixer (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## PHP-CS-Fixer (fix)
	$(PHP) vendor/bin/php-cs-fixer fix

qa: cs stan test ## Полная проверка перед коммитом

# ---------------------------------------------------------------------------
.PHONY: help up down destroy restart ps logs logs-php sh install composer \
        migrate migrate-test fixtures test test-unit test-integration \
        test-coverage stan cs cs-fix qa