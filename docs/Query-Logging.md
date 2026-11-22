# Query Logging

Two **independent** facilities — often confused, but they answer different questions:

| Tool | Captures | Goes to | Question it answers |
|---|---|---|---|
| **`enableQueryLog()`** | Every executed SQL, its bound args, elapsed time | An in-memory array (`getQueryLogs()`) | "What SQL did my code actually run?" |
| **The `log` connection channel** | Connection-level events (driver errors, exceptions) | File path, callable, PSR-3 logger, or object with `critical()` | "What went wrong on the wire?" |

Use the first for profiling and test assertions. Use the second for the production audit trail.

## In-memory query log

```php
use InitPHP\Database\DB;

DB::enableQueryLog();

DB::table('users')->where('name', 'Ada')->read();
DB::table('posts')->where('author_id', 5)->read();

print_r(DB::getQueryLogs());
/*
[
    [
        'query' => 'SELECT * FROM `users` WHERE `name` = :name',
        'args'  => [':name' => 'Ada'],
        'timer' => 0.000385,
    ],
    [
        'query' => 'SELECT * FROM `posts` WHERE `author_id` = :author_id',
        'args'  => [':author_id' => 5],
        'timer' => 0.000412,
    ],
]
*/

DB::disableQueryLog(); // stop recording — the existing buffer stays untouched
```

`enableQueryLog()` flips a flag on the connection; every subsequent prepare / execute appends to the buffer.

### Each log entry

| Key | Type | Notes |
|---|---|---|
| `query` | `string` | The prepared SQL exactly as sent to PDO. |
| `args` | `array<string, mixed>` | The named-parameter map that was bound. |
| `timer` | `float` | Seconds elapsed inside `execute()`. |

### Enable from bootstrap

```php
DB::createImmutable([
    'dsn'       => '...',
    'queryLogs' => true, // start the buffer already on
]);
```

### Using it in tests

```php
public function testReadFiltersSoftDeletedRows(): void
{
    $db = SqliteHelper::makeDatabase();
    $db->enableQueryLog();

    (new Posts())->read();

    $log = $db->getQueryLogs();
    self::assertStringContainsString('IS NULL', $log[0]['query']);
}
```

### Using it for profiling

```php
DB::enableQueryLog();
// run request handler...
$logs = DB::getQueryLogs();

usort($logs, fn ($a, $b) => $b['timer'] <=> $a['timer']);
foreach (array_slice($logs, 0, 10) as $entry) {
    printf("%.4fs  %s\n", $entry['timer'], $entry['query']);
}
```

### Memory cost

The buffer grows without bound. In long-lived processes (workers, daemons), either:

- Leave logging off and turn it on temporarily around suspect code paths.
- Periodically `disableQueryLog()` and re-enable after handing the buffer off.
- Pump entries into the `log` channel and drop the in-memory array.

The buffer is **per `Database` instance**, so two connections have two independent buffers.

## The `log` connection channel

```php
DB::createImmutable([
    'dsn'      => '...',
    'username' => '...',
    'password' => '...',

    'log'      => __DIR__ . '/logs/db-{year}-{month}-{day}.log',
]);
```

The `log` credential accepts four shapes:

### 1. PSR-3 logger

```php
'log' => new Monolog\Logger('db'),
```

The connection calls `$logger->critical($message, $context)`. Anything implementing `Psr\Log\LoggerInterface` works — Monolog, Symfony, custom.

### 2. Callable

```php
'log' => function (string $message): void {
    error_log('[DB] ' . $message);
},
```

The closure / callable is invoked with the formatted message string.

### 3. Object with a `critical()` method

```php
final class Notifier
{
    public function critical(string $msg): void
    {
        $this->slack->send($msg);
    }
}

'log' => new Notifier(),
```

Duck-typed — kept around for callers that don't want to pull `psr/log` in.

### 4. String path

```php
'log' => __DIR__ . '/db.log',
```

The connection writes with `file_put_contents(..., FILE_APPEND)`. Date / time placeholders expand before opening the file:

| Token | Replaced with |
|---|---|
| `{date}` | `Y-m-d` |
| `{datetime}` | `Y-m-d H:i:s` |
| `{timestamp}` | Unix timestamp |
| `{year}` / `{month}` / `{day}` | individual date parts |
| `{hour}` / `{minute}` / `{second}` | individual time parts |

So `db-{year}-{month}-{day}.log` rotates daily for free.

### Disable the channel

Pass `null`, `false`, or an empty string for `'log'` (or omit it entirely). The writer becomes a no-op.

## Combining the two

```php
DB::createImmutable([
    'dsn'       => '...',
    'log'       => __DIR__ . '/db.log',  // production audit trail
    'queryLogs' => false,                // off by default
]);

// Enable the in-memory buffer for one specific request handler:
if ($request->getQueryParams()['debug'] ?? false) {
    DB::enableQueryLog();
}

// ... at the end of the handler:
if (DB::getQueryLogs() !== []) {
    $response = $response->withHeader('X-Query-Count', (string) count(DB::getQueryLogs()));
}
```

## Sample: counting queries per request

```php
final class QueryCounterMiddleware
{
    public function process($request, $handler)
    {
        DB::enableQueryLog();

        $response = $handler->handle($request);

        $logs = DB::getQueryLogs();
        $time = array_sum(array_column($logs, 'timer'));

        return $response
            ->withHeader('X-Query-Count', (string) count($logs))
            ->withHeader('X-Query-Time', sprintf('%.3fms', $time * 1000));
    }
}
```

## Next

- [Debugging](Debugging.md) — what `debug=true` adds.
- [Configuration](Configuration.md) — every connection field, including `log` and `queryLogs`.
