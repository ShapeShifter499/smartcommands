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

		$room = $this->normalizedRoomToken($room);
		$userId = $this->userSession->getUser()?->getUID();
		$filtered = false;
		$generic = null;
		$genericAmbiguous = false;
		// Non-participants get the unscoped list, exactly as if no room had
		// been supplied: room-scoped data (which bots are in the room, where
		// /bot resolves) must not leak on a merely known or guessed token.
		if ($room !== null && $this->isRoomParticipant($room, $userId)) {
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
	 *
	 * Room-scoped data stays in the room: the caller must be a participant of
	 * the room (for a bot that means its Nextcloud user account, not just its
	 * Talk bot record), and a sender other than the caller must be one too.
	 */
	public function genericTarget(string $room = '', string $sender = ''): JSONResponse {
		$room = $this->normalizedRoomToken($room);
		if ($room === null) {
			return $this->error('A valid room token is required.', Http::STATUS_BAD_REQUEST);
		}

		$callerId = $this->userSession->getUser()?->getUID();
		if (!$this->isRoomParticipant($room, $callerId)) {
			return $this->error('Only participants of the room can query it.', Http::STATUS_FORBIDDEN);
		}

		$sender = trim($sender);
		$userId = $sender !== '' ? $sender : $callerId;
		if ($userId !== $callerId && !$this->isRoomParticipant($room, $userId)) {
			return $this->error('sender must be a participant of the room.', Http::STATUS_FORBIDDEN);
		}

		[$generic, $genericAmbiguous] = $this->resolveGeneric($room, $userId);

		return new JSONResponse([
			'generic' => $generic,
			'genericAmbiguous' => $genericAmbiguous,
		]);
	}

	/**
	 * Normalizes and validates a Talk room token; null when it is not a
	 * plausible token. Shared by every endpoint that takes a room parameter
	 * so the accepted format cannot drift between them.
	 */
	private function normalizedRoomToken(string $room): ?string {
		$room = trim($room);
		return preg_match('/^[A-Za-z0-9]{1,64}$/', $room) === 1 ? $room : null;
	}

	/**
	 * Whether the user participates in the Talk room. Fails closed: no user,
	 * no Talk app, or no membership all mean "no access" — room-scoped bot
	 * and resolution data must not leak to non-participants who merely know
	 * (or guess) a room token.
	 */
	private function isRoomParticipant(string $room, ?string $userId): bool {
		if ($userId === null || $userId === '') {
			return false;
		}
		if (!class_exists(\OCA\Talk\Manager::class)) {
			return false;
		}
		try {
			\OCP\Server::get(\OCA\Talk\Manager::class)->getRoomForUserByToken($room, $userId);
			return true;
		} catch (\Throwable) {
			return false;
		}
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
			$source = TargetRegistry::SOURCE_ROOM;
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
