<?php declare(strict_types=1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommand;
use function Amp\resolve;
use Room11\Jeeves\System\PluginManager;

class Alias implements BuiltInCommand
{
    private $chatClient;
    private $aliasStorage;
    private $adminStorage;
    private $builtInCommandManager;
    private $pluginManager;

    public function __construct(
        ChatClient $chatClient,
        CommandAliasStorage $aliasStorage,
        AdminStorage $adminStorage,
        BuiltInActionManager $builtInCommandManager,
        PluginManager $pluginManager
    ) {
        $this->chatClient = $chatClient;
        $this->aliasStorage = $aliasStorage;
        $this->adminStorage = $adminStorage;
        $this->builtInCommandManager = $builtInCommandManager;
        $this->pluginManager = $pluginManager;
    }

    private function addAlias(CommandMessage $command)
    {
        $room = $command->getRoom();
        $aliasCommand = $command->getParameter(0);
        $mapping = implode(' ', $command->getParameters(1));

        if (in_array($aliasCommand, $this->builtInCommandManager->getRegisteredCommands())) {
            return $this->chatClient->postReply($command, "Command '{$aliasCommand}' is built in and cannot be altered");
        }

        if ($this->pluginManager->isCommandMappedForRoom($room, $aliasCommand)) {
            return $this->chatClient->postReply($command, "Command '{$aliasCommand}' is already mapped. Use `!!command list` to display the currently mapped commands.");
        }

        if (yield $this->aliasStorage->exists($room, $aliasCommand)) {
            return $this->chatClient->postReply($command, "Alias '!!{$aliasCommand}' already exists.");
        }

        yield $this->aliasStorage->add($room, $aliasCommand, $mapping);

        return $this->chatClient->postMessage($room, "Command '!!{$aliasCommand}' aliased to '!!{$mapping}'");
    }

    private function removeAlias(CommandMessage $command): \Generator
    {
        $aliasCommand = $command->getParameter(0);

        if (!yield $this->aliasStorage->exists($command->getRoom(), $aliasCommand)) {
            return $this->chatClient->postMessage($command->getRoom(), "Alias '!!{$aliasCommand}' is not currently mapped");
        }

        yield $this->aliasStorage->remove($command->getRoom(), $aliasCommand);

        return $this->chatClient->postMessage($command->getRoom(), "Alias '!!{$aliasCommand}' removed");
    }

    /**
     * Handle a command message
     *
     * @param CommandMessage $command
     * @return Promise
     */
    public function handleCommand(CommandMessage $command): Promise
    {
        return resolve(function() use($command) {
            if (!yield $command->getRoom()->isApproved()) {
                return null;
            }

            if (!yield $this->adminStorage->isAdmin($command->getRoom(), $command->getUserId())) {
                return $this->chatClient->postReply($command, "I'm sorry Dave, I'm afraid I can't do that");
            }

            return $command->getCommandName() === 'alias'
                ? yield from $this->addAlias($command)
                : yield from $this->removeAlias($command);
        });
    }

    /**
     * Get a list of specific commands handled by this plugin
     *
     * @return string[]
     */
    public function getCommandNames(): array
    {
        return ['alias', 'unalias'];
    }
}