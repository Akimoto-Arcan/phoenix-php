<?php

namespace Phoenix;

class View
{
    private static $viewsPath = '';
    private static $layoutsPath = '';
    private static $shared = [];
    private static $sections = [];
    private static $sectionStack = [];
    private static $layout = null;

    public static function init(string $basePath = null): void
    {
        $base = $basePath ?: dirname(__DIR__);
        self::$viewsPath = $base . '/views';
        self::$layoutsPath = $base . '/views/layouts';
    }

    public static function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        self::$layout = $layout;
        self::$sections = [];
        self::$sectionStack = [];

        $file = self::resolvePath($view);
        if (!$file) {
            throw new \RuntimeException("View not found: {$view}");
        }

        $content = self::renderFile($file, array_merge(self::$shared, $data));

        $layoutName = self::$layout;
        self::$layout = null;

        if ($layoutName) {
            $layoutFile = self::$layoutsPath . '/' . $layoutName . '.php';
            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout not found: {$layoutName}");
            }

            $layoutData = array_merge(self::$shared, $data, [
                'content' => $content,
            ]);

            $content = self::renderFile($layoutFile, $layoutData);
        }

        return $content;
    }

    public static function section(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        if (empty(self::$sectionStack)) {
            return;
        }

        $name = array_pop(self::$sectionStack);
        self::$sections[$name] = ob_get_clean();
    }

    public static function yieldSection(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function extends(string $layout): void
    {
        self::$layout = $layout;
    }

    public static function partial(string $name, array $data = []): void
    {
        $paths = [
            self::$viewsPath . '/partials/' . $name . '.php',
            self::$viewsPath . '/components/' . $name . '.php',
            self::$viewsPath . '/' . str_replace('.', '/', $name) . '.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                extract(array_merge(self::$shared, $data));
                require $path;
                return;
            }
        }

        throw new \RuntimeException("Partial not found: {$name}");
    }

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    public static function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function viewExists(string $view): bool
    {
        return self::resolvePath($view) !== null;
    }

    private static function resolvePath(string $view): ?string
    {
        $relative = str_replace('.', '/', $view) . '.php';
        $path = self::$viewsPath . '/' . $relative;

        return file_exists($path) ? $path : null;
    }

    private static function renderFile(string $file, array $data): string
    {
        extract($data);
        ob_start();
        require $file;
        return ob_get_clean();
    }
}
