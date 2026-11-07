# Deletion Module (3snet Senior PHP Backend Developer)

Production-ready модуль для каскадного удаления, отсоединения и мягкого удаления (soft-delete) сущностей через PHP-атрибуты.

## Стек
- **PHP 8.3** (readonly classes, enums, match, promoted properties)
- **Symfony 7.4** (LTS)
- **Doctrine ORM 3 / DBAL 4**
- **PHPUnit 12**
- **Docker** (PHP-FPM 8.3 + Xdebug, Nginx, MySQL 8, Redis, MailHog)

## Архитектура (DDD + Clean Architecture)

Проект разделен на 4 независимых слоя. Это позволяет легко менять фреймворки и базы данных без переписывания бизнес-логики.

```text
src/Deletion/
├── Domain/          # Бизнес-логика (НЕ зависит от Symfony/Doctrine)
│   ├── Model/       # Агрегаты: DeletionPlan, RelationMap
│   ├── ValueObject/ # EntityClass, RelationType, DeleteAction
│   ├── Service/     # DeletionPlanner (DFS + топосортировка)
│   ├── Policy/      # RelationPolicy (правила блокировки)
│   └── Repository/  # Порты (Интерфейсы) для Infrastructure
├── Application/     # Сценарии использования
│   ├── Command/     # DeleteEntityCommand
│   ├── Service/     # DeleteEntityUseCase (Оркестрация + Транзакции)
│   └── Result/      # DeletionResult
├── Infrastructure/  # Адаптеры (ACL)
│   └── Doctrine/    # Репозитории (DBAL bulk-операции), ClassMetadata
└── UI/              # HTTP-слой
    ├── Http/Controller/
    └── Http/Request/ # DTO с Symfony Validator (#[MapRequestPayload])
```

### Ключевые решения (ADR)

1. **Bulk-операции через DBAL**: Вместо загрузки тысяч сущностей в UnitOfWork, мы делаем прямые `DELETE` и `UPDATE` запросы. Это защищает от OOM и ускоряет удаление в 10–100 раз. После операции вызывается `EntityManager::clear()`.
2. **Anti-Corruption Layer (ACL)**: Domain-слой ничего не знает про Doctrine. `ClassMetadata` транслируется в доменные `Value Objects` внутри Infrastructure.
3. **Защита от циклов**: `DeletionPlanner` использует обход в глубину (DFS) с детектом циклических зависимостей (`CyclicDependencyException`).
4. **Транзакционность**: Весь процесс удаления обернут в `wrapInTransaction`. В случае ошибки на любом шаге БД откатится к исходному состоянию.
5. **Pessimistic Lock**: Перед анализом графа корневые сущности блокируются через `SELECT ... FOR UPDATE`, чтобы исключить phantom reads.

## Установка и запуск

```bash
make up
make install
make migrate
make test
```

## API

**Успешное удаление (HTTP 200):**

```bash
curl -i -X POST http://localhost:8080/api/v1/deletion \
  -H "Content-Type: application/json" \
  -d '{"entityClass": "App\\Entity\\Product", "ids": [1, 2]}'
```

Ответ:

```json
{
  "status": "success",
  "message": "Операция удаления выполнена успешно.",
  "data": { "stats": { "Product": 2 }, "total": 2 }
}
```

**Ошибка валидации (HTTP 422):**

```bash
curl -X POST http://localhost:8080/api/v1/deletion \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Блокировка удаления (HTTP 409):**
Если сущность защищена атрибутом `#[BlockingDeletion]`, вызов без `force: true` вернет `409 Conflict`.

## Тестирование

18 unit-тестов, 34 ассерта. Domain-слой тестируется без БД (за счет DI и моков).

```bash
make test
```
