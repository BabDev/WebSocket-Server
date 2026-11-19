<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Storage;

use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Exception\InvalidSession;
use BabDev\WebSocket\Server\Session\Exception\ReadOnlySession;
use BabDev\WebSocket\Server\Session\Exception\SessionMisconfigured;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\Session\Reader\PhpBinaryReader;
use BabDev\WebSocket\Server\Session\Reader\PhpReader;
use BabDev\WebSocket\Server\Session\Reader\PhpSerializeReader;
use BabDev\WebSocket\Server\Session\Reader\Reader;
use BabDev\WebSocket\Server\Session\Storage\Proxy\ReadOnlySessionHandlerProxy;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final class ReadOnlyNativeSessionStorage implements SessionStorageInterface
{
    private bool $started = false;

    private bool $closed = false;

    /**
     * @var array<string, string>
     */
    private array $options = [];

    /**
     * @var array<string, SessionBagInterface>
     */
    private array $bags = [];

    private MetadataBag $metadataBag;

    private readonly Reader $reader;

    private AbstractProxy|\SessionHandlerInterface $saveHandler;

    /**
     * @param array $options See {@see NativeSessionStorage::setOptions()} for supported values
     *
     * @throws SessionMisconfigured if the PHP "session" extension is not active
     */
    public function __construct(
        private readonly OptionsHandler $optionsHandler = new IniOptionsHandler(),
        ?Reader $reader = null,
        array $options = [],
        AbstractProxy|\SessionHandlerInterface|null $handler = null,
        ?MetadataBag $metaBag = null
    ) {
        if (!\extension_loaded('session')) {
            throw new SessionMisconfigured('PHP extension "session" is required.');
        }

        $this->setMetadataBag($metaBag);
        $this->setOptions(['use_strict_mode' => 1, ...$options]);
        $this->setSaveHandler($handler);

        $this->reader = $reader ?? $this->createReader();
    }

    /**
     * @throws InvalidSession if the session data cannot be read or deserialized
     */
    public function start(): bool
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        $id = $this->saveHandler->getId();

        if (!$this->isValidIdFormat($id)) {
            $this->loadSession([]);

            return true;
        }

        $this->saveHandler->open((string) $this->optionsHandler->get('session.save_path'), $this->getName());

        if ($this->isStrictMode() && $this->saveHandler instanceof \SessionUpdateTimestampHandlerInterface && !$this->saveHandler->validateId($id)) {
            $this->loadSession([]);

            return true;
        }

        $data = $this->saveHandler->read($id);

        if (false === $data) {
            throw new InvalidSession('', 'Failed to read the session data.');
        }

        $sessionData = $this->reader->read($data);

        $this->loadSession($sessionData);

        if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
            $this->saveHandler->setActive(false);
        }

        return true;
    }

    public function getId(): string
    {
        return $this->saveHandler->getId();
    }

    /**
     * @note The ID is allowed to be mutated with this storage implementation because the {@see InitializeSession}
     *       needs to parse the ID from the incoming request, which happens after the session object is instantiated.
     */
    public function setId(string $id): void
    {
        $this->saveHandler->setId($id);
    }

    public function getName(): string
    {
        return $this->options['name'] ?? $this->saveHandler->getName();
    }

    /**
     * @throws ReadOnlySession
     */
    public function setName(string $name): never
    {
        throw new ReadOnlySession(\sprintf('The session name cannot be changed in "%s".', self::class));
    }

    /**
     * @throws ReadOnlySession
     */
    public function regenerate(bool $destroy = false, ?int $lifetime = null): never
    {
        throw new ReadOnlySession(\sprintf('The session cannot be regenerated in "%s".', self::class));
    }

    public function save(): void
    {
        if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
            $this->saveHandler->setActive(false);
        }

        $this->closed = true;
        $this->started = false;
    }

    public function clear(): void
    {
        foreach ($this->bags as $bag) {
            $bag->clear();
        }

        $this->loadSession([]);
    }

    public function registerBag(SessionBagInterface $bag): void
    {
        if ($this->started) {
            throw new \LogicException('Cannot register a bag when the session is already started.');
        }

        $this->bags[$bag->getName()] = $bag;
    }

    public function getBag(string $name): SessionBagInterface
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(\sprintf('The SessionBagInterface "%s" is not registered.', $name));
        }

        if (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    public function setMetadataBag(?MetadataBag $metaBag = null): void
    {
        $this->metadataBag = $metaBag ?? new MetadataBag();
    }

    public function getMetadataBag(): MetadataBag
    {
        return $this->metadataBag;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Sets the session options, using the "session." ini setting names without the prefix.
     *
     * The options are kept by the storage, the PHP ini settings are not changed.
     *
     * @see NativeSessionStorage::setOptions()
     *
     * @note The options list is based on the supported options as of Symfony 7.2.0
     *
     * @throws \TypeError if an option value is not a scalar or null
     */
    public function setOptions(array $options): void
    {
        $validOptions = array_flip([
            'cache_expire', 'cache_limiter', 'cookie_domain', 'cookie_httponly',
            'cookie_lifetime', 'cookie_path', 'cookie_secure', 'cookie_samesite',
            'gc_divisor', 'gc_maxlifetime', 'gc_probability',
            'lazy_write', 'name', 'referer_check',
            'serialize_handler', 'use_strict_mode', 'use_cookies',
            'use_only_cookies', 'use_trans_sid',
            'sid_length', 'sid_bits_per_character', 'trans_sid_hosts', 'trans_sid_tags',
        ]);

        foreach ($options as $key => $value) {
            if (\in_array($key, ['referer_check', 'use_only_cookies', 'use_trans_sid', 'trans_sid_hosts', 'trans_sid_tags', 'sid_length', 'sid_bits_per_character'], true)) {
                trigger_deprecation('babdev/websocket-server', '0.1', 'The "%s" option for the read-only native session storage is deprecated as of PHP 8.4 and support will be removed before the 1.0 release.', $key);
            }

            if (isset($validOptions[$key])) {
                if ('cookie_secure' === $key && 'auto' === $value) {
                    continue;
                }

                $this->options[$key] = match (true) {
                    null === $value, false === $value => '',
                    true === $value => '1',
                    \is_scalar($value) => (string) $value,
                    default => throw new \TypeError(\sprintf('The "%s" session option must be a scalar or null, "%s" given.', $key, get_debug_type($value))),
                };
            }
        }
    }

    public function setSaveHandler(AbstractProxy|\SessionHandlerInterface|null $saveHandler = null): void
    {
        if (!$saveHandler instanceof AbstractProxy && $saveHandler instanceof \SessionHandlerInterface) {
            $saveHandler = new ReadOnlySessionHandlerProxy($saveHandler, $this->optionsHandler);
        } elseif (!$saveHandler instanceof AbstractProxy) {
            $saveHandler = new ReadOnlySessionHandlerProxy(new StrictSessionHandler(new \SessionHandler()), $this->optionsHandler);
        }

        $this->saveHandler = $saveHandler;
    }

    /**
     * @throws SessionMisconfigured if the "session.serialize_handler" option is not set to a supported value
     */
    private function createReader(): Reader
    {
        $serializeHandler = $this->getOption('serialize_handler');

        return match ($serializeHandler) {
            'php' => new PhpReader(),
            'php_binary' => new PhpBinaryReader(),
            'php_serialize' => new PhpSerializeReader(),
            default => throw new SessionMisconfigured(\sprintf('The "%s" serialize handler is not supported in "%s", set the "serialize_handler" storage option or the PHP "session.serialize_handler" setting to a supported handler or inject your own "%s" instance.', $serializeHandler, self::class, Reader::class)),
        };
    }

    /**
     * Checks whether the session ID has the format of an ID generated by PHP.
     *
     * This uses the same rule as Symfony's {@see NativeSessionStorage::start()}: the characters allowed by PHP for a
     * session ID, with a length between the minimum and maximum lengths PHP can generate.
     */
    private function isValidIdFormat(string $id): bool
    {
        return 1 === preg_match('/^[a-zA-Z0-9,-]{22,250}$/', $id);
    }

    private function isStrictMode(): bool
    {
        return filter_var($this->getOption('use_strict_mode'), \FILTER_VALIDATE_BOOLEAN);
    }

    private function getOption(string $key): string|false
    {
        return $this->options[$key] ?? $this->optionsHandler->get('session.'.$key);
    }

    private function loadSession(array $session): void
    {
        $bags = [...$this->bags, ...[$this->metadataBag]];

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) && \is_array($session[$key]) ? $session[$key] : [];
            $bag->initialize($session[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
