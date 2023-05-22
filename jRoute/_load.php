<?php

class jRoute
{
    private $routes = [];
    private $dirMappings = [];
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

    public function Route(array $methods, string $pattern, $callback, $requiredRole = null)
    {
        foreach ($methods as $method) {
            $this->routes[strtoupper($method)][$pattern] = ['callback' => $callback, 'role' => $requiredRole];
        }
    }    

    public function PassRoute(string $baseUrl, string $dir, $requiredRole = null)
    {
        $this->Route(['get'], $baseUrl . '{path}', function($path) use ($dir) {
            $file = __DIR__ . $dir . $path;
            if (file_exists($file)) {
                return readfile($file);
            } else {
                $_GET['error_uri'] = 'GET ' . $baseUrl . $path;
                require dirname(__FILE__) . '/errorPages/404.php';
                return;
            }
        }, $requiredRole);
    }    

    public function AddDir(string $webPath, string $rootDir, $requiredRole = null)
    {
        if (!($rootDir = realpath($rootDir))) {
            $this->OutputError("Directory does not exist: " . $rootDir);
            return;
        }
    
        $this->dirMappings[] = ['webPath' => $webPath, 'rootDir' => $rootDir, 'role' => $requiredRole];
    }

    public function Dispatch(string $method, string $uri)
    {
        @session_start();

        $method = strtoupper($method);
        if (!isset($this->routes[$method])) return 'Method not supported.';

        if ($this->urlPrefix != null) $uri = substr($uri, strlen($this->urlPrefix));
        if ($this->debugMode) $this->OutputError($method . ' ' . $uri);

        foreach ($this->routes[$method] as $route => $routeInfo)
        {
            $routePattern = '#^' . preg_replace('/\{([^}]+)\}/', '([^/]+)', $route) . '$#';
            if (preg_match($routePattern, $uri, $matches))
            {
                array_shift($matches);

                if ($routeInfo['role'] !== null && (!isset($_SESSION['role']) || $_SESSION['role'] !== $routeInfo['role'])) {
                    $_GET['error_uri'] = $method . ' ' . $uri;
                    require dirname(__FILE__) . '/errorPages/403.php';
                    return;
                }

                if (is_callable($routeInfo['callback'])) return call_user_func_array($routeInfo['callback'], $matches);

                elseif (is_string($routeInfo['callback'])) {
                    preg_match_all('/\{([^}]+)\}/', $route, $paramNames);
                    $paramNames = $paramNames[1];
                    $params = array_combine($paramNames, $matches);
                    $_GET = array_merge($_GET, $params);
                    require $routeInfo['callback'];
                    return;
                }
            }
        }

        // If route was not found, check directory mappings
        foreach ($this->dirMappings as $mapping) {
            if (strpos($uri, $mapping['webPath']) === 0) {
                if ($mapping['role'] !== null && (!isset($_SESSION['role']) || $_SESSION['role'] !== $mapping['role'])) {
                    $_GET['error_uri'] = $method . ' ' . $uri;
                    require dirname(__FILE__) . '/errorPages/403.php';
                    return;
                }

                $filePath = $mapping['rootDir'] . substr($uri, strlen($mapping['webPath']));
                if (file_exists($filePath) && !is_dir($filePath)) return readfile($filePath);
            }
        }

        // Handle not found
        $_GET['error_uri'] = $method . ' ' . $uri;
        require dirname(__FILE__) . '/errorPages/404.php';
    }
}