# Code Review модуля Deletion

## Оглавление

- [1. Общее впечатление](#1-общее-впечатление)
- [2. Что сделано хорошо](#2-что-сделано-хорошо)
- [3. Критические проблемы (блокирующие)](#3-критические-проблемы-блокирующие)
  - [3.1. Семантика `BLOCKING` противоречит документации и коду](#31-семантика-blocking-противоречит-документации-и-коду)
  - [3.2. `DeletionOrchestrator` игнорирует `canDelete`](#32-deletionorchestrator-игнорирует-candelete)
  - [3.3. Некорректная работа с Doctrine-ассоциациями](#33-некорректная-работа-с-doctrine-ассоциациями)
  - [3.4. `DETACH_RELATIONS` для прямых связей не реализован](#34-detach_relations-для-прямых-связей-не-реализован)
  - [3.5. Игнорирование Soft-Delete](#35-игнорирование-soft-delete)
  - [3.6. Циклические ссылки и отсутствие топологической сортировки](#36-циклические-ссылки-и-отсутствие-топологической-сортировки)
  - [3.7. Middleware: `supports()` игнорируется, ошибки проглатываются](#37-middleware-supports-игнорируется-ошибки-проглатываются)
  - [3.8. Производительность и архитектура](#38-производительность-и-архитектура)
  - [3.9. `DeletionOrchestrator` объявлен `final`](#39-deletionorchestrator-объявлен-final)
- [4. Итоговые рекомендации](#4-итоговые-рекомендации)
- [5. Реализация MetricsDeletionMiddleware](5. Реализация MetricsDeletionMiddleware)

---

## 1. Общее впечатление

Модуль решает задачу проверки зависимостей при удалении и каскадного удаления/отсоединения через атрибуты. Архитектурная идея (разделение анализа и исполнения, middleware) верна, но текущая реализация содержит **критические логические ошибки, проблемы с Doctrine, нарушения SOLID и производительности**, из-за которых код не готов к production.

## 2. Что сделано хорошо

- Декларативное описание связей через атрибуты `RelationTo`.
- Разделение ответственности: `DeletionService` (анализ) и `DeletionOrchestrator` (исполнение).
- Использование middleware для расширения функциональности.
- Применение DTO, enum'ов и `declare(strict_types=1)`.

---

## 3. Критические проблемы (блокирующие)

### 3.1. Семантика `BLOCKING` противоречит документации и коду

| Источник | Утверждение |
|----------|-------------|
| **README** | `BLOCKING` на дочерней сущности блокирует удаление **самой дочерней** сущности. |
| **Enum `RelationType`** | «блокирует удаление текущей сущности». |
| **Код** | Реализует блокировку **родителя** (через `findChildrenByAttributes`). |

**Последствия:** Непредсказуемое поведение, нарушение контракта с разработчиками.  
**Решение:** Ввести параметры `blocksParentDeletion` и `blocksCurrentDeletion` или разделить на два атрибута (`DependsOn`, `ReferencedBy`).

### 3.2. `DeletionOrchestrator` игнорирует `canDelete`

`execute()` не проверяет флаг `$relations->canDelete`. Блокирующие связи с `cascade=NONE` попадают в `childrenDelete` и удаляются, хотя `canDelete` вернул бы `false`. Это может привести к **потере данных**.

**Решение:** Добавить guard:

```php
if (!$relations->canDelete && !$force) {
    throw new DeletionBlockedException($relations);
}
```

### 3.3. Некорректная работа с Doctrine-ассоциациями

- `getScalarFkValue()` возвращает объект вместо ID для ManyToOne полей.  
  *Пример:* если поле `private OrderEntity $order;`, метод вернёт объект `OrderEntity`, а не его идентификатор.

- `getJoinTableParentIds()` использует ORM `QueryBuilder::from()` с именем таблицы – фатальная ошибка (DQL ожидает FQCN сущности).  
  *Пример:* `$qb->from('advert_tag_relation', 'jt')` вызовет `[Semantical Error]`.

- **Composite keys** не поддерживаются – код предполагает один идентификатор (`array_values($parentIdArr)[0]`).

- **Doctrine Proxy** – `ReflectionClass($object::class)` на прокси-классе (`Proxies\__CG__\...`) не найдёт атрибуты.

**Решение:** Использовать `ClassMetadata` для извлечения идентификаторов, применять DBAL для join-таблиц, обрабатывать proxy через `ClassUtils::getClass()`.

### 3.4. `DETACH_RELATIONS` для прямых связей не реализован

Для OneToOne/ManyToOne `DETACH` должен обнулять внешний ключ (`UPDATE child SET parent_id = NULL`), а не игнорироваться.

**Решение:** Добавить логику обновления для скалярных FK.

### 3.5. Игнорирование Soft-Delete

Модуль находит «мягко удалённые» записи и ложно блокирует удаление.

**Решение:** Добавлять фильтр `deleted_at IS NULL` в запросы (или использовать фильтры Doctrine).

### 3.6. Циклические ссылки и отсутствие топологической сортировки

- Рекурсивный обход без `visited` приводит к бесконечному циклу при циклических зависимостях (`A -> B`, `B -> A`).
- Порядок удаления не гарантирует соблюдение FK-ограничений (например, удаление родителя раньше ребёнка).

**Решение:** Ввести `visited` set и топологическую сортировку графа зависимостей.

### 3.7. Middleware: `supports()` игнорируется, ошибки проглатываются

- `notify()` не вызывает `$mw->supports()` – все middleware срабатывают для всех сущностей.
- Пустой `catch (Throwable) {}` скрывает ошибки метрик и аудита.

**Решение:** Добавить проверку `supports()` и логировать исключения через `LoggerInterface`.

### 3.8. Производительность и архитектура

- `getAllMetadata()` + Reflection при каждом первом вызове – нужен cache warmup.
- Загрузка всех child‑объектов в память только для получения ID – нужно использовать `COUNT` и выборку с лимитом.
- `DependentGroupDto` хранит все ID – возможен Out of Memory.
- N+1 в рекурсивном `find()` – нужен batch‑loading.
- Отсутствие chunking для DELETE/DETACH – риски lock escalation.
- `DeletionService` нарушает SRP (reflection, JSON, SQL, анализ).

**Решение:** Разделить на `RelationMetadataRegistry`, `RelationQueryGateway`, `DeletionAnalyzer`; внедрить кеширование, batch‑загрузку и чанкинг.

### 3.9. `DeletionOrchestrator` объявлен `final`

Это мешает декорированию через наследование (например, для перехвата ошибок).

**Решение:** Использовать композицию (реализация описана в отдельном документе) или выделить интерфейс и снять `final`.

---

## 4. Итоговые рекомендации

1. **Пересмотреть семантику `BLOCKING`** и привести документацию в соответствие с кодом (или наоборот).
2. **Добавить guard `canDelete`** перед выполнением удаления.
3. **Исправить работу с Doctrine** – использовать `ClassMetadata` и DBAL для join-таблиц, обрабатывать proxy.
4. **Реализовать `DETACH_RELATIONS` для прямых связей** (обнуление FK).
5. **Добавить поддержку Soft-Delete** через фильтры.
6. **Ввести защиту от циклов** и топологическую сортировку.
7. **Улучшить middleware** – добавить вызов `supports()` и логирование ошибок.
8. **Оптимизировать производительность** – кеш карты, batch-загрузка, чанкинг.
9. **Разделить `DeletionService`** на несколько классов (SRP).
10. **Снять `final` с `DeletionOrchestrator`** или использовать композицию для декорирования.

---

Данный Code Review выявил критические недостатки, требующие исправления перед использованием модуля в production. Рекомендуется внедрить описанные изменения для обеспечения надёжности, производительности и поддерживаемости кода.

# 5. Реализация MetricsDeletionMiddleware

## Оглавление

- [5.1. Обзор подхода](#51-обзор-подхода)
- [5.2. Структура добавляемых файлов](#52-структура-добавляемых-файлов)
- [5.3. Файлы для добавления](#53-файлы-для-добавления)
  - [5.3.1. `MetricsRecorderInterface.php`](#531-metricsrecorderinterfacephp)
  - [5.3.2. `LogMetricsRecorder.php`](#532-logmetricsrecorderphp)
  - [5.3.3. `MetricsDeletionMiddleware.php`](#533-metricsdeletionmiddlewarephp)
  - [5.3.4. `DeletionOrchestratorWrapper.php`](#534-deletionorchestratorwrapperphp)
- [5.4. Интеграция в Symfony (services.yaml)](#54-интеграция-в-symfony-servicesyaml)
- [5.5. Проверка работы](#55-проверка-работы)

---

## 5.1. Обзор подхода

Создан middleware, собирающий метрики по операции удаления:

- **Счётчики** – количество запусков, завершений, ошибок, удалённых детей, отсоединённых связей.
- **Гистограммы** – длительность удаления корня, размер batch'а детей.
- **Gauge (in‑progress)** – количество одновременно выполняющихся операций (для мониторинга зависших удалений).

Для предотвращения утечек памяти в long‑running процессах (Swoole, RoadRunner) используется `WeakMap` для хранения таймингов.  
Для гибкости система метрик абстрагирована через `MetricsRecorderInterface` – можно использовать логирование или Prometheus.  
Так как `DeletionOrchestrator` – `final`, мы применяем декоратор через **композицию** (обёртку), которая перехватывает исключения и уведомляет middleware через кастомный метод `onError`.

## 2.2. Структура добавляемых файлов

Исходная структура модуля:

eletion/
├── Attribute/
├── Dto/
├── Enum/
├── Middleware/
├── Service/
├── DeletionService.php
└── README.md


Добавляем:

- **Metrics/** – новая папка для интерфейса и реализаций рекордера.
- В **Middleware/** – `MetricsDeletionMiddleware.php`.
- В **Service/** – `DeletionOrchestratorWrapper.php` (обёртка).
- config/services.yaml – конфигурация для Symfony DI.


## 5.3. Добавленные файлы:
### 5.3.1. MetricsRecorderInterface.php
Путь: Deletion/Metrics/MetricsRecorderInterface.php

### 5.3.2. LogMetricsRecorder.php
Путь: Deletion/Metrics/LogMetricsRecorder.php

### 5.3.3. MetricsDeletionMiddleware.php
Путь: Deletion/Middleware/MetricsDeletionMiddleware.php

### 5.3.4. DeletionOrchestratorWrapper.php
Путь: Deletion/Service/DeletionOrchestratorWrapper.php

## 5.4. Интеграция в Symfony

Шаг 1. Создать папку config внутри модуля Deletion и внутри нее файл конфигурации Deletion/config/services.yaml

Шаг 2. Подключить конфигурацию в глобальный config/services.yaml

```
imports:
    - { resource: '../src/Shared/Deletion/config/services.yaml' }
```

Шаг 3. Обновить автозагрузку Composer	

Шаг 4. Очистить кеш 

```
php bin/console cache:clear
```