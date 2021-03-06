<?php
/**
 * @license see LICENSE
 */

namespace Serps\Test\TDD\SearchEngine\Google;

use \InvalidArgumentException;
use Serps\Core\Http\HttpClientInterface;
use Serps\Core\Http\SearchEngineResponse;
use Serps\Core\UrlArchive;
use Serps\Exception\CaptchaException;
use Serps\Exception\RequestError\InvalidResponseException;
use Serps\SearchEngine\Google\Exception\GoogleCaptchaException;
use Serps\SearchEngine\Google\GoogleUrl;
use Serps\SearchEngine\Google\GoogleClient;
use Serps\SearchEngine\Google\GoogleUrlArchive;
use Serps\SearchEngine\Google\Page\GoogleCaptcha;
use Serps\SearchEngine\Google\Page\GoogleSerp;
use Zend\Diactoros\Request;
use Serps\SearchEngine\Google\GoogleClient\RequestBuilder;

/**
 * @covers Serps\SearchEngine\Google\GoogleClient
 * @covers Serps\SearchEngine\Google\GoogleClient\RequestBuilder
 */
class GoogleClientTest extends \PHPUnit_Framework_TestCase
{

    public function testValidDom()
    {
        $httpClientMock = $this->getMock(HttpClientInterface::class);
        $responseFromMock = new SearchEngineResponse(
            [],
            200,
            file_get_contents('test/resources/pages-evaluated/simpsons+movie+trailer.html'),
            false,
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            null
        );
        $httpClientMock->method('sendRequest')->willReturn($responseFromMock);

        /* @var $httpClientMock HttpClientInterface */
        $googleClient = new GoogleClient($httpClientMock);
        $url = GoogleUrl::fromString('https://www.google.fr/search?q=simpsons+movie+trailer');

        $dom = $googleClient->query($url);
        $this->assertInstanceOf(GoogleSerp::class, $dom);
        $this->assertEquals('https://www.google.fr/search?q=simpsons+movie+trailer', (string)$dom->getUrl());
    }

    public function testInvalidHttpResponse()
    {
        $httpClientMock = $this->getMock(HttpClientInterface::class);
        $responseFromMock = new SearchEngineResponse(
            [],
            400,
            file_get_contents('test/resources/pages-evaluated/simpsons+movie+trailer.html'),
            false,
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            null
        );
        $httpClientMock->method('sendRequest')->willReturn($responseFromMock);

        /* @var $httpClientMock HttpClientInterface */
        $googleClient = new GoogleClient($httpClientMock);
        $url = GoogleUrl::fromString('https://www.google.fr/search?q=simpsons+movie+trailer');

        try {
            $googleClient->query($url);
            $this->fail('Excetpion should be thrown');
        } catch (InvalidResponseException $e) {
            $this->assertEquals(400, $e->getHttpStatusCode());
        }
    }

    public function testCaptchaDom()
    {
        $httpClientMock = $this->getMock(HttpClientInterface::class);
        $responseFromMock = new SearchEngineResponse(
            [],
            503,
            file_get_contents('test/resources/pages-evaluated/captcha.html'),
            false,
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            null
        );

        $httpClientMock->method('sendRequest')->willReturn($responseFromMock);

        /* @var $httpClientMock HttpClientInterface */
        $googleClient = new GoogleClient($httpClientMock);
        $url = GoogleUrl::fromString('https://www.google.fr/search?q=simpsons+movie+trailer');

        try {
            $googleClient->query($url);
            $this->fail('Exception not thrown');
        } catch (GoogleCaptchaException $e) {
            $this->assertInstanceOf(GoogleCaptcha::class, $e->getCaptcha());
            $this->assertEquals('128.78.166.25', $e->getCaptcha()->getDetectedIp());
        }
    }

    public function testUserAgentAccessors()
    {
        $googleClient = new GoogleClient($this->getMock(HttpClientInterface::class));
        $googleClient->getRequestBuilder()->setUserAgent('test-user-agent');
        $this->assertEquals('test-user-agent', $googleClient->getRequestBuilder()->getUserAgent());

        $googleClient->getRequestBuilder()->setUserAgent('foo-user-agent');
        $this->assertEquals('foo-user-agent', $googleClient->getRequestBuilder()->getUserAgent());

        $googleClient->getRequestBuilder()->setUserAgent(null);
        $this->assertEquals(null, $googleClient->getRequestBuilder()->getUserAgent());

        $this->setExpectedException(InvalidArgumentException::class);
        $googleClient->getRequestBuilder()->setUserAgent(true);
    }

    public function testAcceptLanguage()
    {
        $googleClient = new GoogleClient($this->getMock(HttpClientInterface::class));
        $request = $googleClient->getRequestBuilder()->buildRequest(new GoogleUrl());
        $this->assertEquals(['en'], $request->getHeader('Accept-language'));

        $googleClient->getRequestBuilder()->setDefaultAcceptLanguage('fr');
        $request = $googleClient->getRequestBuilder()->buildRequest(new GoogleUrl());
        $this->assertEquals(['fr'], $request->getHeader('Accept-language'));

        $url = new GoogleUrl();
        $url->setLanguageRestriction('es');
        $request = $googleClient->getRequestBuilder()->buildRequest($url);
        $this->assertEquals(['es'], $request->getHeader('Accept-language'));

        $googleClient->getRequestBuilder()->setAcceptLanguageFromUrl(false);
        $request = $googleClient->getRequestBuilder()->buildRequest(new GoogleUrl());
        $this->assertEquals(['fr'], $request->getHeader('Accept-language'));
    }


    public function testUserAgentWithOnRequest()
    {
        $httpClientMock = $this->getMock(HttpClientInterface::class);
        $responseFromMock = new SearchEngineResponse(
            [],
            200,
            file_get_contents('test/resources/pages-evaluated/simpsons+movie+trailer.html'),
            false,
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            GoogleUrlArchive::fromString('https://www.google.fr/search?q=simpsons+movie+trailer'),
            null
        );
        $httpClientMock->method('sendRequest')->willReturn($responseFromMock);


        /* @var $httpClientMock HttpClientInterface */
        $googleClient = new GoogleClient($httpClientMock);
        $googleClient->getRequestBuilder()->setUserAgent('foo-ua');
        $url = GoogleUrl::fromString('https://www.google.fr/search?q=simpsons+movie+trailer');
        $request = $googleClient->getRequestBuilder()->buildRequest($url);

        $this->assertCount(1, $request->getHeader('user-agent'));
        $this->assertEquals('foo-ua', $request->getHeader('user-agent')[0]);
    }

    public function testRequestAccesor()
    {
        $googlelient = new GoogleClient($this->getMock(HttpClientInterface::class));

        $requestBuilder = $googlelient->getRequestBuilder();
        $this->assertInstanceOf(RequestBuilder::class, $requestBuilder);
        $this->assertSame($requestBuilder, $googlelient->request);
    }
}
