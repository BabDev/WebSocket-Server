<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Storage;

use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
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

        $this->reader = $reader ?? $this->createReader();

        $options = array_merge(
            [
                'auto_start' => 0,
                'cache_limiter' => '',
                'cache_expire' => 0,
                'use_cookies' => 0,
                'lazy_write' => 1,
                'use_strict_mode' => 1,
            ],
            $options
        );

        $this->setMetadataBag($metaBag);
        $this->setOptions($options);
        $this->setSaveHandler($handler);
    }

    public function start(): bool
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        $this->saveHandler->open(session_save_path(), $this->saveHandler->getName());

        $sessionData = $this->reader->read($this->saveHandler->read($this->saveHandler->getId()));

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
        return $this->saveHandler->getName();
    }

    /**
     * @throws ReadOnlySession
     */
    public function setName(string $name): never
    {
        throw new ReadOnlySession(sprintf('The session name cannot be changed in "%s".', self::class));
    }

    /**
     * @throws ReadOnlySession
     */
    public function regenerate(bool $destroy = false, int $lifetime = null): never
    {
        throw new ReadOnlySession(sprintf('The session cannot be regenerated in "%s".', self::class));
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
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface "%s" is not registered.', $name));
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
     * Sets session.* ini variables.
     *
     * For convenience we omit 'session.' from the beginning of the keys.
     * Explicitly ignores other ini keys.
     *
     * @see NativeSessionStorage::setOptions()
     *
     * @note The options list is based on the supported options as of Symfony 6.1.0
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
            if (isset($validOptions[$key])) {
                if ('cookie_secure' === $key && 'auto' === $value) {
                    continue;
                }

                $this->optionsHandler->set('session.'.$key, $value);
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
        return match ($this->optionsHandler->get('session.serialize_handler')) {
            'php' => new PhpReader(),
            'php_binary' => new PhpBinaryReader(),
            'php_serialize' => new PhpSerializeReader(),
            default => throw new SessionMisconfigured(sprintf('The "%s" serialize handler is not supported in "%s", set the PHP "session.serialize_handler" option to a supported handler or inject your own "%s" instance.', $this->optionsHandler->get('session.serialize_handler'), self::class, Reader::class)),
        };
    }

    private function loadSession(array $session): void
    {
        $bags = array_merge($this->bags, [$this->metadataBag]);

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) && \is_array($session[$key]) ? $session[$key] : [];
            $bag->initialize($session[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
