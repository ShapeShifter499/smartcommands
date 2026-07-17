<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Service;

/**
 * Shared validation/normalization for an author-supplied command list. Every
 * place that accepts commands -- a bot's own manifest and the admin-authored
 * global commands -- goes through here, so the id rules, field caps and list
 * size limit can never drift apart between them.
 */
class CommandList {
	/** Protective per-manifest cap; Nextcloud Talk itself has no 100-command limit. */
	public const MAX_COMMANDS = 256;

	/** A command id (or bot id): letters, numbers, underscore, hyphen, 1-64 chars. */
	public static function isValidId(string $id): bool {
		return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1;
	}

	/**
	 * Coerces an arbitrary decoded array into the canonical
	 * {id,label,description,insert} command shape: invalid entries (bad id or
	 * empty insert) are dropped, field lengths and the list size are capped.
	 *
	 * @return list<array{id: string, label: string, description: string, insert: string}>
	 */
	public static function normalize(array $commands): array {
		$normalized = [];
		foreach ($commands as $command) {
			if (!is_array($command)) {
				continue;
			}

			$id = trim((string)($command['id'] ?? ''));
			$insert = (string)($command['insert'] ?? '');
			if (!self::isValidId($id) || trim($insert) === '') {
				continue;
			}

			$normalized[] = [
				'id' => $id,
				'label' => substr(trim((string)($command['label'] ?? $id)), 0, 80),
				'description' => substr(trim((string)($command['description'] ?? '')), 0, 240),
				'insert' => substr($insert, 0, 1000),
			];
		}

		return array_slice($normalized, 0, self::MAX_COMMANDS);
	}
}
