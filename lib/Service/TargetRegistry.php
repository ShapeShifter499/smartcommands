<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Service;

use OCA\SmartCommands\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Derives valid slash-command targets from the registered agent manifests
 * instead of a hardcoded agent list, so onboarding a new agent only requires
 * publishing a manifest (no app code change).
 */
class TargetRegistry {
	private const GENERIC_TARGET = 'agent';
	private const DEFAULT_AGENT_CONFIG_KEY = 'default_agent_target';
	private const GROUP_DEFAULTS_CONFIG_KEY = 'group_default_agent_targets';

	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
	) {
	}

	/**
	 * Lowercase target tokens accepted after a leading slash, including the
	 * generic "agent" alias.
	 *
	 * @return string[]
	 */
	public function targets(): array {
		$targets = [self::GENERIC_TARGET];
		foreach ($this->registeredAgentIds() as $agentId) {
			$targets[] = $agentId;
		}

		return array_values(array_unique($targets));
	}

	/**
	 * Builds the message-match regex for the current targets, equivalent in
	 * shape to the previous hardcoded pattern.
	 */
	public function messagePattern(): ?string {
		$targets = $this->targets();
		if (count($targets) === 1 && $this->registeredAgentIds() === []) {
			// Only the generic alias exists but it has nothing to resolve to;
			// without manifests there is nothing to bridge.
			$defaultTarget = $this->defaultAgentTarget();
			if ($defaultTarget === '') {
				return null;
			}
			$targets[] = $defaultTarget;
		}

		$quoted = array_map(static fn (string $target): string => preg_quote($target, '/'), $targets);
		return '/^\/(?P<target>' . implode('|', $quoted) . ')(?:@[^\s]+)?(?:\s+[\s\S]*)?$/i';
	}

	/**
	 * Resolves the generic "agent" alias for a sender. Precedence:
	 * personal setting -> group default -> server default -> built-in.
	 */
	public function resolveAlias(string $target, ?string $userId = null): string {
		$target = strtolower($target);
		if ($target !== self::GENERIC_TARGET) {
			return $target;
		}

		$registered = $this->registeredAgentIds();

		if ($userId !== null && $userId !== '') {
			$personal = strtolower(trim($this->config->getUserValue(
				$userId,
				Application::APP_ID,
				self::DEFAULT_AGENT_CONFIG_KEY,
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

		return $this->defaultAgentTarget();
	}

	/**
	 * @return array<string, string> groupId => agentId (admin-managed)
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

	public function setGroupDefault(string $groupId, string $agentId): void {
		$map = $this->groupDefaults();
		if ($agentId === '') {
			unset($map[$groupId]);
		} else {
			$map[$groupId] = $agentId;
		}
		$this->config->setAppValue(
			Application::APP_ID,
			self::GROUP_DEFAULTS_CONFIG_KEY,
			json_encode($map, JSON_THROW_ON_ERROR),
		);
	}

	/**
	 * First mapped group (sorted by group id, for determinism when a user is
	 * in several mapped groups) whose agent is still registered.
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
			$agentId = strtolower(trim((string)($map[$groupId] ?? '')));
			if ($agentId !== '' && in_array($agentId, $registered, true)) {
				return $agentId;
			}
		}

		return null;
	}

	/**
	 * @return string[] lowercase agent ids with a registered manifest
	 */
	public function registeredAgentIds(): array {
		$ids = [];
		foreach ($this->config->getAppKeys(Application::APP_ID) as $key) {
			if (!str_starts_with($key, 'agent:')) {
				continue;
			}
			$raw = $this->config->getAppValue(Application::APP_ID, $key, '');
			if ($raw === '') {
				continue;
			}

			try {
				$manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				continue;
			}

			$id = is_array($manifest) ? strtolower(trim((string)($manifest['id'] ?? ''))) : '';
			if ($id !== '' && preg_match('/^[a-z0-9_-]{1,64}$/', $id) === 1) {
				$ids[] = $id;
			}
		}

		sort($ids);
		return array_values(array_unique($ids));
	}

	private function defaultAgentTarget(): string {
		return strtolower(trim($this->config->getAppValue(
			Application::APP_ID,
			self::DEFAULT_AGENT_CONFIG_KEY,
			'nymble',
		)));
	}
}
