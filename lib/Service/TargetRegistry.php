<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Service;

use OCA\SmartCommands\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Derives valid slash-command targets from the registered bot manifests
 * instead of a hardcoded bot list, so onboarding a new bot only requires
 * publishing a manifest (no app code change).
 */
class TargetRegistry {
	private const GENERIC_TARGET = 'bot';
	private const DEFAULT_BOT_CONFIG_KEY = 'default_bot_target';
	private const GROUP_DEFAULTS_CONFIG_KEY = 'group_default_bot_targets';
	private const GLOBAL_COMMANDS_CONFIG_KEY = 'global_commands';

	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private ManifestStore $manifestStore,
	) {
	}

	/**
	 * Lowercase target tokens accepted after a leading slash, including the
	 * generic "bot" alias.
	 *
	 * @return string[]
	 */
	public function targets(): array {
		$targets = [self::GENERIC_TARGET];
		foreach ($this->registeredBotIds() as $botId) {
			$targets[] = $botId;
		}

		return array_values(array_unique($targets));
	}

	/**
	 * Builds the message-match regex for the current targets: the generic
	 * alias plus every registered manifest id. The generic alias is always
	 * included so room-aware resolution can run even with no manifests.
	 */
	public function messagePattern(): string {
		$quoted = array_map(static fn (string $target): string => preg_quote($target, '/'), $this->targets());
		return '/^\/(?P<target>' . implode('|', $quoted) . ')(?:@[^\s]+)?(?:\s+[\s\S]*)?$/i';
	}

	public function isGenericTarget(string $target): bool {
		return strtolower($target) === self::GENERIC_TARGET;
	}

	/**
	 * Resolves a slash target to a concrete bot id. Explicit targets pass
	 * through unchanged; the generic alias resolves to the configured default.
	 * May return '' for the generic alias when no default is configured --
	 * room-aware resolution (see RoomBotLookup::resolveRoomBot) then takes over.
	 */
	public function resolveAlias(string $target, ?string $userId = null): string {
		if (!$this->isGenericTarget($target)) {
			return strtolower($target);
		}

		return $this->configuredDefault($userId);
	}

	/**
	 * Configured default target for the generic alias, by precedence:
	 * personal setting -> group default -> server default. Returns '' when
	 * nothing is configured; there is deliberately no hard-coded fallback.
	 */
	public function configuredDefault(?string $userId = null): string {
		$registered = $this->registeredBotIds();

		if ($userId !== null && $userId !== '') {
			$personal = strtolower(trim($this->config->getUserValue(
				$userId,
				Application::APP_ID,
				self::DEFAULT_BOT_CONFIG_KEY,
				'',
			)));
			if ($personal !== '' && in_array($personal, $registered, true)) {
				return $personal;
			}

			$groupChoice = $this->groupDefaultForUser($userId, $registered);
			if ($groupChoice !== null) {
				return $groupChoice;
			}
		}

		// Validate the server default the same way as the personal/group
		// choices: if the configured bot no longer has a manifest (e.g. it was
		// deleted), ignore the stale value and fall through to room-aware
		// resolution instead of routing the generic alias to a missing bot.
		$server = $this->serverDefault();
		return ($server !== '' && in_array($server, $registered, true)) ? $server : '';
	}

	/**
	 * @return array<string, string> groupId => botId (admin-managed)
	 */
	public function groupDefaults(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::GROUP_DEFAULTS_CONFIG_KEY, '{}');
		try {
			$map = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return [];
		}

		return is_array($map)
			? array_filter($map, static fn ($v, $k): bool => is_string($v) && is_string($k), ARRAY_FILTER_USE_BOTH)
			: [];
	}

	public function setGroupDefault(string $groupId, string $botId): void {
		$map = $this->groupDefaults();
		if ($botId === '') {
			unset($map[$groupId]);
		} else {
			$map[$groupId] = $botId;
		}
		$this->config->setAppValue(
			Application::APP_ID,
			self::GROUP_DEFAULTS_CONFIG_KEY,
			json_encode($map, JSON_THROW_ON_ERROR),
		);
	}

	/**
	 * First mapped group (sorted by group id, for determinism when a user is
	 * in several mapped groups) whose bot is still registered.
	 */
	private function groupDefaultForUser(string $userId, array $registered): ?string {
		$map = $this->groupDefaults();
		if ($map === []) {
			return null;
		}

		$user = $this->userManager->get($userId);
		if ($user === null) {
			return null;
		}

		$groupIds = $this->groupManager->getUserGroupIds($user);
		sort($groupIds);
		foreach ($groupIds as $groupId) {
			$botId = strtolower(trim((string)($map[$groupId] ?? '')));
			if ($botId !== '' && in_array($botId, $registered, true)) {
				return $botId;
			}
		}

		return null;
	}

	/**
	 * @return string[] lowercase, routing-safe bot ids that have a manifest
	 */
	public function registeredBotIds(): array {
		$ids = [];
		foreach ($this->manifestStore->all() as $manifest) {
			$id = strtolower(trim((string)($manifest['id'] ?? '')));
			if ($id === '' || preg_match('/^[a-z0-9_-]{1,64}$/', $id) !== 1) {
				continue;
			}
			// Skip manifests whose publishing account has been deleted: an
			// orphaned bot must not remain a routing target or picker entry.
			if (!$this->manifestOwnerExists($manifest)) {
				continue;
			}
			$ids[] = $id;
		}

		sort($ids);
		return array_values(array_unique($ids));
	}

	/**
	 * All registered manifests with their commands, for read-only display in
	 * the admin settings. Sorted by bot id.
	 *
	 * @return list<array{owner: string, id: string, name: string, stale: bool, commands: list<array{id: string, label: string, description: string, insert: string}>}>
	 */
	public function allManifests(): array {
		$manifests = [];
		foreach ($this->manifestStore->all() as $manifest) {
			// Preserve the original-case id: it is half of the storage key
			// (bot:<owner>:<id>) the admin delete reconstructs, so lowercasing
			// would break deletion for mixed-case account ids. Matching
			// elsewhere is already case-insensitive.
			$id = trim((string)($manifest['id'] ?? ''));
			if ($id === '') {
				continue;
			}
			$manifests[] = [
				'owner' => (string)($manifest['owner'] ?? ''),
				'id' => $id,
				'name' => (string)($manifest['name'] ?? $id),
				// Flag manifests whose publishing account no longer exists so the
				// admin view can surface them for cleanup. These are already
				// excluded from routing and the picker (see registeredBotIds and
				// ManifestController::registeredManifests).
				'stale' => !$this->manifestOwnerExists($manifest),
				'commands' => $this->shapeCommands($manifest),
			];
		}

		usort($manifests, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
		return $manifests;
	}

	/**
	 * Whether the account that published a manifest still exists. A deleted
	 * account leaves its manifest behind as an orphaned config row; treating it
	 * as non-existent keeps those commands out of routing and the picker, and
	 * lets the admin view flag them for one-click cleanup. A merely *disabled*
	 * account still exists, so its commands are deliberately preserved.
	 *
	 * @param array<string, mixed> $manifest
	 */
	public function manifestOwnerExists(array $manifest): bool {
		// owner == botId == account id by construction; fall back to the id for
		// legacy manifests written before the owner field existed.
		$owner = trim((string)($manifest['owner'] ?? ''));
		if ($owner === '') {
			$owner = trim((string)($manifest['id'] ?? ''));
		}
		return $owner !== '' && $this->userManager->userExists($owner);
	}

	/**
	 * Admin-authored, instance-wide commands. Unlike a bot manifest these are
	 * owned by no bot: they are shown to every user in the Smart Picker and are
	 * never room-filtered. Display-shaped like manifest commands.
	 *
	 * @return list<array{id: string, label: string, description: string, insert: string}>
	 */
	public function globalCommands(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::GLOBAL_COMMANDS_CONFIG_KEY, '[]');
		try {
			$decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return [];
		}

		return is_array($decoded) ? $this->shapeCommands(['commands' => $decoded]) : [];
	}

	/**
	 * Replaces the admin-authored global command list. An empty array clears it.
	 * Callers are responsible for normalizing first (see CommandList::normalize).
	 *
	 * @param list<array{id: string, label: string, description: string, insert: string}> $commands
	 */
	public function setGlobalCommands(array $commands): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::GLOBAL_COMMANDS_CONFIG_KEY,
			json_encode(array_values($commands), JSON_THROW_ON_ERROR),
		);
	}

	/**
	 * Deletes a published manifest by owner account and bot id (admin
	 * housekeeping for stale or decommissioned bots). False when none exists.
	 */
	public function deleteManifest(string $owner, string $botId): bool {
		return $this->manifestStore->delete($owner, $botId);
	}

	/**
	 * Normalizes a manifest's commands to the {id,label,description,insert}
	 * shape shared by the admin view and the personal Available-commands view.
	 *
	 * @param array<string, mixed> $manifest
	 * @return list<array{id: string, label: string, description: string, insert: string}>
	 */
	private function shapeCommands(array $manifest): array {
		$commands = [];
		foreach (is_array($manifest['commands'] ?? null) ? $manifest['commands'] : [] as $command) {
			if (!is_array($command)) {
				continue;
			}
			$commands[] = [
				'id' => (string)($command['id'] ?? ''),
				'label' => (string)($command['label'] ?? ''),
				'description' => (string)($command['description'] ?? ''),
				'insert' => (string)($command['insert'] ?? ''),
			];
		}

		return $commands;
	}

	/**
	 * Instance-wide default target (admin setting). Empty when unset; there is
	 * deliberately no hard-coded fallback, so an unconfigured generic alias
	 * relies on room-aware resolution (the single bot in the room) instead.
	 */
	private function serverDefault(): string {
		return strtolower(trim($this->config->getAppValue(
			Application::APP_ID,
			self::DEFAULT_BOT_CONFIG_KEY,
			'',
		)));
	}
}
