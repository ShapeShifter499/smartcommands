<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Service/CommandList.php';

use OCA\SmartCommands\Service\CommandList;

$commands = [];
for ($index = 0; $index < CommandList::MAX_COMMANDS + 20; $index++) {
	$commands[] = [
		'id' => 'command-' . $index,
		'label' => 'Command ' . $index,
		'description' => 'Test command ' . $index,
		'insert' => '/aurel command-' . $index,
	];
}

$normalized = CommandList::normalize($commands);
if (count($normalized) !== CommandList::MAX_COMMANDS) {
	fwrite(STDERR, sprintf(
		"Expected %d commands, got %d.\n",
		CommandList::MAX_COMMANDS,
		count($normalized),
	));
	exit(1);
}

if (CommandList::MAX_COMMANDS < 122) {
	fwrite(STDERR, "The manifest cap must fit Hermes's complete 122-entry menu.\n");
	exit(1);
}

if (
	$normalized[0]['id'] !== 'command-0'
	|| $normalized[CommandList::MAX_COMMANDS - 1]['id'] !== 'command-' . (CommandList::MAX_COMMANDS - 1)
) {
	fwrite(STDERR, "Normalization must retain the first MAX_COMMANDS valid entries.\n");
	exit(1);
}

echo "CommandList cap test: OK (" . count($normalized) . ")\n";