# 07 — Query log

Two independent facilities, often confused:

| Tool | What it captures | Where it goes |
| --- | --- | --- |
| **`enableQueryLog()`** | Every executed SQL, with the bound args and the elapsed time. | An in-memory array — read it back with `getQueryLogs()`. |
| **The `log` connection channel** | Connection-level errors and notable events (driver-level). | A file path, callable, PSR-3 logger, or any object with a `critical()` method. |

Use the first for profiling and test assertions; use the second for the audit trail.

## In-memory query log

```php
use InitPHP\Database\DB;

DB::enableQueryLog();

DB::table('users')->where('name', 'Ada')->read();

print_r(DB::getQueryLogs());
/*
[
    [
        'query' => 'SELECT * FROM `users` WHERE `name` = :name',
        'args'  => [':name' => 'Ada'],
        'timer' => 0.000385,
    ],
]
*/

DB::disableQueryLog(); // stop recording (the existing buffer stays untouched)
```

`enableQueryLog()` flips an in-memory flag on the connection — every subsequent prepare/execute appends to the buffer. Useful in tests:

```php
$db->enableQueryLog();
$model->read();
self::assertStringContainsString('WHERE deleted_at IS NULL', $db->getQueryLogs()[0]['query']);
```

Each entry has:

| Key | Type | Notes |
| --- | --- | --- |
| `query` | `string` | The prepared SQL exactly as sent to PDO. |
| `args`  | `array<string, mixed>` | The named-parameter map that was bound. |
| `timer` | `float` | Seconds elapsed inside `execute()`. |

You can also enable it at bootstrap with `'queryLogs' => true` in the connection array, in which case the buffer fills from the very first query.

## The `log` connection channel

```php
DB::createImmutable([
    'dsn'      => 'mysql:host=localhost;dbname=test;charset=utf8mb4',
    'username' => 'root',
    'password' => '',

    'log'      => __DIR__ . '/logs/db-{year}-{month}-{day}.log',
]);
```

The `log` credential accepts four shapes:

1. **PSR-3 logger** — anything implementing `Psr\Log\LoggerInterface`. The log writer calls `$logger->critical($message, $context)`.

   ```php
   'log' => new Monolog\Logger('db'),
   ```

2. **Callable** — invoked with the formatted message.

   ```php
   'log' => function (string $message): void {
       error_log('[DB] ' . $message);
   },
   ```

3. **Object with a `critical()` method** — duck-typed; kept for callers that don't depend on `psr/log`.

   ```php
   class Notifier {
       public function critical(string $msg): void { /* … */ }
   }
   'log' => new Notifier(),
   ```

4. **String** — treated as a file path, written with `file_put_contents(..., FILE_APPEND)`.

   ```php
   'log' => __DIR__ . '/db.log',
   ```

### Path placeholders

The string form expands these tokens before opening the file:

| Token | Replaced with |
| --- | --- |
| `{date}` | `Y-m-d` |
| `{datetime}` | `Y-m-d H:i:s` |
| `{timestamp}` | Unix timestamp |
| `{year}` / `{month}` / `{day}` | individual date parts |
| `{hour}` / `{minute}` / `{second}` | individual time parts |

So `db-{year}-{month}-{day}.log` produces one file per day automatically.

### Disable the channel

Pass `null`, `false`, or an empty string for `'log'` (or simply omit it). The writer becomes a no-op.

## Debug mode vs the log channel

`'debug' => true` is **not** about the log channel — it includes the offending SQL inside any thrown `SQLExecuteException` so the message in your error reporter actually tells you what failed. Production environments should keep it off; the rendered SQL can leak data into stack traces that ship to third-party services.
