# odnavi/core

Базовый пакет микрофреймворка: контракты (`Db`, `Cache`, `Profiler`, `Entity`,
`EntityCollection`, `Repository`) и реестры-точки расширения (`DbRegistry`,
`CacheRegistry`, `RepositoryRegistry`), которые связывают верхние пакеты
(`odnavi/orm`, `odnavi/routing`) с конкретными драйверами БД/кэша, не привязывая
их жёстко ни к одному из них. Плюс — сквозные утилиты: рефлексия с кэшем
(`ReflectionFactory`, `AttributeReader`), профилирование (`Profiler`), хелперы
(`DateUtil`, `StringUtil`, `FilterUtil`).

## Содержание

- [Установка](#установка)
- [Слой БД: DbRegistry / DbFactory](#слой-бд-dbregistry--dbfactory)
- [Слой кэша: CacheRegistry / CacheFactory](#слой-кэша-cacheregistry--cachefactory)
- [Контракты сущностей и репозиториев](#контракты-сущностей-и-репозиториев)
- [Рефлексия и атрибуты](#рефлексия-и-атрибуты)
- [Профилирование](#профилирование)
- [Утилиты](#утилиты)

## Установка

```bash
composer require odnavi/core
```

Требуется PHP 8.2+ и `ext-reflection`. Остальные зависимости — опциональные,
по месту использования:

- `doctrine/dbal` — для адаптера `DbalDb` (если соединение с БД идёт через DBAL);
- `predis/predis` или `ext-redis` — для адаптеров `PredisCache`/`PhpRedisCache`.

Без них пакет работает на «сыром» `PDO` и `NullCache`/`ArrayCache` — без внешних
зависимостей.

## Слой БД: DbRegistry / DbFactory

`DbRegistry` хранит активное соединение с БД, которым пользуются репозитории и
`EntityManager` пакета `odnavi/orm`. Регистрируется один раз при инициализации
приложения — можно передать как «сырой» драйвер (`PDO`, `Doctrine\DBAL\Connection`,
`wpdb`), так и готовую реализацию `Contract\Db`. Драйвер оборачивается в подходящий
адаптер автоматически через `DbFactory::from()`.

```php
use Odnavi\Core\DbRegistry;

// configs/database.php
$config = [
    'dbname'   => getenv('PG_DB'),
    'user'     => getenv('PG_USER'),
    'password' => getenv('PG_PASSWORD'),
    'host'     => getenv('PG_HOST'),
    'port'     => 5432,
    'sslmode'  => 'disable',
    'driverOptions' => [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
    $config['host'], $config['port'], $config['dbname'], $config['sslmode']
);

$pdo = new PDO($dsn, $config['user'], $config['password'], $config['driverOptions']);

DbRegistry::set($pdo);
```

Дальше — везде, где нужен доступ к БД:

```php
use Odnavi\Core\DbRegistry;

$db = DbRegistry::get();

$user = $db->prepare('SELECT * FROM users WHERE id = ?', [$id])->fetch();

$db->transactional(function () use ($db, $data) {
    $db->insert('users', $data);
});
```

Контракт `Contract\Db` — позиционные плейсхолдеры `?` (как генерирует
`QueryBuilder` из `odnavi/orm`):

| Метод | Назначение |
|-------|-----------|
| `prepare(sql, args)` | готовит запрос, возвращает `$this` для цепочки |
| `fetch()` / `fetchAll()` | одна строка / все строки (ассоц. массив) |
| `fetchOne()` / `fetchFirstColumn()` | скаляр / первый столбец всех строк |
| `execute()` | не-SELECT запрос, число затронутых строк |
| `insert()` / `update()` / `delete()` | шорткаты для таблицы по массиву данных/критериев |
| `lastInsertId()` / `lastError()` | сервисная информация |
| `beginTransaction()` / `commit()` / `rollBack()` / `transactional(callback)` | транзакции |

Готовые адаптеры под драйверы:

| Адаптер | Драйвер | Особенность |
|---------|---------|-------------|
| `Adapter\Db\PdoDb` | `PDO` | 1-based `bindValue`, типизация параметров по PHP-типу |
| `Adapter\Db\DbalDb` | `Doctrine\DBAL\Connection` | требует `doctrine/dbal` |
| `Adapter\Db\WpdbDb` | `wpdb` (WordPress) | конвертирует `?` в `%s`/`%d`/`%f` под `$wpdb->prepare()` |

## Слой кэша: CacheRegistry / CacheFactory

`CacheRegistry` — тот же паттерн для кэша. Пока клиент не зарегистрирован,
используется `NullCache` (no-op) — кэш остаётся опциональной зависимостью.

```php
use Odnavi\Core\CacheRegistry;
use Predis\Client;

// configs/redis.php
$config = [
    'scheme'     => 'tcp',
    'host'       => getenv('REDIS_HOST') ?: 'redis',
    'port'       => (int) (getenv('REDIS_PORT') ?: 6379),
    'persistent' => filter_var(getenv('REDIS_PERSISTENT'), FILTER_VALIDATE_BOOL),
];

$client = new Client($config);

CacheRegistry::set($client);
```

```php
use Odnavi\Core\CacheRegistry;

$cache = CacheRegistry::get();

$cache->set('route_map', $routes, 3600);
$routes = $cache->get('route_map'); // null, если ключа нет или истёк TTL
$cache->delete('route_map');
```

Контракт `Contract\Cache` — три метода: `get(key)`, `set(key, value, ?ttl)`,
`delete(key)`. Значения сериализуются адаптером (`PredisCache`/`PhpRedisCache`
используют `serialize`/`unserialize`).

Готовые адаптеры:

| Адаптер | Клиент | Заметки |
|---------|--------|---------|
| `Adapter\Cache\PhpRedisCache` | `\Redis` (ext-redis) | — |
| `Adapter\Cache\PredisCache` | `Predis\ClientInterface` | требует `predis/predis`; сверх контракта даёт прямые Redis-операции (`push`/`pop`, `zAdd`/`zScore`/`zRangeByScore`/..., `setNx`) |
| `Adapter\Cache\ArrayCache` | — | в памяти процесса, без внешнего хранилища — для тестов и локальной разработки |
| `Adapter\Cache\NullCache` | — | no-op, дефолт до регистрации реального клиента |

## Контракты сущностей и репозиториев

`Contract\Entity`, `Contract\EntityCollection`, `Contract\Repository` — контракты,
от которых зависит `odnavi/routing` в generic CRUD-операциях (`#[Get]`,
`#[Post]`, `#[Patch]`, `#[Delete]`), не будучи привязанным к конкретной ORM.
Реализует их слой `odnavi/orm` (`AbstractEntity`, `Collection`, `EntityRepository`).

`RepositoryRegistry` резолвит репозиторий по классу сущности — в отличие от
`DbRegistry`/`CacheRegistry` регистрируется не готовый инстанс, а фабричная
функция (репозиторий нужен per-класс сущности):

```php
use Odnavi\Core\RepositoryRegistry;
use ORM\Repository\RepositoryFactory;

RepositoryRegistry::setResolver(
    fn(string $entityClass) => RepositoryFactory::get($entityClass)
);
```

```php
use Odnavi\Core\RepositoryRegistry;

$repo = RepositoryRegistry::get(UserEntity::class);
$user = $repo->find($id);
```

## Рефлексия и атрибуты

`ReflectionFactory` — фабрика `ReflectionClass`/`ReflectionProperty` с кэшем на
процесс, чтобы не пересоздавать объекты рефлексии на каждый вызов.
`AttributeReader` — обёртка над нативными `#[Attribute]` поверх неё, тоже с
кэшем результатов.

```php
use Odnavi\Core\Service\{ReflectionFactory, AttributeReader};

$class = ReflectionFactory::getClass(UserEntity::class);

// атрибуты уровня класса, отфильтрованные по FQCN
$tables = AttributeReader::getForClass($class, Table::class);

// свойства с атрибутами, родители — первыми
foreach (AttributeReader::getForProperties($class) as ['property' => $property, 'attrs' => $attrs]) {
    foreach ($attrs as $attr) {
        if ($attr instanceof Column) {
            // построение метаданных колонки по $property и $attr
        }
    }
}
```

Так `odnavi/orm` читает `#[Table]`/`#[Column]`/`#[JoinColumn]` при построении
метаданных сущности, не гоняя рефлексию заново на каждый запрос.

## Профилирование

Два независимых инструмента.

**`Profiler`** — синглтон с вложенными таймерами (стек: `startTimer`/`stopTimer`
работают как открывающий/закрывающий тег, вложенность — по порядку вызовов):

```php
use Odnavi\Core\Profiler;

Profiler::startTimer('handle request', ['route' => $route]);
Profiler::startTimer('db query', ['sql' => $sql]);
// ...
Profiler::stopTimer(); // закрывает 'db query'
Profiler::stopTimer(); // закрывает 'handle request'

$timers = Profiler::gerProfiling(); // дерево: имя => время (сек) или ['time' => ..., 'params' => ..., 'timers' => [...]]
```

**`Contract\Profiler`** — минимальный интерфейс (`start(label, context)` /
`stop()`) для внешних реализаций профилирования, которые внедряет
потребитель (например, `odnavi/orm` подключает свой профайлер запросов через
`Support\Profiling::set()`, по умолчанию — no-op). Не путать с классом
`Profiler` выше — это разные, независимо используемые механизмы.

## Утилиты

**`StringUtil`** — конвертация регистра имён, используется при сопоставлении
ключей тела запроса и свойств сущности (`type_id` ↔ `typeId`):

```php
use Odnavi\Core\Util\StringUtil;

StringUtil::toCamelCase('type_id');        // 'typeId'
StringUtil::toCamelCase('type_id', true);  // 'TypeId'
StringUtil::toSnakeCase('typeId');         // 'type_id'
StringUtil::formatCase('typeId', StringUtil::FORMAT_SNAKE_CASE); // 'type_id'
```

**`DateUtil`** — иммутабельные даты в UTC с кэшем `DateTimeZone` на процесс.
Параметр по умолчанию завязан на глобальную константу `CURRENT_TIME` — единую
точку отсчёта «сейчас» на один запрос/цикл воркера, которую приложение должно
определить самостоятельно при старте (`define('CURRENT_TIME', time());`):

```php
use Odnavi\Core\Util\DateUtil;

$now  = DateUtil::getDate();            // DateTimeImmutable в UTC на момент CURRENT_TIME
$date = DateUtil::getDate($timestamp);  // на произвольный timestamp
```

**`FilterUtil`** — хелпер `applyDateFilter()` для построения `BETWEEN`-условий
по диапазону дат поверх `QueryBuilder` пакета `odnavi/orm`; используется вместе
с ним, а не как самостоятельный инструмент.

## Лицензия

MIT, см. [LICENSE](LICENSE).
