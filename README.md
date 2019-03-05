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

Instantiation will vary from adapter to adapter, so please check the documentation for the adapter you're using.

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
    $testCounter->inc();
    
    $testHistogram = $registry->get('test_histogram');
    $testHistogram->observe(153);
```

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