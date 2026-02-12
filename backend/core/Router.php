<?php
/**
 * API Router
 * 
 * Routes HTTP requests to appropriate endpoint handlers
 */

class Router {
    private $basePath;
    private $requestMethod;
    private $requestPath;
    private $endpoints = [];
    private $pathParams = [];
    private static $instance = null;
    
    public function __construct() {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Auto-detect base path (everything before and including /api)
        if (strpos($this->requestPath, '/api') !== false) {
            $this->basePath = substr($this->requestPath, 0, strpos($this->requestPath, '/api') + 4);
        } else {
            $this->basePath = '';
        }
        
        // Remove base path
        if ($this->basePath && strpos($this->requestPath, $this->basePath) === 0) {
            $this->requestPath = substr($this->requestPath, strlen($this->basePath));
        }
        
        // Remove trailing slash
        $this->requestPath = rtrim($this->requestPath, '/') ?: '/';
        
        // Store singleton instance
        self::$instance = $this;
    }
    
    /**
     * Register endpoint
     */
    public function add($method, $path, $handler) {
        if (!isset($this->endpoints[$path])) {
            $this->endpoints[$path] = [];
        }
        $this->endpoints[$path][$method] = $handler;
    }
    
    /**
     * Route request to handler
     */
    public function dispatch() {
        // Debug: Log the request
        error_log("Router: {$this->requestMethod} {$this->requestPath}");
        error_log("Available endpoints: " . json_encode(array_keys($this->endpoints)));
        
        // Try exact match
        if (isset($this->endpoints[$this->requestPath][$this->requestMethod])) {
            error_log("Exact match found for {$this->requestPath}");
            $handler = $this->endpoints[$this->requestPath][$this->requestMethod];
            call_user_func($handler);
            return;
        }
        
        // Try wildcard patterns
        foreach ($this->endpoints as $path => $methods) {
            if ($this->matchPath($path, $this->requestPath)) {
                error_log("Wildcard match found: pattern={$path}, request={$this->requestPath}");
                if (isset($methods[$this->requestMethod])) {
                    // Extract path parameters
                    $this->extractPathParams($path, $this->requestPath);
                    
                    $handler = $methods[$this->requestMethod];
                    call_user_func($handler);
                    return;
                }
            }
        }
        
        // Not found
        error_log("No endpoint match found for {$this->requestPath}");
        Response::notFound("Endpoint not found: {$this->requestPath}");
    }
    
    /**
     * Extract path parameters like :id, :item_id from URL
     */
    private function extractPathParams($pattern, $path) {
        // Split pattern and path into segments
        $patternSegments = array_values(array_filter(explode('/', $pattern)));
        $pathSegments = array_values(array_filter(explode('/', $path)));
        
        foreach ($patternSegments as $i => $segment) {
            if (strpos($segment, ':') === 0) {
                $paramName = substr($segment, 1); // Remove the colon
                if (isset($pathSegments[$i])) {
                    $this->pathParams[$paramName] = $pathSegments[$i];
                }
            }
        }
    }
    
    /**
     * Match path with wildcard support
     * e.g., /products/:id matches /products/123
     * /sales/draft/:id/items matches /sales/draft/1/items
     */
    private function matchPath($pattern, $path) {
        // First escape the pattern for regex, but protect the placeholders
        // Replace :id and similar placeholders with a marker
        $marker = '___PARAM_MARKER___';
        $pattern = preg_replace('/:[\w]+/', $marker, $pattern);
        
        // Now escape for regex
        $pattern = preg_quote($pattern, '#');
        
        // Replace marker with digit pattern
        $pattern = str_replace(preg_quote($marker, '#'), '[0-9]+', $pattern);
        
        return preg_match('#^' . $pattern . '$#', $path);
    }
    
    /**
     * Get path parameter (static access)
     */
    public static function getParam($name) {
        return $_GET[$name] ?? null;
    }
    
    /**
     * Get path ID from URL like /endpoint/123
     * For routes like /sales/draft/:id/items, returns the draft ID (first numeric segment)
     */
    public static function getIdFromPath($position = 0) {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = array_filter(explode('/', $path));
        $numericParts = [];
        
        // Find all numeric segments (potential IDs)
        foreach ($parts as $part) {
            if (is_numeric($part)) {
                $numericParts[] = $part;
            }
        }
        
        return $numericParts[$position] ?? (end($numericParts) ?: end($parts));
    }
    
    /**
     * Get a specific path parameter from the current instance
     */
    public static function getPathParam($name) {
        if (self::$instance) {
            return self::$instance->pathParams[$name] ?? null;
        }
        return null;
    }
    
    /**
     * Get JSON body
     */
    public static function getBody() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}
?>
