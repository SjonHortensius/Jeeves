<?php

namespace Room11\Jeeves\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Plugin;

class Xkcd implements Plugin {
    use CommandOnlyPlugin;

    const NOT_FOUND_COMIC = 'https://xkcd.com/1334/';

    private $chatClient;

    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient) {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getResult(Command $message): \Generator {
        $uri = "https://www.google.com/search?q=site:xkcd.com+intitle%3A\"xkcd%3A+" . urlencode(implode(' ', $message->getParameters()));

        /** @var Response $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            yield from $this->chatClient->postMessage(
                "Useless error message here so debugging this is harder than needed."
            );

            return;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($response->getBody());
        libxml_use_internal_errors($internalErrors);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' g ')]/h3/a");

        if (!$nodes->length) {
            yield from $this->chatClient->postMessage(self::NOT_FOUND_COMIC);

            return;
        }

        foreach ($nodes as $node) {
            if (preg_match('~^/url\?q=(https://xkcd\.com/\d+/)~', $node->getAttribute('href'), $matches)) {
                yield from $this->chatClient->postMessage($matches[1]);

                return;
            }
        }

        yield from $this->chatClient->postMessage(self::NOT_FOUND_COMIC);
    }

    /**
     * Handle a command message
     *
     * @param Command $command
     * @return \Generator
     */
    public function handleCommand(Command $command): \Generator
    {
        if (!$command->getParameters()) {
            return;
        }

        yield from $this->getResult($command);
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getHandledCommands(): array
    {
        return ['xkcd'];
    }
}
