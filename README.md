# Deletion Module (3snet Senior PHP Backend Developer)

Production-ready модуль для каскадного удаления, отсоединения и мягкого удаления (soft-delete) Doctrine-сущностей, управляемый через PHP-атрибуты. Реализован с упором на безопасность, отказоустойчивость и строгое соблюдение принципов DDD и Dependency Inversion.

## Стек

- PHP 8.3+ (readonly classes, enums, match, promoted properties)
- Symfony 7.4 (LTS) (Security, RateLimiter, MapRequestPayload)
- Doctrine ORM 3 / DBAL 4
- PHPUnit 12
- Docker (PHP-FPM 8.3 + Xdebug, Nginx, MySQL 8, Redis, MailHog)

## Архитектура (DDD + Clean Architecture)

Проект разделен на 4 независимых слоя (Ports & Adapters). Слои Domain и Application полностью изолированы от инфраструктуры и фреймворка.

**Структура проекта:**

- **Domain:** Бизнес-логика (Pure PHP, без зависимости от Symfony/Doctrine).
    - `Model`: Агрегаты (DeletionPlan, RelationMap).
    - `ValueObject`: EntityClass, RelationType, DeleteAction.
    - `Service`: DeletionPlanner (DFS + топосортировка), RelationActionResolverInterface.
    - `Policy`: RelationPolicy (правила блокировки).
    - `Repository`: Порты (Интерфейсы) для Infrastructure.
- **Application:** Сценарии использования.
    - `Command`: DeleteEntityCommand.
    - `Port`: TransactionManagerInterface (Абстракция транзакций).
    - `Service`: DeleteEntityUseCase (Оркестрация, без привязки к БД).
    - `Result`: DeletionResult.
- **Infrastructure:** Адаптеры (ACL).
    - `Attribute`: RelationActionResolver (чтение PHP-атрибутов).
    - `Doctrine`: Репозитории (DBAL bulk), ClassMetadata, DoctrineTransactionManager.
- **UI:** HTTP-слой.
    - `Http/Controller`: Тонкие контроллеры.
    - `Http/EventListener`: Обработка исключений (RFC 7807) и Rate Limit.
    - `Http/Request`: DTO с валидацией через MapRequestPayload.

## Ключевые архитектурные решения (ADR)

### Строгий Dependency Inversion

Application слой ничего не знает о Doctrine. Управление транзакциями абстрагировано через `TransactionManagerInterface`. Блокировки (`SELECT ... FOR UPDATE`) делегированы в репозиторий, что позволяет использовать любую ORM/DBAL без переписывания UseCase.

### Security & Rate Limiting

Эндпоинт защищен HTTP Basic Auth с использованием `InMemoryUser`. Настроены `password_hashers` с разными алгоритмами для dev (plaintext) и prod (auto/bcrypt). Rate Limiter (sliding_window, 5 запросов/мин) вынесен в `DeletionRateLimitListener` на событие `kernel.controller`. Это гарантирует срабатывание защиты **до** валидации DTO и предотвращает флуд невалидными запросами.

### RFC 7807 (Problem Details)

Все ошибки API (401, 409, 422, 429, 500) возвращаются в едином машиночитаемом формате `application/problem+json` через глобальный `DeletionExceptionListener`. Для ответа 429 пробрасывается заголовок `Retry-After`.

### Bulk-операции через DBAL

Прямые `DELETE` и `UPDATE` запросы вместо загрузки тысяч сущностей в UnitOfWork. Это защищает от OOM и ускоряет удаление в 10–100 раз. После операции вызывается `EntityManager::clear()`.

### Pessimistic Lock

Перед анализом графа корневые сущности блокируются (`PESSIMISTIC_WRITE`), чтобы исключить phantom reads во время построения плана удаления.

### Защита от циклов

`DeletionPlanner` использует обход в глубину (DFS) с детектом циклических зависимостей и выбросом `CyclicDependencyException`.

## Установка и запуск

```bash
make up
make install
make migrate
make test
```

## API

По умолчанию в dev-окружении доступен пользователь `api` с паролем `dev_only_change_me` (задается через переменную `API_USER_PASSWORD` в `.env`).

### Успешное удаление (HTTP 200)

Запрос:

```bash
curl -i -X POST http://localhost:8080/api/v1/deletion \
  -u api:dev_only_change_me \
  -H "Content-Type: application/json" \
  -d '{"entityClass": "App\\Entity\\Product", "ids": [1, 2]}'
```

Ответ:

```json
{
  "status": "success",
  "message": "Операция удаления выполнена успешно.",
  "data": { 
    "stats": { "Product": 2 }, 
    "total": 2 
  }
}
```

### Ошибка авторизации (HTTP 401)

Если не передан заголовок `Authorization` или неверные данные:

```json
{
  "type": "about:blank",
  "title": "Unauthorized",
  "status": 401,
  "detail": "Full authentication is required to access this resource."
}
```

### Ошибка валидации (HTTP 422)

Если передан неверный класс (например, `stdClass`) или пустой массив `ids`:

```json
{
  "type": "about:blank",
  "title": "Validation Failed",
  "status": 422,
  "detail": "Класс \"stdClass\" не является Doctrine-сущностью."
}
```

### Превышение лимита запросов (HTTP 429)

При 6-м запросе в течение минуты возвращается заголовок `Retry-After`:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 45
Content-Type: application/problem+json
```

```json
{
  "type": "about:blank",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "Превышен лимит запросов на удаление (максимум 5 в минуту)."
}
```

### Блокировка удаления (HTTP 409)

Если сущность защищена атрибутом `BlockingDeletion`, вызов без `force: true` вернет 409 Conflict:

```json
{
  "type": "about:blank",
  "title": "Deletion Conflict",
  "status": 409,
  "detail": "Удаление заблокировано связью ..."
}
```

## Тестирование

Проект покрыт unit-тестами (Domain и Application слои тестируются без БД за счет моков портов).

В планах (Backlog): написание интеграционных тестов (`tests/Functional/`) для проверки полного HTTP-цикла, включая Auth, Rate Limit и RFC 7807 ответы.

```bash
make test
```
