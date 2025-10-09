<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Transport;

use Evenement\EventEmitter;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Exception\TransportException;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Message;
use PhpMcp\Schema\JsonRpc\Parser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

final class StdioTransport extends EventEmitter implements ServerTransportInterface
{
    private const string CLIENT_ID = 'stdio';

    /**
     * Read chunk size for LLM messages
     * Larger chunks reduce system calls for typical LLM message sizes
     */
    private const int READ_CHUNK_SIZE = 1_048_576; // 1MB

    /**
     * Maximum message size from LLM clients
     * LLMs typically don't send messages larger than 1MB
     */
    private const int MAX_MESSAGE_SIZE = 2_097_152; // 2MB

    protected string $buffer = '';
    protected bool $closing = false;
    protected bool $listening = false;

    /**
     * @param resource $input
     * @param resource $output
     */
    public function __construct(
        private $input = \STDIN,
        private $output = \STDOUT,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function listen(): void
    {
        if ($this->listening) {
            throw new TransportException('Stdio transport is already listening.');
        }

        if ($this->closing) {
            throw new TransportException('Cannot listen, transport is closing/closed.');
        }

        $this->logger->info('StdioTransport is listening for messages on STDIN...');

        $this->listening = true;
        $this->closing = false;

        $this->emit('ready');
        $this->emit('client_connected', [self::CLIENT_ID]);

        while (!\feof($this->input)) {
            // Read in chunks to handle large messages
            // This solves the macOS 8KB fgets() limitation
            $chunk = \fread($this->input, self::READ_CHUNK_SIZE);

            if (false === $chunk) {
                $this->logger->error('Failed to read from STDIN');
                break;
            }

            if (empty($chunk)) {
                // No data available, continue
                continue;
            }

            $this->buffer .= $chunk;

            // Check for buffer overflow protection
            if (\strlen($this->buffer) > self::MAX_MESSAGE_SIZE) {
                $this->logger->error('Message size exceeded maximum allowed size', [
                    'buffer_size' => \strlen($this->buffer),
                    'max_size' => self::MAX_MESSAGE_SIZE,
                ]);

                // Send error and clear buffer
                $error = Error::forInternalError(
                    \sprintf(
                        'Message size exceeded maximum allowed size of %d bytes',
                        self::MAX_MESSAGE_SIZE,
                    ),
                );
                $this->sendMessage($error, self::CLIENT_ID);
                $this->buffer = '';
                continue;
            }

            $this->processBuffer();
        }

        $this->logger->info('StdioTransport finished listening.');
    }

    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        $deferred = new Deferred();

        try {
            $json = \json_encode(
                $message,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );

            $this->logger->debug('Sending message', [
                'type' => $message::class,
                'size' => \strlen($json),
            ]);

            \fwrite($this->output, $json . "\n");
            \fflush($this->output); // Ensure message is sent immediately

            $deferred->resolve();
        } catch (\JsonException $e) {
            $this->logger->error('JSON encoding failed', [
                'error' => $e->getMessage(),
                'message_type' => $message::class,
            ]);

            // Attempt to send error response
            try {
                $error = Error::forInternalError(
                    "Failed to encode message: " . $e->getMessage(),
                );
                $errorJson = \json_encode($error, JSON_THROW_ON_ERROR);
                \fwrite($this->output, $errorJson . "\n");
                \fflush($this->output);
            } catch (\Throwable $errorException) {
                $this->logger->critical('Unable to send error response', [
                    'exception' => $errorException->getMessage(),
                ]);
            }

            $deferred->reject($e);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send message', [
                'exception' => $e->getMessage(),
            ]);
            $deferred->reject($e);
        }

        return $deferred->promise();
    }

    public function close(): void
    {
        if (\is_resource($this->input)) {
            \fclose($this->input);
        }

        if (\is_resource($this->output)) {
            \fclose($this->output);
        }

        $this->removeAllListeners();
    }

    /**
     * Processes the internal buffer to find complete lines/frames.
     */
    private function processBuffer(): void
    {
        while (\str_contains($this->buffer, "\n")) {
            $pos = (int) \strpos($this->buffer, "\n");
            $line = \substr($this->buffer, 0, $pos);
            $this->buffer = \substr($this->buffer, $pos + 1);

            $trimmedLine = \trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            $this->logger->debug('Processing message', [
                'size' => \strlen($trimmedLine),
            ]);

            try {
                $message = Parser::parse($trimmedLine);
                $this->emit('message', [$message, self::CLIENT_ID]);
            } catch (\Throwable $e) {
                $this->logger->error('Error parsing message', [
                    'exception' => $e->getMessage(),
                    'message_preview' => \substr($trimmedLine, 0, 100) . '...',
                ]);

                $error = Error::forParseError(
                    "Invalid JSON: " . $e->getMessage(),
                );
                $this->sendMessage($error, self::CLIENT_ID);
            }
        }
    }
}
