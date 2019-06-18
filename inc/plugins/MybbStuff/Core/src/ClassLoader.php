<?php
declare(strict_types=1);

namespace MybbStuff\Core;

final class ClassLoader
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var array
     */
    private $nameSpacePrefixes;

    private function __construct()
    {
        $this->nameSpacePrefixes = [];
    }

    public static function getInstance(): self
    {
        if (is_null(static::$instance)) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    public function registerNamespace(string $nameSpacePrefix, string $basePath): void
    {
        if (substr($nameSpacePrefix, -1) !== '\\') {
            $nameSpacePrefix .= '\\';
        }

        if (substr($basePath, -1) !== '/') {
            $basePath .= '/';
        }

        $this->nameSpacePrefixes[$nameSpacePrefix] = $basePath;
    }

    public function resolve(string $class): void
    {
        foreach ($this->nameSpacePrefixes as $prefix => $basePath) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }

            $relative = substr($class, $len);

            $file = $basePath . str_replace('\\', '/', $relative) . '.php';

            if (file_exists($file)) {
                require $file;

                break;
            }
        }
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'resolve']);
    }
}