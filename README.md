# BearyChat for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elfsundae/laravel-bearychat.svg?style=flat-square)](https://packagist.org/packages/elfsundae/laravel-bearychat)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/ElfSundae/laravel-bearychat/master.svg?style=flat-square)](https://travis-ci.org/ElfSundae/laravel-bearychat)
[![StyleCI](https://styleci.io/repos/62485352/shield)](https://styleci.io/repos/62485352)
[![SensioLabsInsight](https://img.shields.io/sensiolabs/i/311bf772-b936-423c-ad4c-07f4b44078b7.svg?style=flat-square)](https://insight.sensiolabs.com/projects/311bf772-b936-423c-ad4c-07f4b44078b7)
[![Quality Score](https://img.shields.io/scrutinizer/g/ElfSundae/laravel-bearychat.svg?style=flat-square)](https://scrutinizer-ci.com/g/ElfSundae/laravel-bearychat)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/ElfSundae/laravel-bearychat/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/ElfSundae/laravel-bearychat/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/elfsundae/laravel-bearychat.svg?style=flat-square)](https://packagist.org/packages/elfsundae/laravel-bearychat)

The Laravel integration for [BearyChat](https://github.com/ElfSundae/bearychat) to send robot messages.

This package is compatible with [Laravel 5](#laravel-5), [Laravel 4](#laravel-4) and [Lumen](#lumen).

> - :cn: [**中文文档**](README_zh.md)
> - **Laravel Notification Channel:** [BearyChatChannel](https://github.com/laravel-notification-channels/bearychat)

## Contents

<!-- MarkdownTOC -->

- [Installation](#installation)
    - [Laravel 5](#laravel-5)
    - [Laravel 4](#laravel-4)
    - [Lumen](#lumen)
- [Usage](#usage)
    - [Basic Usage](#basic-usage)
    - [Asynchronous Message](#asynchronous-message)
    - [Sending Laravel Exceptions](#sending-laravel-exceptions)
    - [Creating Outgoing Responses](#creating-outgoing-responses)
    - [Customize Guzzle](#customize-guzzle)
- [Changelog](#changelog)
- [Testing](#testing)
- [License](#license)

<!-- /MarkdownTOC -->

## Installation

You can install this package using the [Composer](https://getcomposer.org) manager:

```sh
$ composer require elfsundae/laravel-bearychat
```

After updating composer, you may configure your app according to the following steps:

### Laravel 5

Add the service provider to the `providers` array in `config/app.php`:

```php
ElfSundae\BearyChat\Laravel\ServiceProvider::class,
```

Register facade:

```php
'BearyChat' => ElfSundae\BearyChat\Laravel\BearyChat::class,
```

Then publish the config file:

```sh
$ php artisan vendor:publish --tag=bearychat
```

Next, configure your BearyChat clients by editing the config file in `config/bearychat.php`.

### Laravel 4

Please install version [`1.1.x`](https://github.com/ElfSundae/laravel-bearychat/tree/1.1.x):

```sh
$ composer require elfsundae/laravel-bearychat:1.1.*
```

Add the service provider to the `providers` array in `config/app.php`:

```php
'ElfSundae\BearyChat\Laravel\ServiceProvider',
```

Then publish the config file:

```sh
$ php artisan config:publish elfsundae/laravel-bearychat
```

Next, configure your BearyChat clients by editing the config file in `app/config/packages/elfsundae/laravel-bearychat/config.php`.

### Lumen

Register the service provider in `bootstrap/app.php`:

```php
$app->register(ElfSundae\BearyChat\Laravel\ServiceProvider::class);
```

Then copy the config file from this package to your app's `config/bearychat.php`:

```sh
$ cp vendor/elfsundae/laravel-bearychat/config/bearychat.php config/bearychat.php
```

Now you can configure your BearyChat clients by editing `config/bearychat.php`.

## Usage

### Basic Usage

You can obtain the BearyChat `Client` using the `BearyChat` facade, or the `bearychat()` helper function.

```php
BearyChat::send('message');

bearychat()->sendTo('@elf', 'Hi!');
```

You may access various clients via the `client` method of the `BearyChat` facade, or pass a client name to the `bearychat()` function. The name should correspond to one of the clients listed in your BearyChat configuration file.

```php
BearyChat::client('dev')->send('foo');

bearychat('admin')->send('bar');
```

> **For more advanced usage, please [read the documentation](https://github.com/ElfSundae/bearychat/blob/master/README.md) of the BearyChat PHP package.**

### Asynchronous Message

Sending a BearyChat message actually requests the Incoming Webhook via synchronous HTTP, so it will slow down your app execution. For sending asynchronous messages, You can queue them using Laravel's awesome [queue system](https://laravel.com/docs/queues).

Here is an example of the Queueable Job for Laravel 5.3:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use ElfSundae\BearyChat\Message;

class SendBearyChat implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The BearyChat client.
     *
     * @var \ElfSundae\BearyChat\Client
     */
    protected $client;

    /**
     * The Message instance to be sent.
     *
     * @var \ElfSundae\BearyChat\Message
     */
    protected $message;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $message  A Message instance, or parameters which can be handled
     *                          by the `send` method of a Message instance.
     */
    public function __construct($message = null)
    {
        if ($message instanceof Message) {
            $this->message = $message;
        } elseif (is_string($message)) {
            $this->text($message);

            if (func_num_args() > 1) {
                if (is_bool($markdown = func_get_arg(1))) {
                    $this->markdown(func_get_arg(1));

                    if (func_num_args() > 2) {
                        $this->notification(func_get_arg(2));
                    }
                } else {
                    call_user_func_array([$this, 'add'], array_slice(func_get_args(), 1));
                }
            }
        }
    }

    /**
     * Any unhandled methods will be sent to the Message instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return $this
     */
    public function __call($method, $parameters)
    {
        $message = $this->message ?: new Message($this->client ?: bearychat());

        $this->message = call_user_func_array([$message, $method], $parameters);

        return $this;
    }

    /**
     * Set the client with client name.
     *
     * @param  string  $name
     * @return $this
     */
    public function client($name)
    {
        $this->client = bearychat($name);

        return $this;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if ($this->client) {
            $this->client->sendMessage($this->message);
        } else {
            $this->message->send();
        }
    }
}
```

Then you can dispatch `SendBearyChat` jobs by calling the `dispatch` method on any object which includes the `DispatchesJobs` trait, or just use the `dispatch()` global function:

```php
dispatch(new SendBearyChat('hello'));

dispatch(new SendBearyChat('hello', true, 'notification'));

dispatch(new SendBearyChat('hello', 'attachment content', 'attachment title', 'http://path/to/image', '#f00'));

dispatch((new SendBearyChat)->client('server')->text('hello')->add('attachment'));

dispatch(new SendBearyChat(
    bearychat('admin')->text('New order!')->add($order, $order->name, $order->image_url)
));
```

### Sending Laravel Exceptions

A common usage of BearyChat is real-time reporting Laravel exceptions. Just override the `report` method of your exception handler:

```php
/**
 * Report or log an exception.
 *
 * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
 *
 * @param  \Exception  $e
 * @return void
 */
public function report(Exception $e)
{
    parent::report($e);

    if (app()->environment('production') && $this->shouldReport($e)) {
        dispatch(
            (new SendBearyChat)
            ->client('server')
            ->text('New Exception!')
            ->notification('New Exception: '.get_class($e))
            ->markdown(false)
            ->add(str_limit($e, 1300), get_class($e), null, '#a0a0a0')
        );
    }
}
```

### Creating Outgoing Responses

Need to respond to an [Outgoing Robot](https://bearychat.com/integrations/outgoing)?  Simply create a JSON response with a `Message` instance.

```php
Route::post('webhook/bearychat', 'WebhookController@bearychat');
```

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ElfSundae\BearyChat\Message;

class WebhookController extends Controller
{
    /**
     * The BearyChat Outgoing Robot.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bearychat(Request $request)
    {
        $message = (new Message)
            ->text('Response for ' . $request->input('text'))
            ->add('attachment content');

        return response()->json($message);
    }
}
```

You may exclude your Outgoing handler from [Laravel's CSRF protection](https://laravel.com/docs/csrf#csrf-excluding-uris).

### Customize Guzzle

You can customize [Guzzle](http://docs.guzzlephp.org) HTTP clients for BearyChat by calling the `customHttpClient` method on the `BearyChat` facade or `app('bearychat')`.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client as HttpClient;
use ElfSundae\BearyChat\Laravel\BearyChat;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        BearyChat::customHttpClient(function ($name) {
            if ($name == 'dev') {
                return new HttpClient([
                    'connect_timeout' => 10,
                    'timeout' => 30,
                    'verify' => false
                ]);
            }
        });
    }
}
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

```sh
$ composer test
```

## License

The BearyChat Laravel package is available under the [MIT license](LICENSE).
