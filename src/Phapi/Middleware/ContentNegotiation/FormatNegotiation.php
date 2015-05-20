<?php


namespace Phapi\Middleware\ContentNegotiation;

use Phapi\Contract\Di\Container;
use Phapi\Contract\Middleware\Middleware;
use Phapi\Exception\InternalServerError;
use Phapi\Exception\NotAcceptable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Format negotiation
 *
 * @category Phapi
 * @package  Phapi\Middleware\ContentNegotiation
 * @author   Peter Ahinko <peter@ahinko.se>
 * @license  MIT (http://opensource.org/licenses/MIT)
 * @link     https://github.com/phapi/middleware-content-negotiation
 * @link     http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html Reference to the HTTP/1.1 specification
 */
class FormatNegotiation implements Middleware
{

    /**
     * Dependency injection container
     *
     * @var Container
     */
    private $container;

    /**
     * Set dependency injection container
     *
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Invoking the middleware
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface $response
     * @throws InternalServerError
     * @throws NotAcceptable
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        // Make sure we have serializers
        if (!isset($this->container['acceptTypes'])) {
            throw new InternalServerError('No serializers seems to be registered');
        }

        // Get priorities
        $supported = $this->container['acceptTypes'];

        // Make sure we have what we need to do the negotiation
        if ($request->hasHeader('Accept')) {
            // Get header
            $accept = $request->getHeaderLine('Accept');

            // Negotiate
            $best = $this->getBestFormat($accept, $supported);

            // If an Accept header field is present, and if the server cannot send a response
            // which is acceptable according to the combined Accept field value, then the server
            // SHOULD send a 406 (not acceptable) response.
            if ($best === null) {
                // We must set the mime type before throwing the exception so that the correct serializer
                // is triggered to serialize the error message.
                $this->container['latestRequest'] = $request->withAttribute('Accept', $supported[0]);

                throw new NotAcceptable(
                    'Can not send a response which is acceptable according to the Accept header. '.
                    'Supported mime types are: '. implode(', ', $supported)
                );
            }
        } else {
            // If no Accept header field is present, then it is assumed that the client accepts all media types.
            $best = $this->acceptsAnything(1, $supported);
        }

        // Save result
        $request = $request->withAttribute('Accept', $best['value']);

        // Check if we have a charset configured
        $charset = '';
        if (isset($this->container['charset'])) {
            // Add charset to the content type header
            $charset = ';charset='. $this->container['charset'];
        }
        // Set response content type header
        $response = $response->withHeader('Content-Type', $best['value'] . $charset);

        // Set extra params
        if (isset($best['parameters'])) {
            // Set parameters array as attribute
            $request = $request->withAttribute('AcceptParameters', $best['parameters']);
        }

        // Call next middleware
        return $next($request, $response, $next);
    }

    /**
     * Get the best format based on the accept header and the list of supported mime types.
     *
     * @param $accept
     * @param $supported
     * @return mixed|null
     */
    public function getBestFormat($accept, $supported)
    {
        // Parse the accept header
        $parts = $this->parseHeader($accept);

        // Sort the array based on
        $parts = $this->sortParts($parts);

        // Loop through the array
        foreach ($parts as $part) {
            // Check if mime type is $supported
            if (in_array($part['value'], $supported)) {
                // Return the mime type
                return $part;
            }

            // Match for example image/*
            if (substr($part['value'], -2) === '/*') {
                // Get the type
                $range = substr($part['value'], 0, -2);

                // Look if type is found in $supported
                foreach ($supported as $support) {
                    if (strpos($support, $range, 0) === 0) {
                        $part['value'] = $support;
                        return $part;
                    }
                }
            }

            // Check if accepts anything
            if ($part['value'] === '*/*') {
                return $this->acceptsAnything($part['quality'], $supported);
            }
        }

        // Return null if no match is found
        return null;
    }

    /**
     * The client accepts any mime type, look for the first registered
     * supported mime type. These are registered by the serializers. If we for
     * some reason don't get a hit there: throw an error
     *
     * @param int $quality
     * @param array $supported
     * @return array
     * @throws InternalServerError
     */
    private function acceptsAnything($quality = 1, array $supported = [])
    {
        // Return the first supported accept mime type Note: acceptTypes are registered by the serializers.
        if (!empty($supported)) {
            return [ 'value' => $supported[0], 'quality' => $quality];
        }

        // Seems like no serializers are registered, this should never happen.
        // But if it does, throw an error.
        throw new InternalServerError('No serializers seems to be configured.');
    }

    /**
     * Parse string (usually the accept header) and divide it in to
     * an array of parts that includes the mime type, quality and other
     * params.
     *
     * @param string $accept
     * @return array
     */
    public function parseHeader($accept)
    {
        // Array of supported mime types and their params
        $supported = [];

        // Separate the header into parts based on the comma (,) char
        $ranges = array_map('trim', explode(',', $accept));

        $index = 0;
        // Loop through all ranges
        foreach ($ranges as $range) {
            // Separate each part based on the semi colon (;) so that we get
            // the mime type and maybe some parameters
            $parts = array_map('trim', explode(';', $range));

            // Get and unset the mime type
            $mimeType = $parts[0];
            unset($parts[0]);

            // Set default quality to 1 since the HTTP/1.1 specification says that if no
            // quality is provided it defaults to 1
            $support = [ 'value' => $mimeType, 'quality' => (float) 1 ];

            // Loop through the parts
            foreach ($parts as $part) {
                // Save all params
                $param = explode('=', $part);
                // Make sure we exploded the string
                if (isset($param[1])) {
                    // Save param
                    if ($param[0] === 'q') {
                        $support['quality'] = (float) $param[1];
                    } else {
                        $support['parameters'][$param[0]] = $param[1];
                    }
                }
            }

            $support['index'] = $index;

            // Save the result to the array with all supported mime types
            $supported[] = $support;

            $index++;
        }

        return $supported;
    }

    /**
     * Sort an array of parts of an accept header. Sorts based on the
     * quality parameter and makes sure that catch all (* / *) and
     * for example text/* has a lower priority.
     *
     * @param array $parts
     * @return array
     */
    public function sortParts(array $parts = [])
    {
        // Sort based on the "quality" param
        uasort($parts, function ($one, $two) {
            if ($one['quality'] > $two['quality']) {
                return -1;
            }

            if ($one['quality'] < $two['quality']) {
                return 1;
            }

            // If q is same

            // */* accept all should always be valued lower
            if (substr($one['value'], -3) === '*/*') {
                return 1;
            }
            // /* accept all of specific type should be valued lower
            if (substr($one['value'], -2) === '/*') {
                return 1;
            }

            // Let the array index decide position
            return $one['index'] < $two['index'] ? -1 : 1;
        });

        return $parts;
    }
}
