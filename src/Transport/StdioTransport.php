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
            $line = \fgets($this->input);
            if (false === $line) {
                break;
            }

            $this->buffer .= $line;
            $this->processBuffer();
        }

        $this->logger->info('StdioTransport finished listening.');
    }

    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        $json = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $deferred = new Deferred();

        try {
            \fwrite($this->output, $json . "\n");
            return $deferred->promise();
        } catch (\Throwable $e) {
            return $deferred->promise();
        }
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
            $pos = (int)\strpos($this->buffer, "\n");
            $line = \substr($this->buffer, 0, $pos);
            $this->buffer = \substr($this->buffer, $pos + 1);

            $trimmedLine = \trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            try {
                $message = Parser::parse($trimmedLine);
            } catch (\Throwable $e) {
                $this->logger->error('Error parsing message', ['exception' => $e]);
                $error = Error::forParseError("Invalid JSON: " . $e->getMessage());
                $this->sendMessage($error, 'stdio');
                continue;
            }

            $this->emit('message', [$message, 'stdio']);
        }
    }
}
