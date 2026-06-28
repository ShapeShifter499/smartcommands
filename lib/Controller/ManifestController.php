<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Controller;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\RoomBotLookup;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class ManifestController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
		private RoomBotLookup $roomBotLookup,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * When a Talk room token is supplied, only bots whose webhook bot is
	 * enabled in that conversation are returned, so the picker does not offer
	 * commands that would go nowhere.
	 */
	public function commands(string $room = ''): JSONResponse {
		$manifests = $this->registeredManifests();

		$room = trim($room);
		$filtered = false;
		if ($room !== '' && preg_match('/^[A-Za-z0-9]{1,64}$/', $room) === 1) {
			$bots = $this->roomBotLookup->webhookBotsForRoom($room);
			$manifests = array_values(array_filter(
				$manifests,
				function (array $manifest) use ($bots): bool {
					$botId = strtolower((string)($manifest['id'] ?? ''));
					if ($botId === '') {
						return false;
					}
					foreach ($bots as $bot) {
						if ($this->roomBotLookup->botMatchesTarget($bot['name'], $botId)) {
							return true;
						}
					}
					return false;
				},
			));
			$filtered = true;
		}

		return new JSONResponse([
			'bots' => $manifests,
			'filteredByRoom' => $filtered ? $room : null,
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function upsertBot(string $botId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->error('Authentication required.', Http::STATUS_UNAUTHORIZED);
		}

		$botId = trim($botId);
		if (!$this->isValidId($botId)) {
			return $this->error('Bot id must contain only letters, numbers, underscores, and hyphens.', Http::STATUS_BAD_REQUEST);
		}

		$userId = $user->getUID();
		if ($botId !== $userId) {
			return $this->error('Bot id must match the authenticated user id.', Http::STATUS_FORBIDDEN);
		}

		$params = $this->request->getParams();
		$name = trim((string)($params['name'] ?? $botId));
		$commands = $params['commands'] ?? null;
		if (!is_array($commands)) {
			return $this->error('Manifest must include a commands array.', Http::STATUS_BAD_REQUEST);
		}

		$manifest = [
			'id' => $botId,
			'name' => $name !== '' ? $name : $botId,
			'owner' => $userId,
			'updatedAt' => time(),
			'commands' => $this->normalizeCommands($commands),
		];
		if (count($manifest['commands']) === 0) {
			return $this->error('Manifest must include at least one valid command.', Http::STATUS_BAD_REQUEST);
		}

		$key = $this->manifestKey($userId, $botId);
		$this->config->setAppValue(Application::APP_ID, $key, json_encode($manifest, JSON_THROW_ON_ERROR));

		return new JSONResponse(['bot' => $manifest], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteBot(string $botId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->error('Authentication required.', Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->isValidId($botId)) {
			return $this->error('Bot id must contain only letters, numbers, underscores, and hyphens.', Http::STATUS_BAD_REQUEST);
		}

		$userId = $user->getUID();
		if ($botId !== $userId) {
			return $this->error('Bot id must match the authenticated user id.', Http::STATUS_FORBIDDEN);
		}

		$this->config->deleteAppValue(Application::APP_ID, $this->manifestKey($userId, $botId));

		return new JSONResponse(['deleted' => true]);
	}

	private function registeredManifests(): array {
		$manifests = [];
		foreach ($this->config->getAppKeys(Application::APP_ID) as $key) {
			if (!str_starts_with($key, 'bot:')) {
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

			if (is_array($manifest) && isset($manifest['id'], $manifest['name'], $manifest['commands']) && is_array($manifest['commands'])) {
				$manifests[] = $manifest;
			}
		}

		usort($manifests, static fn (array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));
		return $manifests;
	}

	private function normalizeCommands(array $commands): array {
		$normalized = [];
		foreach ($commands as $command) {
			if (!is_array($command)) {
				continue;
			}

			$id = trim((string)($command['id'] ?? ''));
			$insert = (string)($command['insert'] ?? '');
			if (!$this->isValidId($id) || trim($insert) === '') {
				continue;
			}

			$normalized[] = [
				'id' => $id,
				'label' => substr(trim((string)($command['label'] ?? $id)), 0, 80),
				'description' => substr(trim((string)($command['description'] ?? '')), 0, 240),
				'insert' => substr($insert, 0, 1000),
			];
		}

		return array_slice($normalized, 0, 100);
	}

	private function isValidId(string $id): bool {
		return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1;
	}

	private function manifestKey(string $userId, string $botId): string {
		return 'bot:' . rawurlencode($userId) . ':' . rawurlencode($botId);
	}

	private function error(string $message, int $status): JSONResponse {
		return new JSONResponse(['error' => $message], $status);
	}
}
