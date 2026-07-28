<?php

declare(strict_types=1);

namespace MinifyX\Application;

final class BuildResult
{
    private string $content;
    private string $filename;
    private string $url;
    private string $path;
    private bool $fromCache;
    private bool $written;
    private bool $success;
    private string $error;
    private string $errorCode;
    private string $sourceFile;
    private string $phase;

    public function __construct(
        string $content = '',
        string $filename = '',
        string $url = '',
        string $path = '',
        bool $fromCache = false,
        bool $written = false,
        bool $success = false,
        string $error = '',
        string $errorCode = '',
        string $sourceFile = '',
        string $phase = ''
    ) {
        $this->content = $content;
        $this->filename = $filename;
        $this->url = $url;
        $this->path = $path;
        $this->fromCache = $fromCache;
        $this->written = $written;
        $this->success = $success;
        $this->error = $error;
        $this->errorCode = $errorCode;
        $this->sourceFile = $sourceFile;
        $this->phase = $phase;
    }

    public static function failure(
        string $error,
        string $errorCode = 'compile_failed',
        string $sourceFile = '',
        string $phase = 'compile'
    ): self {
        return new self('', '', '', '', false, false, false, $error, $errorCode, $sourceFile, $phase);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'filename' => $this->filename,
            'url' => $this->url,
            'path' => $this->path,
            'fromCache' => $this->fromCache,
            'written' => $this->written,
            'success' => $this->success,
            'error' => $this->error,
            'errorCode' => $this->errorCode,
            'sourceFile' => $this->sourceFile,
            'phase' => $this->phase,
        ];
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    public function isWritten(): bool
    {
        return $this->written;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getSourceFile(): string
    {
        return $this->sourceFile;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;

        return $clone;
    }

    public function withFilename(string $filename): self
    {
        $clone = clone $this;
        $clone->filename = $filename;

        return $clone;
    }

    public function withUrl(string $url): self
    {
        $clone = clone $this;
        $clone->url = $url;

        return $clone;
    }

    public function withPath(string $path): self
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }
}
