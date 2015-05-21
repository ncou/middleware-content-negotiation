# Content Negotiation Middleware
The Content Negotiation Middleware does exactly what the name indicates. It takes the <code>Accept</code> header and parses it, matches it against the list of supported mime types (registered by serializers) and finally sets the proper <code>Content-Type</code> header on the response object. It's also possible to get the negotiated mime type and parameters (if included) from the request object:

```php
<?php
/*
 * Get the response content type header that has the negotiated header.
 *
 * Returns the header value including charset.
 * Example: application/json;charset=utf-8
 */
$mimeType = $response->getHeaderLine('Content-Type');

// Get the negotiated mime type:
$mimeType = $request->getAttribute('Accept');

// Get parameters included in the accept header
$acceptParameters = $request->getAttribute('Accept-Parameters'); // returns an array.
```

## Exceptions
The middleware will throw a <code>406 NotAcceptable</code> if the requested mime type isn't supported. An <code>500 InternalServerError</code> is thrown if no serializers are found.

## Phapi
This middleware is a Phapi package used by the [Phapi Framework](https://github.com/phapi/phapi). The middleware are also [PSR-7](https://github.com/php-fig/http-message) compliant and implements the [Phapi Middleware Contract](https://github.com/phapi/contract).

## License
Content Negotiation Middleware is licensed under the MIT License - see the [license.md](https://github.com/phapi/middleware-content-negotiation/blob/master/license.md) file for details

## Contribute
Contribution, bug fixes etc are [always welcome](https://github.com/phapi/middleware-content-negotiation/issues/new).
