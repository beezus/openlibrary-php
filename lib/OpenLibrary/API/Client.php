<?php

namespace OpenLibrary\API;

/**
 * A client to access the Open Library API
 */
class Client
{
    private RequestHandler $requestHandler;

    /**
     * Create a new Client
     *
     * @param RequestHandler|null $requestHandler Custom request handler (optional)
     */
    public function __construct(?RequestHandler $requestHandler = null)
    {
        $this->requestHandler = $requestHandler ?? new RequestHandler();
    }

    /**
     * Retrieve RequestHandler instance
     *
     * @return RequestHandler
     */
    private function requestHandler(): RequestHandler
    {
        return $this->requestHandler;
    }

    /**
     * Get a book by its Open Library ID
     *
     * @param string $olid the OLID
     * @return array the response array
     */
    public function getBookByOLID(string $olid): array
    {
        return $this->getRequest(
            sprintf('/books/%s.json', $olid)
        );
    }

    /**
     * Get editions by International Standard Book Number
     *
     * @param string $isbn the ISBN
     * @param int $limit number of results per page
     * @param int $page page number (starting from 1)
     * @return array the response array with pagination information
     */
    public function getEditionsByISBN(string $isbn, int $limit = 20, int $page = 1): array
    {
        $this->validateISBN($isbn);
        
        $isbnType = (strlen($isbn) == 10) ? 'isbn_10' : 'isbn_13';
        
        // Calculate offset from page number
        $offset = ($page - 1) * $limit;

        $response = $this->getRequest(
            '/query.json',
            [
                'type' => '/type/edition',
                $isbnType => $isbn,
                'limit' => $limit,
                'offset' => $offset,
                '*' => ''
            ]
        );
        
        // Add pagination information
        return $this->addPaginationInfo($response, $limit, $page);
    }
    
    /**
     * Validate ISBN format
     *
     * @param string $isbn the ISBN to validate
     * @throws \InvalidArgumentException if ISBN is invalid
     */
    private function validateISBN(string $isbn): void
    {
        if (strlen($isbn) != 10 && strlen($isbn) != 13) {
            throw new \InvalidArgumentException('ISBN must be 10 or 13 characters.');
        }
    }

    /**
     * Get editions by Library of Congress Control Number
     *
     * @param string $lccn the LCCN
     * @param int $limit number of results per page
     * @param int $page page number (starting from 1)
     * @return array the response array with pagination information
     */
    public function getEditionsByLCCN(string $lccn, int $limit = 20, int $page = 1): array
    {
        // Calculate offset from page number
        $offset = ($page - 1) * $limit;
        
        $response = $this->getRequest(
            '/query.json',
            [
                'type' => '/type/edition',
                'lccn' => $lccn,
                'limit' => $limit,
                'offset' => $offset,
                '*' => ''
            ]
        );
        
        // Add pagination information
        return $this->addPaginationInfo($response, $limit, $page);
    }

    /**
     * Get editions by Online Computer Library Center number
     *
     * @param string $oclc the OCLC number
     * @param int $limit number of results per page
     * @param int $page page number (starting from 1)
     * @return array the response array with pagination information
     */
    public function getEditionsByOCLC(string $oclc, int $limit = 20, int $page = 1): array
    {
        // Calculate offset from page number
        $offset = ($page - 1) * $limit;
        
        $response = $this->getRequest(
            '/query.json',
            [
                'type' => '/type/edition',
                'oclc_numbers' => $oclc,
                'limit' => $limit,
                'offset' => $offset,
                '*' => ''
            ]
        );
        
        // Add pagination information
        return $this->addPaginationInfo($response, $limit, $page);
    }

    /**
     * Get an author by Open Library author key
     *
     * @param string $authorKey the Open Library author key
     * @return array the response array
     */
    public function getAuthorByKey(string $authorKey): array
    {
        return $this->getRequest(
            sprintf('/authors/%s.json', $authorKey)
        );
    }

    /**
     * Get editions of a work
     *
     * @param string $workKey the Open Library work key
     * @param int $limit number of results per page (0 for all available)
     * @param int $page page number (starting from 1)
     * @return array the response array with pagination information
     */
    public function getEditionsOfWork(string $workKey, int $limit = 20, int $page = 1): array
    {
        $path = sprintf('/works/%s/editions.json', $workKey);
        
        // Calculate offset from page number (pages start at 1)
        $offset = ($page - 1) * $limit;
        
        // Get a single page with the specified limit and offset
        $response = $this->getRequest(
            $path,
            [
                'limit' => $limit,
                'offset' => $offset,
                '*' => ''
            ]
        );
        
        // Add pagination information
        return $this->addPaginationInfo($response, $limit, $page);
    }

    /**
     * Make a GET request to the given endpoint and return the response
     *
     * @param string $path the path to call on
     * @param array  $options the options to call with
     *
     * @return array the response object (parsed)
     */
    public function getRequest(string $path, array $options = []): array
    {
        $response = $this->makeRequest('GET', $path, $options);

        return $this->parseResponse($response);
    }
 
    /**
     * Parse a response and return an appropriate result
     *
     * @param \stdClass $response the response from the server
     *
     * @throws RequestException
     * @return array the response data
     */
    private function parseResponse(\stdClass $response): array
    {
        $response->json = json_decode($response->body);

        if ($response->status < 400) {
            // Normalize the response format
            return $this->normalizeResponse($response->json);
        } else {
            throw new RequestException($response);
        }
    }
    
    /**
     * Normalize different response formats
     *
     * @param mixed $data The parsed JSON response
     * @return array Normalized response
     */
    private function normalizeResponse($data): array
    {
        // If it's already a list-type response with entries (from Content API)
        if (is_object($data) && isset($data->entries)) {
            return [
                'total' => $data->size ?? count($data->entries),
                'items' => $data->entries,
                'links' => $data->links ?? null
            ];
        }
        
        // If it's a simple array (from Query API)
        if (is_array($data)) {
            return [
                'total' => count($data),
                'items' => $data,
                'links' => null
            ];
        }
        
        // If it's a single object (not a list)
        if (is_object($data)) {
            return (array)$data;
        }
        
        // Fallback
        return is_array($data) ? $data : [$data];
    }
    
    /**
     * Add pagination information to a response
     *
     * @param array $response The normalized response
     * @param int $limit Items per page
     * @param int $currentPage Current page number
     * @return array Response with pagination information
     */
    private function addPaginationInfo(array $response, int $limit, int $currentPage): array
    {
        $totalItems = isset($response['total']) ? (int)$response['total'] : 0;
        $totalPages = $limit > 0 ? max(1, ceil($totalItems / $limit)) : 1;
        
        $response['pagination'] = [
            'current_page' => $currentPage,
            'per_page' => $limit,
            'total_items' => $totalItems,
            'total_pages' => $totalPages
        ];
        
        return $response;
    }

    /**
     * Make a request to the given endpoint and return the response
     *
     * @param string $method the method to call: GET, POST
     * @param string $path the path to call on
     * @param array  $options the options to call with
     *
     * @return \stdClass the response object (not parsed)
     */
    private function makeRequest(string $method, string $path, array $options): \stdClass
    {
        return $this->requestHandler()->request($method, $path, $options);
    }

}
