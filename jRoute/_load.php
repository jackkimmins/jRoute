<?php

class RouterOptions {
    public $debugMode = false;
    public $urlPrefix = null;
    public $cspLevel = 'none'; // Available levels: none, basic, medium, heavy, extreme

    public function __construct($debugMode = false, $urlPrefix = null, $cspLevel = 'none') {
        $this->debugMode = $debugMode;
        $this->urlPrefix = $urlPrefix;
        $this->cspLevel = $cspLevel;
    }
}

class jRoute {
    private $routes = [];
    private $dirMappings = [];
    private $options;
    private $cspPolicies = [
        'none' => "",
        'basic' => "default-src 'self';",
        'medium' => "default-src 'self'; script-src 'self'; object-src 'none';",
        'heavy' => "default-src 'self'; script-src 'self'; object-src 'none'; style-src 'self'; img-src 'self';",
        'extreme' => "default-src 'self'; script-src 'self'; object-src 'none'; style-src 'self'; img-src 'self'; media-src 'none'; frame-src 'none'; font-src 'self'; connect-src 'self';",
    ];

    public function __construct(RouterOptions $options) {
        $this->options = $options;
    }

    private function OutputError($msg) {
        echo "<br /><b>jRoute</b>: <code>" . $msg . "</code><br />";
    }

    // Method for adding a new route
    public function Route(array $methods, string $pattern, $callback, array $requiredRole = null)
    {
        foreach ($methods as $method) {
            $this->routes[strtoupper($method)][$pattern] = ['callback' => $callback, 'role' => $requiredRole];
        }
    }

    private function WriteRoutesToFile() {
        $filePath = __DIR__ . '/jRoute_RoutesDebug.txt';
        $fileContent = "Current Routes (Debug Mode):\n\n";

        foreach ($this->routes as $method => $routesByMethod) {
            foreach ($routesByMethod as $pattern => $routeInfo) {
                $valid = is_callable($routeInfo['callback']) ? 'Valid' : 'Invalid';
                $fileContent .= "Method: $method, Pattern: $pattern, Valid: $valid\n";
                if (!empty($routeInfo['role'])) {
                    $roles = is_array($routeInfo['role']) ? implode(', ', $routeInfo['role']) : $routeInfo['role'];
                    $fileContent .= "Roles: $roles\n";
                }
                $fileContent .= "\n";
            }
        }

        file_put_contents($filePath, $fileContent);
    }

    // Method for passing a route to a file if it exists
    public function PassRoute(string $baseUrl, string $dir, array $requiredRole = null)
    {
        $this->Route(['get'], $baseUrl . '{path}', function($path) use ($dir) {
            $file = __DIR__ . $dir . $path;
            if (file_exists($file)) {
                return readfile($file);
            } else {
                $this->RequireErrorPage('404');
                return;
            }
        }, $requiredRole);
    }    

    // Method for adding a directory mapping
    public function AddDir(string $webPath, string $rootDir, array $requiredRole = null)
    {
        if (!($rootDir = realpath($rootDir))) {
            $this->OutputError("Directory does not exist: " . $rootDir);
            return;
        }
    
        $this->dirMappings[] = ['webPath' => $webPath, 'rootDir' => $rootDir, 'role' => $requiredRole];
    }

    private function SetSecureHeaders() {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: no-referrer-when-downgrade");

        $csp = $this->cspPolicies[$this->options->cspLevel];
        if ($csp !== "") header("Content-Security-Policy: " . $csp);
    }

    // Main method for dispatching the incoming request to the correct route
    public function Dispatch(string $method, string $uri) {
        @session_start();

        if ($this->options->debugMode) $this->WriteRoutesToFile();

        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            $this->RequireErrorPage('405');
            return;
        }

        if ($this->options->urlPrefix !== null) $uri = substr($uri, strlen($this->options->urlPrefix));
        if ($this->options->debugMode) $this->OutputError($method . ' ' . $uri);

        foreach ($this->routes[$method] as $route => $routeInfo) {
            $routePattern = '#^' . preg_replace('/\{([^}]+)\}/', '([^/]+)', $route) . '$#';
            if (preg_match($routePattern, $uri, $matches)) {
                array_shift($matches);

                if ($routeInfo['role'] !== null && (!isset($_SESSION['role']) || in_array($_SESSION['role'], $routeInfo['role']) === false)) {
                    $this->RequireErrorPage('403');
                    return;
                }

                $this->SetSecureHeaders();

                if (is_callable($routeInfo['callback'])) {
                    return call_user_func_array($routeInfo['callback'], $matches);
                } elseif (is_string($routeInfo['callback'])) {
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
                // Check if user has required role
                if ($mapping['role'] !== null && (!isset($_SESSION['role']) || in_array($_SESSION['role'], $mapping['role']) === false)) {
                    $this->RequireErrorPage('403'); // Forbidden
                    return;
                }

                // Check if file exists and is not a directory
                $filePath = $mapping['rootDir'] . substr($uri, strlen($mapping['webPath']));
                if (file_exists($filePath) && !is_dir($filePath)) return readfile($filePath);
            }
        }

        // If route or file was not found, require 404 page
        $this->RequireErrorPage('404');
    }

    private function RequireErrorPage($errorCode) {
        $reqUrl = $_SERVER['REQUEST_URI'];
        if ($this->options->urlPrefix !== null) $reqUrl = substr($reqUrl, strlen($this->options->urlPrefix));
        $reqUrl = $_SERVER['REQUEST_METHOD'] . ' ' . $reqUrl;

        require dirname(__FILE__) . "/errorPage.php";
        ErrorPage::Display($errorCode, $reqUrl);
    }
}