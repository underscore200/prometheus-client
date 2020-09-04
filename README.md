## Installation
Run the following command in your project root.

```shell
$ composer require dbstudios/prometheus-client
```

## Getting Started
There are two components that you'll need to set up in order to start using this library. The first is the
`CollectorRegistry`, which acts as a repository for your collectors. There isn't any special configuration to worry
about, all you need is an instance you can can access anywhere in your application.

```php
<?php
    use DaybreakStudios\PrometheusClient\CollectorRegistry;
    
    $registry = new CollectorRegistry();
```

Next up is an adapter, which acts as an interface between the library code and your chose storage system. At the time
of writing, this library only ships with an adapter for APCu.

Instantiation will vary from adapter to adapter, so please check the [documentation for the adapter](#adapters) you're
using.

```php
<?php
    use DaybreakStudios\PrometheusClient\Adapter\ApcuAdapter;
    
    $adapter = new ApcuAdapter();
```

And finally, you'll need one or more collectors.

```php
<?php
    use DaybreakStudios\PrometheusClient\Collector\Counter;
    use DaybreakStudios\PrometheusClient\Collector\Gauge;
    use DaybreakStudios\PrometheusClient\Collector\Histogram;
    
    $counter = new Counter($adapter, 'test_counter', 'Please ignore');
    $registry->register($counter);
    
    $gauge = new Gauge($adapter, 'test_gauge', 'Please ignore');
    $registry->register($gauge);
    
    $histogram = new Histogram($adapter, 'test_histogram', 'Please ignore', [
    	1,
    	5,
    	15,
    	50,
    	100
    ]);
    
    $registry->register($histogram);
```

Once a collector is registered, you can either expose them as global variables, or by name via the `CollectorRegistry`
(which needs to be globally accessible in some way).

```php
<?php
    $testCounter = $registry->get('test_counter');
    $testCounter->increment();
    
    $testHistogram = $registry->get('test_histogram');
    $testHistogram->observe(153);
```

## "Strong" Typing
To help enforce types when retrieving collectors from the registry, you can use the `getCounter()`, `getGauge()`, and
`getHistogram()` methods in place of the basic `get()` method.

```php
<?php
    $counter = $registry->getCounter('test_counter');
    $counter->increment();
    
    $histogram = $registry->getHistogram('test_gauge');
    // throws DaybreakStudios\PrometheusClient\Exception\CollectorRegistryException due to type mismatch
```

In addition to performing the same `null` checking that `get()` performs, each of those methods will also check that the
collector is of the expected type, and throw an exception if the collector is not. They'll also correctly enable IDE
autocompletion, since those three methods specify the proper return type in their PHPDoc block.

## Using Labels
You must define all of a collector's labels when its registered.

```php
<?php
    use DaybreakStudios\PrometheusClient\Collector\Counter;
    
    $counter = new Counter($adapter, 'api_calls_total', 'Number of API calls made', [
        'path',
        'method',  	
    ]);
    
    $counter->increment([
    	'method' => 'GET',
    	'path' => '/users/me',
    ]);
```

The order in which you specify the labels when using the collector (i.e. in `Counter::increment()` in the example above)
does not matter, however ALL label values must be provided each time. 

## Exporting
You can export data from your registry by setting up an endpoint in your application with code similar to the code
below.

```php
<?php
    use DaybreakStudios\PrometheusClient\Export\Render\TextRenderer;
    
    $renderer = new TextRenderer();
    
    header('Content-Type: ' . $renderer->getMimeType());
    echo $renderer->render($registry->collect());
```

## Adapters
This library provides access to the underlying storage system via adapters. The built-in adapters are documented below.

### Redis
The `DaybreakStudios\PrometheusClient\Adapter\RedisAdapter` uses Redis to store metrics. To use the Redis adapter, you
simply need to provide it with the host of the Redis instance.

```php
<?php
    use DaybreakStudios\PrometheusClient\Adapter\RedisAdapter;
    use DaybreakStudios\PrometheusClient\Adapter\Redis\RedisClientConfiguration;

    $config = new RedisClientConfiguration('localhost');
    
    // You can also supply other information, such as port or password, using the setters
    // available on the configuration object, e.g.:
    //     - $config->setPort(1234)
    //     - $config->setPassword('MyTotallySecurePassword')

    $adapter = new RedisAdapter($config);
```

Keys in the Redis adapter are automatically prefixed in order to prevent collisions with other keys that might be in
your Redis instance. By default, the prefix is "dbstudios_prom:", but you can change this by providing a second argument
to the constructor of `RedisClientConfiguration`.

### Filesystem
The `DaybreakStudios\PrometheusClient\Adapter\FilesystemAdapter` uses files to store metrics. Data written to the
adapter's files is encoded using PHP's [`serialize()`](http://php.net/manual/en/function.serialize.php) function, so
types will be properly preserved. To use the `FilesystemAdapter`, you will need to specify which directory the adapter
should use to store it's files. In order to prevent data loss, the directory you specify should _only_ be used by
Prometheus.

```php
<?php
    use DaybreakStudios\PrometheusClient\Adapter\FilesystemAdapter;
    
    $adapter = new FilesystemAdapter('/var/www/html/prometheus');
```

Unlike the [APCu adapter](#apcu), cached data will persist, even if your server reboots. You can use the
`FilesystemAdapter::clear()` method to remove all files from the adapter's cache. Please keep in mind that this will
delete _everything_ in the directory you specified as the adapter's base directory.

### APCu
The `DaybreakStudios\PrometheusClient\Adapter\ApcuAdapter` uses [APCu](http://php.net/manual/en/book.apcu.php) to store
metrics. The APCU adapter uses no additional configuration.

There are a few pitfalls to be aware of, however. APCu, by default, does not persist stored data through certain events,
such as a server reboot. Additionally, it also wipes its entire cache once the cache fills up. Neither of those
should cause problems for your Prometheus installation, but it's something you should keep in mind if you choose to use
the APCu adapter.

Additionally, APCu _does not_ properly support accessing its cache for PHP sessions started from the command line.
Under a default configuration, every call to an `apcu_*` function is "black holed", meaning that they'll always return
`false`, and will not store any data in the cache. You can enable the CLI cache by adding `apc.enable_cli=1` to your
`php.ini`, but that will only keep information in the cache for the run time of the script. Once the script is done
executing, the cache data will be purged. As far as I'm aware, _there is no way to alter this behavior_.
