<?php

namespace ApiMapper;

use ApiMapper\EventListeners\ListenerInterface;
use ApiMapper\Providers\ProviderInterface;

use Buzz\Message\RequestInterface;
use Buzz\Browser;
use Exception;

class ApiMapper
{
    /**
     * Holds the browser (Buzz) instance we will use to perform http calls
     *
     * @var Buzz\Browser
     */
    private $browser = null;

    /**
     * Holds the base URL where Api call will be done
     *
     * @var string
     */
    private $baseUrl = null;

    /**
     * Holds event listeners
     *
     * @var array(ListenerInterface)
     */
    private $eventListeners = array();

    /**
     * Holds route parameter providers
     *
     * @var array(ProviderInterface)
     */
    private $routeParameters = array(); 

    /**
     * Holds query parameter providers
     *
     * @var array(ProviderInterface)
     */
    private $queryParameters = array();

    /**
     * Holds header parameters
     *
     * @var array(ProviderInterface)
     */
    private $headerProviders = array();

    public function __construct(Browser $browser, $baseUrl = null)
    {
        $this->browser = $browser;
        $this->baseUrl = $baseUrl ?: "http://localhost";
    }

    /**
     * Set the route parameter providers collection
     *
     * @param array $routeParameters 
     */
    public function setRouteParameters(array $routeParameters)
    {
        $this->routeParameters = $routeParameters;
    }

    /**
     * Add a route parameter provider to the current collection
     *
     * @param string $name
     * @param ProviderInterface $routeParameter 
     */
    public function addRouteParameter($name, ProviderInterface $routeParameter)
    {
        $this->routeParameters[$name] = $routeParameter;
    }

    /**
     * Get the route parameter provider collection
     *
     * @return array(ProviderInterface)
     */
    public function getRouteParameters()
    {
        return $this->routeParameters;
    }

    /**
     * Set the query parameter providers collection
     *
     * @param array $queryParameters 
     */
    public function setQueryParameters(array $queryParameters)
    {
        $this->queryParameters = $queryParameters;
    }

    /**
     * Add a query parameter provider to the current collection
     *
     * @param string $name
     * @param ProviderInterface $queryParameter 
     */
    public function addQueryParameter($name, ProviderInterface $queryParameter)
    {
        $this->queryParameters[$name] = $queryParameter;
    }

    /**
     * Get the query parameter provider collection
     *
     * @return array(ProviderInterface)
     */
    public function getQueryParameters()
    {
        return $this->queryParameters;
    }

    /**
     * Set the event listeners collection
     *
     * @param array $eventListeners 
     */
    public function setEventListeners(array $eventListeners)
    {
        $this->eventListeners = $eventListeners;
    }

    /**
     * Add an event listener to the current collection
     *
     * @param ListenerInterface $eventListener 
     */
    public function addEventListener(ListenerInterface $eventListener)
    {
        $this->eventListeners[] = $eventListener;
    }

    /**
     * Get the event listeners collection
     *
     * @return array(ListenerInterface)
     */
    public function getEventListeners()
    {
        return $this->eventListeners;
    }

    /**
     * Set the header providers collection
     *
     * @param array $eventListeners
     */
    public function setHeaderProviders(array $headerProviders)
    {
        $this->headerProviders = $headerProviders;
    }

    /**
     * Add an header providers to the current collection
     *
     * @param ProviderInterface $eventListener
     */
    public function addHeaderProvider(ProviderInterface $headerProvider)
    {
        $this->headerProviders[] = $headerProvider;
    }

    /**
     * Get the header providers collection
     *
     * @return array(ProviderInterface)
     */
    public function getHeaderProviders()
    {
        return $this->headerProviders;
    }

    /**
     * Select a base URL to use for the given call
     * 
     * @return string
     */
    public function getBaseUrl()
    {
        if (is_array($this->baseUrl))
            return $this->baseUrl[mt_rand(0, count($this->baseUrl) - 1)];

        return $this->baseUrl;
    }

    /**
     * Build an URL
     *
     * @param string $originalRoute
     * @param array $parameters
     * @return string 
     */
    private function buildUrl($originalRoute, $parameters)
    {
        // First, we try to find placeholders
        preg_match_all("~\{([^\}]+)\}~", $originalRoute, $results);

        // Did we find any placeholders ?
        $route = $originalRoute;
        if (!empty($results) && isset($results[0])) {
            // We replace placeholders with $parameters
            foreach ($results[0] as $result) {
                if (!isset($parameters[$result])) {
                    // If a parameter is not found, we look if a custom parameter provider exists
                    $notFound = false;
                    if (!isset($this->routeParameters[$result])) {
                        $notFound = true;
                    } else {
                        $parameter = $this->routeParameters[$result]->lookup($originalRoute);
                        if ($parameter === false)
                            $notFound = true;
                    }

                    if ($notFound)
                        throw new Exception("Route placeholder not found: $result in $originalRoute");

                    $parameters[$result] = $parameter;
                }

                $route = str_replace($result, $parameters[$result], $route);
                unset($parameters[$result]);
            }
        }

        // Add query parameter providers
        foreach ($this->queryParameters as $parameterName => $parameter) {
            $parameter = $parameter->lookup($originalRoute);
            if ($parameter !== false)
                $parameters[$parameterName] = $parameter;
        }

        // Build the query fields with the remaining parameters
        $query = http_build_query($parameters);

        // Build the final URL
        return http_build_url(
                $this->getBaseUrl(),
                array(
                    "path" => $route,
                    "query" => $query
                ),
                HTTP_URL_JOIN_PATH | HTTP_URL_JOIN_QUERY
            );
    }

    /**
     * Dispatch data to all event listeners
     *
     * @param array $data 
     */
    private function dispatch($data)
    {
        foreach ($this->eventListeners as $eventListener)
            $eventListener->handle($data);
    }

    /**
     * Return true if the given method is safe (GET or HEAD)
     * 
     * @param string $method
     * @return bool
     */
    private static function isSafeMethod($method)
    {
        return in_array(strtoupper($method), array(
            RequestInterface::METHOD_GET,
            RequestInterface::METHOD_HEAD
        ));
    }

    /**
     * Perform an HTTP call
     * 
     * $arguments is made of two arguments:
     * - first: route (Example: "{token}/ticket/list/{root}")
     * - second: parameters (Example: array("{root}" => "12", "key1" => "value2"), optionnal)
     *   => {token}/ticket/list/12?key1=value2
     *
     * @param string $method
     * @param array $arguments
     * @return array
     */
    public function __call($method, $arguments)
    {
        // Check for arguments
        if (empty($arguments))
            throw new Exception("No arguments have been passed to ApiMapper::$method()");

        // Extracts arguments (Note $parameters and $fields are optionnal)
        $route = array_shift($arguments);
        $parameters = empty($arguments) ? array() : array_shift($arguments);

        // Fill route placeholders, and append query fields
        $url = $this->buildUrl($route, $parameters);

        // Load headers
        $headers = array();
        foreach ($this->headerProviders as $headerProvider) {
            $header = $headerProvider->lookup($route);
            if ($header !== false)
                $headers[] = $header;
        }

        // Perform the call
        if (static::isSafeMethod($method)) {
            $response = $this->browser->call($url, $method, $headers);
        } else {
            $fields = empty($arguments) ? array() : array_shift($arguments);
            $response = $this->browser->submit($url, $fields, $method, $headers);
        }

        // Parse the content
        $content = array(
            "method" => strtoupper($method),
            "route" => $route,
            "url" => $url,
            "response" => $response,
            "parameters" => $parameters,
            "json" => json_decode($response->getContent(), true)
        );

        // Dispatch content to event listeners
        $this->dispatch($content);

        // Return the content
        return $content;
    }
}
