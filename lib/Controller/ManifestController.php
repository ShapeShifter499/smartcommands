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
	 * commands that would go nowhere. The response then also resolves the
	 * generic /bot alias for the requesting user in that room, so the picker
	 * can show where /bot would go.
	 */
	public function commands(string $room = ''): JSONResponse {
		$manifests = $this->registeredManifests();

		$room = trim($room);
		$filtered = false;
		$generic = null;
		$genericAmbiguous = false;
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

			$userId = $this->userSession->getUser()?->getUID();
			[$generic, $genericAmbiguous] = $this->resolveGeneric($room, $userId);
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
			'generic' => $generic,
			'genericAmbiguous' => $genericAmbiguous,
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Resolves the generic /bot alias for a sender in a room. Talk delivers
	 * /bot messages to every webhook bot in the room; this is the shared
	 * arbiter that lets each bot answer only when it is the resolved target,
	 * so the picker's "here, /bot goes to X" hint matches what happens. Bots
	 * call it (authenticated, e.g. app password) when they receive a /bot
	 * message, passing the message's sender so the sender's personal default
	 * wins — without `sender` it resolves for the authenticated caller.
	 */
	public function genericTarget(string $room = '', string $sender = ''): JSONResponse {
		$room = trim($room);
		if ($room === '' || preg_match('/^[A-Za-z0-9]{1,64}$/', $room) !== 1) {
			return $this->error('A valid room token is required.', Http::STATUS_BAD_REQUEST);
		}

		$sender = trim($sender);
		$userId = $sender !== '' ? $sender : $this->userSession->getUser()?->getUID();
		[$generic, $ambiguous] = $this->resolveGeneric($room, $userId);

		return new JSONResponse([
			'generic' => $generic,
			'ambiguous' => $ambiguous,
		]);
	}

	/**
	 * Shared resolution behind the picker hint and the generic-target API:
	 * the sender's configured default (personal -> group -> server) when that
	 * bot is in the room, else the room's single bot, else nothing (ambiguous
	 * when several bots are present).
	 *
	 * @return array{0: ?array{name: string, target: string, source: string}, 1: bool}
	 */
	private function resolveGeneric(string $room, ?string $userId): array {
		[$default, $source] = $this->targetRegistry->configuredDefaultWithSource($userId);
		[$bot, $ambiguous] = $this->roomBotLookup->resolveRoomBot($room, 'bot', true, $default);
		if ($bot === null) {
			return [null, $ambiguous];
		}

		// The configured default only explains the outcome when it is the bot
		// that actually resolved; otherwise the room's single bot answered
		// (the default is unset or not in this room).
		if ($default === '' || !$this->roomBotLookup->botMatchesTarget($bot['name'], $default)) {
			$source = 'room';
		}

		return [[
			'name' => $bot['name'],
			'target' => $this->roomBotLookup->slashTargetForBot($bot['name']),
			'source' => $source,
		], $ambiguous];
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
