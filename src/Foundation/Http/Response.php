<?php

namespace Yivic\YivicKernelTheme\Foundation\Http;

class Response
{
    protected array $headers = [];
    protected int $status = 200;
    protected string $content = '';

    public function __construct(string $content = '', int $status = 200)
    {
        $this->content = $content;
        $this->status  = $status;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setStatusCode(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function sendHeaders(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}", true);
        }
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function send(): void
    {
        $this->sendHeaders();
        echo $this->content;
    }
}