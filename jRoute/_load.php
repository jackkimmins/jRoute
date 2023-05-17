<?php

class jRoute
{
    private $routes = [];
    private $urlPrefix = null;
    private $debugMode = false;

    private function OutputError($msg)
    {
        echo "<br /><b>jRoute</b>: <code>" . $msg . "</code><br />";
    }

    public function __construct($urlPrefix = null, $debugMode = false)
    {
        $this->urlPrefix = $urlPrefix;
        $this->debugMode = $debugMode;
    }

    public function Route($methods, $pattern, $callback)
    {
        foreach ($methods as $method) {
            $method = strtoupper($method);
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }
            $this->routes[$method][$pattern] = $callback;
        }
    }

    public function PassRoute($baseUrl, $dir)
    {
        $this->Route(['get'], $baseUrl . '{path}', function($path) use ($dir) {
            $file = __DIR__ . $dir . $path;
            if (file_exists($file)) {
                return readfile($file);
            } else {
                $_GET['error_uri'] = 'GET ' . $baseUrl . $path;
                require dirname(__FILE__) . '/errorPages/404.php';
            }
        });
    }

    public function Dispatch($method, $uri)
    {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            // Handle method not supported
            return 'Method not supported.';
        }

        if ($this->urlPrefix != null) $uri = substr($uri, strlen($this->urlPrefix));
        if ($this->debugMode) $this->OutputError($method . ' ' . $uri);

        foreach ($this->routes[$method] as $route => $callback)
        {
            $routePattern = '#^' . preg_replace('/\{([^}]+)\}/', '([^/]+)', $route) . '$#';
            if (preg_match($routePattern, $uri, $matches))
            {
                array_shift($matches);

                if (is_callable($callback)) return call_user_func_array($callback, $matches);

                elseif (is_string($callback)) {
                    preg_match_all('/\{([^}]+)\}/', $route, $paramNames);
                    $paramNames = $paramNames[1];
                    $params = array_combine($paramNames, $matches);
                    $_GET = array_merge($_GET, $params);
                    require $callback;
                    return;
                }
            }
        }

        // Handle not found
        $_GET['error_uri'] = $method . ' ' . $uri;
        require dirname(__FILE__) . '/errorPages/404.php';
    }
}