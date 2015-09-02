<?php

namespace Phapi\Tests\ContentNegotiation;

use Phapi\Middleware\ContentNegotiation\FormatNegotiation;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @coversDefaultClass \Phapi\Middleware\ContentNegotiation\FormatNegotiation
 */
class FormatNegoitationTest extends TestCase
{

    public function testInvoke()
    {
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $container->shouldReceive('offsetExists')->with('acceptTypes')->andReturn(true);
        $container->shouldReceive('offsetGet')->with('acceptTypes')->andReturn(['application/json', 'text/json']);
        $container->shouldReceive('offsetExists')->with('charset')->andReturn(true);
        $container->shouldReceive('offsetGet')->with('charset')->andReturn('utf-8');

        $middleware = new FormatNegotiation();
        $middleware->setContainer($container);

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('Accept')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('Accept')->andReturn('application/json;version=2');
        $request->shouldReceive('withAttribute')->withArgs(['Accept', 'application/json'])->andReturnSelf();
        $request->shouldReceive('withAttribute')->withArgs(['Accept-Parameters', [ 'version' => 2]])->andReturnSelf();

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('withHeader')->withArgs(['Content-Type', 'application/json;charset=utf-8']);

        $middleware(
            $request,
            $response,
            function ($request, $response) {
                return $response;
            }
        );
    }

    public function dataProviderParse()
    {
        return [
            [
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.1,text/*;q=0.7,image/gif; q=0.8,image/*;q=0.6,image/jpeg; q=0.6',
                [
                    [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ],
                    [ 'value' => 'application/xhtml+xml', 'quality' => 1, 'index' => 1 ],
                    [ 'value' => 'application/xml', 'quality' => 0.9, 'index' => 2 ],
                    [ 'value' => '*/*', 'quality' => 0.1, 'index' => 3 ],
                    [ 'value' => 'text/*', 'quality' => 0.7, 'index' => 4 ],
                    [ 'value' => 'image/gif', 'quality' => 0.8, 'index' => 5 ],
                    [ 'value' => 'image/*', 'quality' => 0.6, 'index' => 6 ],
                    [ 'value' => 'image/jpeg', 'quality' => 0.6, 'index' => 7 ],
                ]
            ],
            [
                'text/html,application/xhtml+xml,application/xml;q=0.9,image/gif; q=0.8,text/*;q=0.7,image/jpeg; q=0.6,image/*;q=0.6,*/*;q=0.1',
                [
                    [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ],
                    [ 'value' => 'application/xhtml+xml', 'quality' => 1, 'index' => 1 ],
                    [ 'value' => 'application/xml', 'quality' => 0.9, 'index' => 2 ],
                    [ 'value' => 'image/gif', 'quality' => 0.8, 'index' => 3 ],
                    [ 'value' => 'text/*', 'quality' => 0.7, 'index' => 4 ],
                    [ 'value' => 'image/jpeg', 'quality' => 0.6, 'index' => 5 ],
                    [ 'value' => 'image/*', 'quality' => 0.6, 'index' => 6 ],
                    [ 'value' => '*/*', 'quality' => 0.1, 'index' => 7],
                ]
            ],
            [
                'text/html,application/json;version=2,application/xml;q=0.9,image/gif; q=0.8,text/*;q=0.7,image/jpeg; q=0.6,image/*;q=0.6,*/*;q=0.1',
                [
                    [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ],
                    [ 'value' => 'application/json', 'quality' => 1, 'index' => 1 , 'parameters' => [ 'version' => '2' ]],
                    [ 'value' => 'application/xml', 'quality' => 0.9, 'index' => 2 ],
                    [ 'value' => 'image/gif', 'quality' => 0.8, 'index' => 3 ],
                    [ 'value' => 'text/*', 'quality' => 0.7, 'index' => 4 ],
                    [ 'value' => 'image/jpeg', 'quality' => 0.6, 'index' => 5 ],
                    [ 'value' => 'image/*', 'quality' => 0.6, 'index' => 6 ],
                    [ 'value' => '*/*', 'quality' => 0.1, 'index' => 7],
                ]
            ]
        ];
    }

    /**
     * @dataProvider dataProviderParse
     */
    public function testParse($header, $expected)
    {
        $negotiation = new FormatNegotiation();
        $parts = $negotiation->parseHeader($header);
        $this->assertEquals($expected, $parts);
    }

    public function dataProviderSort()
    {
        return [
            [
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.1,text/*;q=0.7,image/gif; q=0.8,image/*;q=0.6,image/jpeg; q=0.6',
                [
                    0 => [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ],
                    1 => [ 'value' => 'application/xhtml+xml', 'quality' => 1, 'index' => 1 ],
                    2 => [ 'value' => 'application/xml', 'quality' => 0.9, 'index' => 2 ],
                    5 => [ 'value' => 'image/gif', 'quality' => 0.8, 'index' => 5 ],
                    4 => [ 'value' => 'text/*', 'quality' => 0.7, 'index' => 4 ],
                    7 => [ 'value' => 'image/jpeg', 'quality' => 0.6, 'index' => 7 ],
                    6 => [ 'value' => 'image/*', 'quality' => 0.6, 'index' => 6 ],
                    3 => [ 'value' => '*/*', 'quality' => 0.1, 'index' => 3 ],
                ]
            ],
            [
                'text/html,application/xhtml+xml,application/xml;q=0.9,image/gif; q=0.8,text/*;q=0.7,image/jpeg; q=0.6,image/*;q=0.6,*/*;q=0.1',
                [
                    0 => [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ],
                    1 => [ 'value' => 'application/xhtml+xml', 'quality' => 1, 'index' => 1 ],
                    2 => [ 'value' => 'application/xml', 'quality' => 0.9, 'index' => 2 ],
                    3 => [ 'value' => 'image/gif', 'quality' => 0.8, 'index' => 3 ],
                    4 => [ 'value' => 'text/*', 'quality' => 0.7, 'index' => 4 ],
                    5 => [ 'value' => 'image/jpeg', 'quality' => 0.6, 'index' => 5 ],
                    6 => [ 'value' => 'image/*', 'quality' => 0.6, 'index' => 6 ],
                    7 => [ 'value' => '*/*', 'quality' => 0.1, 'index' => 7],
                ]
            ],
            [
                'text/html,application/xhtml+xml,*/*',
                [
                    0 => [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ],
                    1 => [ 'value' => 'application/xhtml+xml', 'quality' => 1, 'index' => 1 ],
                    2 => [ 'value' => '*/*', 'quality' => 1, 'index' => 2],
                ]
            ]
        ];
    }

    /**
     * @dataProvider dataProviderSort
     */
    public function testSort($header, $expected)
    {
        $negotiation = new FormatNegotiation();
        $parts = $negotiation->parseHeader($header);
        $sorted = $negotiation->sortParts($parts);
        $this->assertEquals($expected, $sorted);
    }

    /**
     * @dataProvider dataProviderForGetBest
     */
    public function testGetBestFormat($acceptHeader, $priorities, $expected)
    {
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $middleware = new FormatNegotiation();
        $middleware->setContainer($container);

        $this->assertEquals($expected, $middleware->getBestFormat($acceptHeader, $priorities));
    }

    public function dataProviderForGetBest()
    {
        $pearAcceptHeader = 'text/html,
            application/xhtml+xml,
            application/xml;q=0.9,
            */*;q=0.1,
            text/*;q=0.7,
            image/gif; q=0.8,
            image/*;q=0.4,
            image/jpeg; q=0.6';

        // SORTED:  'text/html, application/xml;q=0.9, image/gif; q=0.8, text/*;q=0.7,image/jpeg; q=0.6,image/*;q=0.4,*/*;q=0.1'

        return array(
            // PEAR HTTP2 tests
            array(
                $pearAcceptHeader,
                array(
                    'image/gif',
                    'image/png',
                    'application/xhtml+xml',
                    'application/xml',
                    'text/html',
                    'image/jpeg',
                    'text/plain',
                ),
                [ 'value' => 'text/html', 'quality' => 1, 'index' => 0 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'image/gif',
                    'image/png',
                    'application/xhtml+xml',
                    'application/xml',
                    'image/jpeg',
                    'text/plain',
                ),
                [ 'value' => 'application/xhtml+xml', 'quality' => 1, 'index' => 1 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'image/gif',
                    'image/png',
                    'application/xml',
                    'image/jpeg',
                    'text/plain',
                ),
                [ 'value' => 'application/xml', 'quality' => 0.9, 'index' => 2 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'image/gif',
                    'image/png',
                    'image/jpeg',
                    'text/plain',
                ),
                [ 'value' => 'image/gif', 'quality' => 0.8, 'index' => 5 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'image/png',
                    'image/jpeg',
                    'text/plain',
                ),
                [ 'value' => 'text/plain', 'quality' => 0.7, 'index' => 4 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'image/png',
                    'image/jpeg',
                ),
                [ 'value' => 'image/jpeg', 'quality' => 0.6, 'index' => 7 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'image/png',
                ),
                [ 'value' => 'image/png', 'quality' => 0.4, 'index' => 6 ]
            ),
            array(
                $pearAcceptHeader,
                array(
                    'audio/midi',
                ),
                [ 'value' => 'audio/midi', 'quality' => 0.1 ]
            ),
            array(
                'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                array(
                    'application/rss+xml'
                ),
                [ 'value' => 'application/rss+xml', 'quality' => 0.8 ]
            ),
            array(
                'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5',
                array(
                    'text/html'
                ),
                array(
                    'value'   => 'text/html',
                    'quality' => 1,
                    'parameters' => [
                        'level' => 1
                    ],
                    'index' => 2
                )
            ),
            array(
                'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5',
                array(
                    'text/plain'
                ),
                array(
                    'value'   => 'text/plain',
                    'quality' => 0.5,
                )
            ),
            array(
                'text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5',
                array(
                    'image/jpeg',
                ),
                array(
                    'value'   => 'image/jpeg',
                    'quality' => 0.5,
                )
            ),
            array(
                '*/*',
                array('application/json'),
                array(
                    'value'      => 'application/json',
                    'quality'    => 1,
                ),
            ),
            // Incompatible
            array(
                'text/html',
                array(
                    'application/rss'
                ),
                null
            ),
            array(
                'text/plain; q=0.5, text/html, text/x-dvi; q=0.8, text/x-c',
                array(),
                null,
            ),
            // IE8 Accept header
            array(
                'image/jpeg, application/x-ms-application, image/gif, application/xaml+xml, image/pjpeg, application/x-ms-xbap, */*',
                array(
                    'text/html',
                    'application/xhtml+xml'
                ),
                [ 'value' => 'text/html', 'quality' => 1 ]
            ),
        );
    }

    public function testInvokeException()
    {
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $container->shouldReceive('offsetExists')->with('acceptTypes')->andReturn(true);
        $container->shouldReceive('offsetGet')->with('acceptTypes')->andReturn(['application/json', 'text/json']);
        $container->shouldReceive('offsetSet')->withAnyArgs();
        $container->shouldReceive('offsetExists')->with('charset')->andReturn(true);
        $container->shouldReceive('offsetGet')->with('charset')->andReturn('utf-8');
        $middleware = new FormatNegotiation();
        $middleware->setContainer($container);

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('Accept')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('Accept')->andReturn('application/xml');

        $request->shouldReceive('withAttribute')->withArgs(['Accept', 'application/json'])->andReturnSelf();

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('withHeader')->with('Content-Type', 'application/json;charset=utf-8')->andReturnSelf();

        $this->setExpectedException('Phapi\Exception\NotAcceptable', 'Can not send a response which is acceptable according to the Accept header.');
        $middleware(
            $request,
            $response,
            function ($request, $response) {
                return $response;
            }
        );
    }

    public function testInvokeNoSerializers()
    {
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $container->shouldReceive('offsetExists')->with('acceptTypes')->andReturn(false);
        $container->shouldReceive('offsetGet')->with('acceptTypes')->andReturn(['application/json', 'text/json']);
        $container->shouldReceive('offsetSet')->withAnyArgs();
        $middleware = new FormatNegotiation();
        $middleware->setContainer($container);

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('Accept')->andReturn(true);
        $request->shouldReceive('getHeaderLine')->with('Accept')->andReturn('application/xml');

        $request->shouldReceive('withAttribute')->withArgs(['Accept', 'application/json'])->andReturnSelf();
        $request->shouldReceive('withAttribute')->withArgs(['Accept-Parameters', [ 'version' => 2]])->andReturnSelf();

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('withHeader')->withArgs(['Content-Type', 'application/json']);

        $this->setExpectedException('Phapi\Exception\InternalServerError', 'No serializers seems to be registered');
        $middleware(
            $request,
            $response,
            function ($request, $response) {
                return $response;
            }
        );
    }

    public function testInvokeWithNoHeader()
    {
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $container->shouldReceive('offsetExists')->with('acceptTypes')->andReturn(true);
        $container->shouldReceive('offsetGet')->with('acceptTypes')->andReturn(['application/json', 'text/json']);
        $container->shouldReceive('offsetExists')->with('charset')->andReturn(false);
        $middleware = new FormatNegotiation();
        $middleware->setContainer($container);

        $request = \Mockery::mock('Psr\Http\Message\ServerRequestInterface');
        $request->shouldReceive('hasHeader')->with('Accept')->andReturn(false);

        $request->shouldReceive('withAttribute')->withArgs(['Accept', 'application/json'])->andReturnSelf();

        $response = \Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('withHeader')->withArgs(['Content-Type', 'application/json']);

        $middleware(
            $request,
            $response,
            function ($request, $response) {
                return $response;
            }
        );
    }

    public function testGetBestFormatNoSerializerException()
    {
        $container = \Mockery::mock('Phapi\Contract\Di\Container');
        $middleware = new FormatNegotiation();
        $middleware->setContainer($container);

        $this->setExpectedException('Phapi\Exception\InternalServerError', 'No serializers seems to be configured');
        $middleware->getBestFormat('*/*', []);
    }
}
