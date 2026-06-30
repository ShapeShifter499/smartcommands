<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Controller;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\CommandList;
use OCA\SmartCommands\Service\ManifestStore;
use OCA\SmartCommands\Service\RoomBotLookup;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class ManifestController extends Controller {
	public function __construct(
		IRequest $request,
		private ManifestStore $manifestStore,
		private IUserSession $userSession,
		private RoomBotLookup $roomBotLookup,
		private TargetRegistry $targetRegistry,
		private IL10N $l10n,
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

		// Global commands are admin-authored and instance-wide: they belong to
		// no bot, so they are never room-filtered and always lead the list.
		$globals = $this->targetRegistry->globalCommands();
		if ($globals !== []) {
			array_unshift($manifests, [
				'id' => '',
				'name' => $this->l10n->t('Global commands'),
				'owner' => '',
				'commands' => $globals,
			]);
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
		if (!CommandList::isValidId($botId)) {
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
			'commands' => CommandList::normalize($commands),
		];
		if (count($manifest['commands']) === 0) {
			return $this->error('Manifest must include at least one valid command.', Http::STATUS_BAD_REQUEST);
		}

		$this->manifestStore->save($userId, $botId, $manifest);

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

		if (!CommandList::isValidId($botId)) {
			return $this->error('Bot id must contain only letters, numbers, underscores, and hyphens.', Http::STATUS_BAD_REQUEST);
		}

		$userId = $user->getUID();
		if ($botId !== $userId) {
			return $this->error('Bot id must match the authenticated user id.', Http::STATUS_FORBIDDEN);
		}

		$this->manifestStore->delete($userId, $botId);

		return new JSONResponse(['deleted' => true]);
	}

	private function registeredManifests(): array {
		// Orphaned manifests (publishing account deleted) never route, so the
		// picker must not offer them either. liveManifests() is the single
		// source of truth for that exclusion (shared with the personal
		// Available-commands list); the controller only re-sorts by name.
		$manifests = $this->targetRegistry->liveManifests();
		usort($manifests, static fn (array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));
		return $manifests;
	}

	private function error(string $message, int $status): JSONResponse {
		return new JSONResponse(['error' => $message], $status);
	}
}
