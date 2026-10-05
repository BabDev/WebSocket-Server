# Installation & Setup

To install the package, run the following [Composer](https://getcomposer.org/) command:

```bash
composer require babdev/websocket-server
```

## Optional Dependencies

Some features require additional packages or PHP extensions, which are not installed by default:

| Feature                                                                                           | Requirement                                                                                                     |
|---------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| Dispatching connection events with the `DispatchMessageToHandler` middleware                      | A [PSR-14](https://www.php-fig.org/psr/psr-14/) event dispatcher (`psr/event-dispatcher` and an implementation) |
| Resolving message handlers from a service container with the `PsrContainerMessageHandlerResolver` | A [PSR-11](https://www.php-fig.org/psr/psr-11/) container (`psr/container` and an implementation)               |
| Logging failures the server cannot handle                                                         | A [PSR-3](https://www.php-fig.org/psr/psr-3/) logger (`psr/log` and an implementation)                          |
| Reading session data with the `InitializeSession` middleware                                      | The `session` PHP extension                                                                                     |
| Graceful shutdown on `SIGTERM` and `SIGINT` when using the `Application` class                    | The `pcntl` PHP extension, or an event loop with signal support                                                 |

