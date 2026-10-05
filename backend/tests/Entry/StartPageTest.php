<?php

declare(strict_types=1);

namespace CommonSight\Tests\Entry;

use CommonSight\Application\StartPageGate;
use CommonSight\Config\AuthSettings;
use CommonSight\Config\ConfigError;
use CommonSight\Entry\AuthLoader;
use CommonSight\Entry\StartPageBranding;
use CommonSight\Entry\StartPageEndpoint;
use CommonSight\Sdk\Auth\AuthProvider;
use CommonSight\Sdk\Auth\AuthRequest;
use CommonSight\Sdk\Auth\Community;
use CommonSight\Tests\Support\CollectingLogger;
use CommonSight\Tests\Support\Fixtures;
use CommonSight\Tests\Support\TempDir;
use PHPUnit\Framework\TestCase;

/**
 * The start page through gate.php (ACCESS-AND-BRANDING A-D3): unchanged without a provider and for an admitted visitor;
 * for one who is not, the element is marked with the community's name and links; closed on every failure.
 */
final class StartPageTest extends TestCase
{
    private const PAGE = "<!doctype html>\n<title>CommonSight</title>\n<link rel=\"icon\" type=\"image/svg+xml\" href=\"./custom/favicon.svg\" />\n<main><commonsight-map country=\"AT\" logo=\"./custom/logo.svg\"></commonsight-map></main>\n";

    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = TempDir::create('start-page');
        TempDir::write($this->dir . '/index.html', self::PAGE);
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->dir);
    }

    private function respond(?AuthProvider $provider, CollectingLogger $log = new CollectingLogger()): string
    {
        return (new StartPageEndpoint(new StartPageGate($provider, $log), $this->dir . '/index.html', StartPageBranding::none()))->respond(['wsc_session' => 'abc', 'stray' => ['x']], 'https://cs.example.org/');
    }

    public function testWithoutAProviderThePageIsOpen(): void
    {
        self::assertSame(self::PAGE, $this->respond(null));
    }

    public function testAnAdmittedVisitorGetsThePageUnchanged(): void
    {
        self::assertSame(self::PAGE, $this->respond(new FixedProvider(true)));
    }

    public function testAVisitorWhoIsNotAdmittedGetsTheMembersCardWithTheLinksOfTheCommunity(): void
    {
        $provider = new FixedProvider(false);
        $html = $this->respond($provider);

        self::assertStringContainsString('<commonsight-map access="denied" community="Forum &quot;A&amp;B&quot;" login-url="https://forum.example.org/login?url=https%3A%2F%2Fcs.example.org%2F" register-url="https://forum.example.org/register" country="AT"', $html);
        self::assertSame(['wsc_session' => 'abc'], $provider->seen?->cookies, 'only text cookies reach the provider');
    }

    public function testAFailureOfTheProviderClosesThePageAndIsLogged(): void
    {
        $log = new CollectingLogger();
        $html = $this->respond(new FixedProvider(new \RuntimeException('forum database not reachable')), $log);

        self::assertStringContainsString('<commonsight-map access="denied" community="Forum', $html);
        self::assertContains('auth.failed', $log->events());
    }

    public function testClosedWithoutAnyProviderToAsk(): void
    {
        self::assertStringContainsString('<commonsight-map access="denied" country="AT"', StartPageEndpoint::closed($this->dir . '/index.html', StartPageBranding::none()));
    }

    /** Title, name, logo and favicon from custom/branding.json, in any format; they win over the defaults of the page. */
    public function testTheBrandingOfTheInstallationSetsTitleNameLogoAndFavicon(): void
    {
        TempDir::write($this->dir . '/custom/branding.json', (string) json_encode(['title' => 'Beispiel & $1', 'name' => 'BEISPIEL', 'logo' => './custom/previval-logo.png', 'logoLink' => 'https://previval.org/', 'logoAlt' => 'PREVIVAL "Krisenvorsorge"', 'favicon' => './custom/previval-favicon.png']));
        $html = StartPageBranding::fromFile($this->dir . '/custom/branding.json')->apply(self::PAGE);

        self::assertStringContainsString('<commonsight-map site-name="BEISPIEL" logo="./custom/previval-logo.png" logo-link="https://previval.org/" logo-alt="PREVIVAL &quot;Krisenvorsorge&quot;" country="AT" logo="./custom/logo.svg">', $html);
        self::assertStringContainsString('<link rel="icon" href="./custom/previval-favicon.png" />', $html);
        self::assertStringContainsString('<title>Beispiel &amp; $1</title>', $html, 'escaped, and "$1" stays text');
        self::assertStringNotContainsString('<title>CommonSight</title>', $html);
        self::assertSame(self::PAGE, StartPageBranding::fromFile($this->dir . '/custom/none.json')->apply(self::PAGE), 'without the file the defaults stay');
    }

    public function testABrandingWithAScriptAddressIsRefused(): void
    {
        TempDir::write($this->dir . '/custom/branding.json', '{"logoLink": "javascript:alert(1)"}');

        $this->expectException(\InvalidArgumentException::class);
        StartPageBranding::fromFile($this->dir . '/custom/branding.json');
    }

    public function testAnUnknownProviderIsAConfigurationError(): void
    {
        $this->expectException(ConfigError::class);
        $this->expectExceptionMessage('auth.provider: unknown provider keycloak (known: woltlab)');

        (new AuthLoader(Fixtures::generated()))->provider(new AuthSettings('keycloak'));
    }

    public function testInvalidSettingsOfTheProviderAreAConfigurationError(): void
    {
        $this->expectException(ConfigError::class);
        $this->expectExceptionMessage('auth.settings: cookie');

        (new AuthLoader(Fixtures::generated()))->provider(new AuthSettings('woltlab', ['cookie' => 'a b']));
    }
}

/** A provider with a fixed answer, or a failure; remembers the request it saw. */
final class FixedProvider implements AuthProvider
{
    public ?AuthRequest $seen = null;

    public function __construct(private readonly bool|\RuntimeException $answer) {}

    public function admits(AuthRequest $request): bool
    {
        $this->seen = $request;
        if ($this->answer instanceof \RuntimeException) {
            throw $this->answer;
        }

        return $this->answer;
    }

    public function community(AuthRequest $request): Community
    {
        return new Community('Forum "A&B"', 'https://forum.example.org/login?url=' . rawurlencode($request->pageUrl), 'https://forum.example.org/register');
    }
}
