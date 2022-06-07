<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Storage\Proxy;

use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Exception\ReadOnlySession;
use BabDev\WebSocket\Server\Session\Exception\SessionMisconfigured;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;

final class ReadOnlySessionHandlerProxy extends AbstractProxy implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private ?string $sessionId = null;

    private readonly string $sessionName;

    public function __construct(
        public readonly \SessionHandlerInterface $handler,
        private readonly OptionsHandler $optionsHandler = new IniOptionsHandler(),
    ) {
        $this->wrapper = $handler instanceof \SessionHandler;
        $this->saveHandlerName = $this->wrapper ? $this->optionsHandler->get('session.save_handler') : 'user';
        $this->sessionName = $this->optionsHandler->get('session.name');
    }

    /**
     * @throws SessionMisconfigured
     */
    public function getId(): string
    {
        if (null === $this->sessionId) {
            throw new SessionMisconfigured(sprintf('The session ID must be set in "%s" before calling %s(), make sure "%s::setId()" has been called.', self::class, __METHOD__, self::class));
        }

        return $this->sessionId;
    }

    /**
     * @throws ReadOnlySession if trying to change the session ID once it has been set
     */
    public function setId(string $id): void
    {
        if (null !== $this->sessionId) {
            throw new ReadOnlySession(sprintf('The session ID cannot be changed once set in "%s".', self::class));
        }

        $this->sessionId = $id;
    }

    public function getName(): string
    {
        return $this->sessionName;
    }

    /**
     * @throws ReadOnlySession
     */
    public function setName(string $name): never
    {
        throw new ReadOnlySession(sprintf('The session name cannot be changed in "%s".', self::class));
    }

    public function open(string $path, string $name): bool
    {
        return $this->handler->open($path, $name);
    }

    public function close(): bool
    {
        return $this->handler->close();
    }

    public function read(string $id): string|false
    {
        return $this->handler->read($id);
    }

    /**
     * @throws ReadOnlySession
     */
    public function write(string $id, string $data): never
    {
        throw new ReadOnlySession('Cannot write updated session data with a read-only session.');
    }

    public function destroy(string $id): bool
    {
        return $this->handler->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->handler->gc($max_lifetime);
    }

    public function validateId(string $id): bool
    {
        return !$this->handler instanceof \SessionUpdateTimestampHandlerInterface || $this->handler->validateId($id);
    }

    /**
     * @throws ReadOnlySession
     */
    public function updateTimestamp(string $id, string $data): never
    {
        throw new ReadOnlySession('Cannot write updated session data with a read-only session.');
    }
}
