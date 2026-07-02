<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Service;

use OCP\IDBConnection;

/**
 * Shared lookup of enabled webhook bots per Talk conversation. Used by both
 * bridge listeners for routing and by the manifest API for room-aware
 * filtering of the Smart Picker command list.
 */
class RoomBotLookup {
	private const BOT_STATE_DISABLED = 0;
	private const BOT_FEATURE_WEBHOOK = 1;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Enabled webhook-capable bots in the conversation. Bots backed by a
	 * nextcloudapp:// URL (in-process event bots) are excluded: they cannot
	 * receive forwarded webhooks.
	 *
	 * @return list<array{name: string, url: string, secret: string}>
	 */
	public function webhookBotsForRoom(string $roomToken): array {
		$query = $this->db->getQueryBuilder();
		$query->select('s.name', 's.url', 's.secret', 's.features')
			->from('talk_bots_server', 's')
			->innerJoin('s', 'talk_bots_conversation', 'c', $query->expr()->eq('s.id', 'c.bot_id'))
			->where($query->expr()->eq('c.token', $query->createNamedParameter($roomToken)))
			->andWhere($query->expr()->neq('s.state', $query->createNamedParameter(self::BOT_STATE_DISABLED)))
			->andWhere($query->expr()->neq('c.state', $query->createNamedParameter(self::BOT_STATE_DISABLED)));

		$bots = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$name = (string)($row['name'] ?? '');
			$url = (string)($row['url'] ?? '');
			$secret = (string)($row['secret'] ?? '');
			$features = (int)($row['features'] ?? 0);
			if ($url === '' || $secret === ''
				|| str_starts_with($url, 'nextcloudapp://')
				|| ($features & self::BOT_FEATURE_WEBHOOK) === 0) {
				continue;
			}
			$bots[] = [
				'name' => $name,
				'url' => $url,
				'secret' => $secret,
			];
		}
		$result->closeCursor();

		return $bots;
	}

	/**
	 * First enabled webhook bot in the room whose name matches the resolved
	 * target (exact lowercase name or first word of the name).
	 *
	 * @return null|array{name: string, url: string, secret: string}
	 */
	public function findBotForTarget(string $roomToken, string $resolvedTarget): ?array {
		$resolvedTarget = strtolower(trim($resolvedTarget));
		if ($resolvedTarget === '') {
			return null;
		}

		foreach ($this->webhookBotsForRoom($roomToken) as $bot) {
			if ($this->botMatchesTarget($bot['name'], $resolvedTarget)) {
				return $bot;
			}
		}

		return null;
	}

	/**
	 * Room-aware resolution of a slash target to a concrete webhook bot.
	 *
	 * Explicit targets route to the matching bot in the room. The generic
	 * alias resolves to, in order:
	 *   1. the configured default, when its bot is enabled in the room;
	 *   2. the single webhook bot in the room, when exactly one is present;
	 *   3. otherwise none -- "ambiguous" when several bots are present, so the
	 *      caller can nudge the user toward an explicit /<name> command.
	 *
	 * @return array{0: null|array{name: string, url: string, secret: string}, 1: bool}
	 *         [resolved bot or null, ambiguous]
	 */
	public function resolveRoomBot(string $roomToken, string $target, bool $isGeneric, string $configuredDefault): array {
		if (!$isGeneric) {
			return [$this->findBotForTarget($roomToken, $target), false];
		}

		$configuredDefault = strtolower(trim($configuredDefault));
		if ($configuredDefault !== '') {
			$bot = $this->findBotForTarget($roomToken, $configuredDefault);
			if ($bot !== null) {
				return [$bot, false];
			}
		}

		$bots = $this->webhookBotsForRoom($roomToken);
		if (count($bots) === 1) {
			return [$bots[0], false];
		}

		return [null, count($bots) > 1];
	}

	public function botMatchesTarget(string $botName, string $resolvedTarget): bool {
		return $this->slashTargetForBot($botName) === $resolvedTarget
			|| strtolower(trim($botName)) === $resolvedTarget;
	}

	/**
	 * The slash target a user would type to address a bot explicitly: the
	 * first word of its name, lowercased ("Nymble Bot" -> "nymble").
	 */
	public function slashTargetForBot(string $botName): string {
		return strtok(strtolower(trim($botName)), " \t\r\n") ?: '';
	}
}
