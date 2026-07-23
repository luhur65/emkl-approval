<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        //
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );

        $response->setHeader(
            'X-Frame-Options',
            'SAMEORIGIN'
        );

        $response->setHeader(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->setHeader(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        $response->setHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );
    }
}
