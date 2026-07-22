# Mongo connection timeouts

`diCore\Database\Legacy\Mongo` keeps the **MongoDB driver's own defaults**:

| Option | Default | Covers |
|---|---|---|
| `serverSelectionTimeoutMS` | 30000 | finding a usable server |
| `connectTimeoutMS` | 10000 | establishing the TCP/TLS connection |
| `socketTimeoutMS` | 300000 | a single socket round-trip, i.e. **query execution** |

The library does not retune them, and does not even pass them: an unconfigured
timeout is left out of the client options entirely, so the driver applies whatever
its own current default is. The constants above are documented reference values
returned by the getters, not something pinned into the connection. Tightening
timeouts is a deployment-topology decision, and the safe value differs per
consumer — so it belongs to the project.

## Setting them per connection

Pass them in the connection settings array; they land in
`ConnectionData::getOtherOptions()`:

```php
Connection::open(
    [
        'host' => 'localhost',
        'database' => 'my_db',
        'socketTimeoutMS' => 30000,
    ],
    Engine::MONGO,
    'mongo_main'
);
```

A DSN cannot carry them (the query string is not parsed), so `open()` and
`openByDsn()` take them as a separate argument:

```php
Connection::openByDsn($dsn, 'mongo_main', ['socketTimeoutMS' => 30000]);
Connection::open($dsn, Engine::MONGO, 'mongo_main', ['socketTimeoutMS' => 30000]);
```

A value the DSN actually provided always wins — this array cannot override the
host or credentials. It can still *supply* one the DSN omitted (e.g. a database for
a path-less DSN), so it is not a security boundary: treat it as the same trust
level as the connection data itself.

**Only these three timeouts are read.** Other URI options (`authSource`,
`replicaSet`, `tls`, `readPreference`, …) are accepted into the settings array and
then silently ignored — add them to `Mongo::getClientOptions()` if you need them.
`MysqlConnection`/`PostgresqlConnection` ignore the array entirely.

**Changed:** credentials are now percent-encoded when the URI is built, and DSN
credentials percent-decoded when parsed. Two upgrade hazards, both narrow:

- a DSN whose password held a literal `%` sequence (`pa%41ss`) now resolves to the
  decoded value, per RFC;
- a settings array whose password was *pre-encoded* to work around the old
  unescaped-`@` bug (`p%40ss`) is now encoded again (`p%2540ss`) and will fail to
  authenticate — store the raw password instead.

## Choosing values

**`socketTimeoutMS` cannot share a value with the connect timeouts.** It covers
query execution, so it must stay well above the slowest legitimate query. Mongo
does **not** retry an operation it cuts short — too low a value turns a slow page
into an error.

**Use looser values on CLI.** Workers, crons, migrations and bulk imports run
legitimately long operations, have no FPM worker to protect, and rely on server
selection to ride out a Mongo restart. A value chosen to keep web requests snappy
is a regression there.

**A replica set needs a generous `serverSelectionTimeoutMS`.** Server selection is
exactly what waits out a primary election (normally seconds). A value tuned for a
single node turns a routine failover into an outage. The same applies to managed
clusters, where TLS setup alone can take seconds.

**None of these apply to a closed port.** A stopped container refuses the
connection immediately and the driver throws at once — measured ~0.00s. The
timeouts bite when the host is *unreachable* (network partition, firewall DROP,
dead node) or stops answering mid-operation. Measured on an unreachable host:
10.0s on the driver defaults vs 1.51s at `connectTimeoutMS = 1500`.
