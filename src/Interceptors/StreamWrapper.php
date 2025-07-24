<?php

namespace OutboundIQ\Interceptors;

use OutboundIQ\Client;

class StreamWrapper
{
    private static Client $client;
    public $context;
    private $position;
    private $content = '';
    private float $startTime;
    private string $url;
    private array $headers = [];
    private $varname = '';
    private static array $originalWrappers = [];

    public static function register(Client $client): void
    {
        self::$client = $client;
        
        // Store and unregister original wrappers
        foreach (['http', 'https'] as $protocol) {
            if (in_array($protocol, stream_get_wrappers())) {
                self::$originalWrappers[$protocol] = true;
                @stream_wrapper_unregister($protocol);
            }
        }
        
        // Register our wrapper
        stream_wrapper_register('http', self::class);
        stream_wrapper_register('https', self::class);
    }

    public static function unregister(): void
    {
        // Restore original wrappers
        foreach (['http', 'https'] as $protocol) {
            if (in_array($protocol, stream_get_wrappers())) {
                @stream_wrapper_unregister($protocol);
            }
            if (isset(self::$originalWrappers[$protocol])) {
                stream_wrapper_restore($protocol);
            }
        }
    }

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $this->startTime = microtime(true);
        $this->url = $path;
        $this->position = 0;

        // Initialize curl
        $ch = curl_init();
        
        // Get context options
        $contextOptions = [];
        if ($this->context) {
            $contextOptions = stream_context_get_options($this->context);
        }
        
        // Set curl options
        $curlOptions = [
            CURLOPT_URL => $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'OutboundIQ/1.0'
        ];

        // Apply context options to curl
        if (!empty($contextOptions['http'])) {
            $http = $contextOptions['http'];
            
            // Method
            if (!empty($http['method'])) {
                $curlOptions[CURLOPT_CUSTOMREQUEST] = $http['method'];
            }
            
            // Headers
            if (!empty($http['header'])) {
                $headers = is_array($http['header']) ? $http['header'] : explode("\r\n", $http['header']);
                $curlOptions[CURLOPT_HTTPHEADER] = $headers;
            }
            
            // Content/Body
            if (!empty($http['content'])) {
                $curlOptions[CURLOPT_POSTFIELDS] = $http['content'];
            }
        }

        curl_setopt_array($ch, $curlOptions);

        // Execute request
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            $errorCode = curl_errno($ch);
            $statusCode = 0;
            $errorType = 'unknown_error';

            // Map curl error codes to error types
            switch ($errorCode) {
                case CURLE_OPERATION_TIMEOUTED:
                    $errorType = 'timeout';
                    break;
                case CURLE_COULDNT_CONNECT:
                    $errorType = 'connection_error';
                    break;
                case CURLE_COULDNT_RESOLVE_HOST:
                    $errorType = 'dns_error';
                    break;
                case CURLE_SSL_CONNECT_ERROR:
                    $errorType = 'ssl_error';
                    break;
            }

            // Track the failed request
            self::$client->trackApiCall(
                url: $path,
                method: $contextOptions['http']['method'] ?? 'GET',
                duration: (microtime(true) - $this->startTime) * 1000,
                statusCode: $statusCode,
                requestHeaders: $curlOptions[CURLOPT_HTTPHEADER] ?? [],
                requestBody: $curlOptions[CURLOPT_POSTFIELDS] ?? null,
                responseHeaders: [],
                responseBody: null,
                request_type: 'file_get_contents',
                error_message: $error,
                error_type: $errorType
            );

            curl_close($ch);
            trigger_error("OutboundIQ StreamWrapper Error: $error", E_USER_WARNING);
            return false;
        }

        // Split headers and body
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerStr = substr($response, 0, $headerSize);
        $this->content = substr($response, $headerSize);
        
        // Parse headers
        $this->headers = array_filter(explode("\r\n", $headerStr));
        
        // Get status code for tracking
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Track the API call
        self::$client->trackApiCall(
            url: $path,
            method: $contextOptions['http']['method'] ?? 'GET',
            duration: (microtime(true) - $this->startTime) * 1000,
            statusCode: $statusCode,
            requestHeaders: $curlOptions[CURLOPT_HTTPHEADER] ?? [],
            requestBody: $curlOptions[CURLOPT_POSTFIELDS] ?? null,
            responseHeaders: $this->parseHeaders($this->headers),
            responseBody: $this->content,
            request_type: 'file_get_contents'
        );

        curl_close($ch);
        return true;
    }

    public function stream_read($count)
    {
        if ($this->position >= strlen($this->content)) {
            return false;
        }
        
        $ret = substr($this->content, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_write($data)
    {
        return 0;
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_eof()
    {
        return $this->position >= strlen($this->content);
    }

    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < strlen($this->content) && $offset >= 0) {
                    $this->position = $offset;
                    return true;
                }
                return false;
            case SEEK_CUR:
                if ($offset >= 0) {
                    $this->position += $offset;
                    return true;
                }
                return false;
            case SEEK_END:
                if (strlen($this->content) + $offset >= 0) {
                    $this->position = strlen($this->content) + $offset;
                    return true;
                }
                return false;
        }
        return false;
    }

    public function stream_stat()
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0444,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => strlen($this->content),
            'atime' => time(),
            'mtime' => time(),
            'ctime' => time(),
            'blksize' => -1,
            'blocks' => -1
        ];
    }

    public function url_stat($path, $flags)
    {
        return $this->stream_stat();
    }

    private function parseHeaders(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                continue;
            }
            
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $parsed[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $parsed;
    }
} 