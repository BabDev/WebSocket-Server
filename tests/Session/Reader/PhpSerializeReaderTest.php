<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Reader;

use BabDev\WebSocket\Server\Session\Reader\PhpSerializeReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpSerializeReaderTest extends TestCase
{
    /**
     * @return \Generator<string, array{non-empty-string}>
     */
    public static function dataRead(): \Generator
    {
        yield 'Basic Symfony session payload' => ['a:2:{s:15:"_sf2_attributes";a:3:{s:3:"foo";s:3:"bar";s:12:"messages.foo";s:3:"bar";s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}s:9:"_sf2_meta";a:3:{s:1:"u";i:1653958488;s:1:"c";i:1653958488;s:1:"l";i:0;}}'];

        yield 'Symfony application session payload' => ['a:2:{s:15:"_sf2_attributes";a:3:{s:14:"_security_main";s:340:"O:74:"Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken":3:{i:0;N;i:1;s:4:"main";i:2;a:5:{i:0;O:15:"App\Entity\User":3:{s:2:"id";i:123456;s:5:"email";s:17:"xxxxxxx@xxxxx.xxx";s:8:"password";s:60:"$2y$13$9klfX3owVXOQ4jjoqTMi8.71kW5i6rHixw5E2kRE1KW7yPELxmSHi";}i:1;b:1;i:2;N;i:3;a:0:{}i:4;a:1:{i:0;s:9:"ROLE_USER";}}}";s:23:"_security.last_username";s:17:"xxxxxxx@xxxxx.xxx";s:20:"_security.last_error";O:67:"Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException":5:{i:0;N;i:1;i:0;i:2;s:19:"Invalid CSRF token.";i:3;s:83:"/var/www/html/vendor/symfony/security-http/EventListener/CsrfProtectionListener.php";i:4;i:51;}}s:9:"_sf2_meta";a:3:{s:1:"u";i:1724707524;s:1:"c";i:1724705819;s:1:"l";i:0;}}'];
    }

    /**
     * @param non-empty-string $input
     */
    #[DataProvider('dataRead')]
    public function testReadsData(string $input): void
    {
        $output = new PhpSerializeReader()->read($input);

        $this->assertArrayHasKey('_sf2_attributes', $output);
        $this->assertArrayHasKey('_sf2_meta', $output);
    }
}
