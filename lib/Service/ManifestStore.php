<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Service;

use OCA\SmartCommands\AppInfo\Application;
use OCP\IConfig;

/**
 * Single source of truth for how command manifests are stored: the app-config
 * key layout and the decode path. Every reader/writer (TargetRegistry,
 * ManifestController) goes through here so the key format and JSON handling
 * can never drift apart.
 *
 * A manifest is stored as JSON under the app key "bot:<owner>:<botId>", where
 * owner == botId == the publishing user's id (a bot may only publish under its
 * own account). The decoded form has at least an 'id'; typically also 'name',
 * 'owner', 'updatedAt' and a 'commands' array.
 */
class ManifestStore {
	/** @var null|list<array<string, mixed>> request-lifetime memoization of all() */
	private ?array $cache = null;

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * Canonical storage key. The same encoding must be used to write and to
	 * read/delete, so it lives in exactly one place.
	 */
	public function key(string $owner, string $botId): string {
		return 'bot:' . rawurlencode($owner) . ':' . rawurlencode($botId);
	}

	/**
	 * All stored manifests as decoded arrays (valid JSON, array, non-empty id),
	 * memoized for the request. Callers apply their own additional filtering.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array {
		if ($this->cache !== null) {
			return $this->cache;
		}

		$manifests = [];
		foreach ($this->config->getAppKeys(Application::APP_ID) as $key) {
			if (!str_starts_with($key, 'bot:')) {
				continue;
			}
			$raw = $this->config->getAppValue(Application::APP_ID, $key, '');
			if ($raw === '') {
				continue;
			}

			$manifest = $this->decode($raw);
			if ($manifest === null || trim((string)($manifest['id'] ?? '')) === '') {
				continue;
			}

			$manifests[] = $manifest;
		}

		return $this->cache = $manifests;
	}

	/**
	 * Single manifest by owner + bot id, or null when absent/invalid.
	 *
	 * @return null|array<string, mixed>
	 */
	public function get(string $owner, string $botId): ?array {
		$raw = $this->config->getAppValue(Application::APP_ID, $this->key($owner, $botId), '');
		return $raw === '' ? null : $this->decode($raw);
	}

	public function save(string $owner, string $botId, array $manifest): void {
		$this->config->setAppValue(
			Application::APP_ID,
			$this->key($owner, $botId),
			json_encode($manifest, JSON_THROW_ON_ERROR),
		);
		$this->cache = null;
	}

	public function delete(string $owner, string $botId): bool {
		$key = $this->key($owner, $botId);
		if ($this->config->getAppValue(Application::APP_ID, $key, '') === '') {
			return false;
		}
		$this->config->deleteAppValue(Application::APP_ID, $key);
		$this->cache = null;
		return true;
	}

	/**
	 * @return null|array<string, mixed>
	 */
	private function decode(string $raw): ?array {
		try {
			$manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}

		return is_array($manifest) ? $manifest : null;
	}
}
