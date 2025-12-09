<?php

namespace Yivic\YivicKernelTheme\Foundation\Http;

class Request
{
    public function all(): array
    {
        return $_REQUEST;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        return $key ? ($_GET[$key] ?? $default) : $_GET;
    }

    public function post(string $key = null, mixed $default = null): mixed
    {
        return $key ? ($_POST[$key] ?? $default) : $_POST;
    }

    public function file(string $name): mixed
    {
        return $_FILES[$name] ?? null;
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getPathInfo(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post($key, $this->query($key, $default));
    }
}