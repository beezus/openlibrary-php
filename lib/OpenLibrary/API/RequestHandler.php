<?php

namespace OpenLibrary\API;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * A request handler for Open Library API requests
 */
class RequestHandler
{
    private string $base_url;
    private string $version;
    private Client $client;

    /**
     * Instantiate a new RequestHandler
     */
    public function __construct()
    {
        $this->base_url = 'https://openlibrary.org';
        $this->version = '1.0.0';
        $this->client = new Client([
            'allow_redirects' => false
        ]);
    }

    /**
     * Set the base url for this request handler
     *
     * @param string $url The base url
     */
    public function setBaseUrl(string $url): void
    {
        $this->base_url = $url;
    }

    /**
     * Make a request with this request handler
     *
     * @param string $method one of GET, POST
     * @param string $path the path to hit
     * @param array $options the array of params
     *
     * @return \stdClass response object
     */
    public function request(string $method, string $path, array $options): \stdClass
    {
        // Ensure there are options
        $options = $options ?: [];
        $url = $this->base_url . $path;
        
        $requestOptions = [
            'headers' => [
                'User-Agent' => 'openlibrary.php/'.$this->version
            ],
            'query' => $options
        ];

        // Collapse Guzzle's errors to deal with at the Client level
        try {
            $response = $this->client->request($method, $url, $requestOptions);
        } catch (GuzzleException $e) {
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $response = $e->getResponse();
            } else {
                // Create a response-like object for other exceptions
                $obj = new \stdClass;
                $obj->status = 500;
                $obj->body = $e->getMessage();
                $obj->headers = [];
                return $obj;
            }
        }

        // Construct the object that the Client expects to see, and return it
        $obj = new \stdClass;
        $obj->status = $response->getStatusCode();
        $obj->body = (string)$response->getBody();
        $obj->headers = $response->getHeaders();
        
        return $obj;
    }
}
