<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Listener;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\RoomBotLookup;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class TalkSlashCommandBridgeListener implements IEventListener {
	private const DEDUPE_TTL_SECONDS = 300;
	private const DEDUPE_CONFIG_KEY = 'talk_slash_bridge_recent';
	private const PARSE_EVENT_MAX_AGE_SECONDS = 90;

	public function __construct(
		private IClientService $clientService,
		private IConfig $config,
		private ICertificateManager $certificateManager,
		private ISecureRandom $secureRandom,
		private TargetRegistry $targetRegistry,
		private RoomBotLookup $roomBotLookup,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($this->config->getAppValue(Application::APP_ID, 'talk_slash_bridge_enabled', '1') !== '1') {
			return;
		}

		if (class_exists(\OCA\Talk\Events\MessageParseEvent::class)
			&& $event instanceof \OCA\Talk\Events\MessageParseEvent) {
			$this->handleMessageParseEvent($event);
			return;
		}

		if (!class_exists(\OCA\Talk\Events\AMessageSentEvent::class)
			|| !$event instanceof \OCA\Talk\Events\AMessageSentEvent) {
			return;
		}

		$comment = $event->getComment();
		$rawMessage = trim($comment->getMessage());
		$target = $this->matchTarget($rawMessage);
		if ($target === null) {
			return;
		}

		$participant = $event->getParticipant();
		$attendee = $participant?->getAttendee();
		if ($attendee === null) {
			return;
		}

		$actorType = $attendee->getActorType();
		if (!in_array($actorType, ['users', 'guests', 'federated_users', 'emails'], true)) {
			return;
		}

		$room = $event->getRoom();
		$this->processCommand(
			$room->getToken(),
			$room->getName(),
			$rawMessage,
			$target,
			$actorType,
			(string)$attendee->getActorId(),
			$attendee->getDisplayName(),
			(string)$attendee->getParticipantType(),
			(string)$comment->getId(),
			'talk-message-sent',
		);
	}

	private function handleMessageParseEvent(\OCA\Talk\Events\MessageParseEvent $event): void {
		$message = $event->getMessage();
		$comment = $message->getComment();
		if ($comment === null) {
			return;
		}

		$ageSeconds = time() - $comment->getCreationDateTime()->getTimestamp();
		if ($ageSeconds < 0 || $ageSeconds > self::PARSE_EVENT_MAX_AGE_SECONDS) {
			return;
		}

		$rawMessage = trim($message->getMessageRaw() ?: $message->getMessage() ?: $comment->getMessage());
		$target = $this->matchTarget($rawMessage);
		if ($target === null) {
			return;
		}

		$actorType = $message->getActorType();
		if (!in_array($actorType, ['users', 'guests', 'federated_users', 'emails'], true)) {
			return;
		}

		$participantType = '';
		$participant = $message->getParticipant();
		$attendee = $participant?->getAttendee();
		if ($attendee !== null) {
			$participantType = (string)$attendee->getParticipantType();
		}

		$room = $event->getRoom();
		$this->processCommand(
			$room->getToken(),
			$room->getName(),
			$rawMessage,
			$target,
			$actorType,
			$message->getActorId(),
			$message->getActorDisplayName(),
			$participantType,
			(string)$comment->getId(),
			'talk-message-parse',
		);
	}

	/**
	 * Matches the message against the targets currently known from registered
	 * manifests. Returns the lowercase target token, or null when the message
	 * is not an agent command.
	 */
	private function matchTarget(string $rawMessage): ?string {
		if ($rawMessage === '' || $rawMessage[0] !== '/') {
			return null;
		}

		$pattern = $this->targetRegistry->messagePattern();
		if ($pattern === null || !preg_match($pattern, $rawMessage, $matches)) {
			return null;
		}

		return strtolower($matches['target']);
	}

	private function processCommand(
		string $roomToken,
		string $roomName,
		string $rawMessage,
		string $target,
		string $actorType,
		string $actorId,
		string $actorDisplayName,
		string $participantType,
		string $messageId,
		string $eventSource,
	): void {
		if ($this->shouldSkipRecentDuplicate($roomToken, $actorType, $actorId, $rawMessage, $messageId)) {
			$this->logger->debug('Smart Picker Commands slash bridge skipped duplicate Talk command event', [
				'app' => Application::APP_ID,
				'target' => $target,
				'roomToken' => $roomToken,
				'messageId' => $messageId,
				'eventSource' => $eventSource,
			]);
			return;
		}

		$resolvedTarget = $this->targetRegistry->resolveAlias(
			$target,
			$actorType === 'users' ? $actorId : null,
		);
		$bot = $this->roomBotLookup->findBotForTarget($roomToken, $resolvedTarget);
		if ($bot === null) {
			$this->logger->debug('Smart Picker Commands slash bridge found no matching Talk bot', [
				'app' => Application::APP_ID,
				'target' => $target,
				'resolvedTarget' => $resolvedTarget,
				'roomToken' => $roomToken,
				'eventSource' => $eventSource,
			]);
			return;
		}

		$content = json_encode([
			'message' => $rawMessage,
			'parameters' => [],
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

		$body = json_encode([
			'type' => 'Create',
			'actor' => [
				'type' => 'Person',
				'id' => $actorType . '/' . $actorId,
				'name' => $actorDisplayName,
				'talkParticipantType' => $participantType,
			],
			'object' => [
				'type' => 'Note',
				'id' => $messageId,
				'name' => 'message',
				'content' => $content,
				'mediaType' => 'text/markdown',
			],
			'target' => [
				'type' => 'Collection',
				'id' => $roomToken,
				'name' => $roomName,
			],
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

		$this->sendWebhook($bot['name'], $bot['url'], $bot['secret'], $body);
	}

	private function shouldSkipRecentDuplicate(string $roomToken, string $actorType, string $actorId, string $rawMessage, string $messageId): bool {
		$now = time();
		$keyMaterial = $messageId !== '' ? $messageId : $actorType . "\0" . $actorId . "\0" . $rawMessage;
		$key = hash('sha256', $roomToken . "\0" . $keyMaterial);
		$recent = json_decode($this->config->getAppValue(Application::APP_ID, self::DEDUPE_CONFIG_KEY, '{}'), true);
		if (!is_array($recent)) {
			$recent = [];
		}

		foreach ($recent as $recentKey => $timestamp) {
			if (!is_int($timestamp) && !ctype_digit((string)$timestamp)) {
				unset($recent[$recentKey]);
				continue;
			}
			if ($now - (int)$timestamp > self::DEDUPE_TTL_SECONDS) {
				unset($recent[$recentKey]);
			}
		}

		if (isset($recent[$key])) {
			$this->config->setAppValue(Application::APP_ID, self::DEDUPE_CONFIG_KEY, json_encode($recent, JSON_THROW_ON_ERROR));
			return true;
		}

		$recent[$key] = $now;
		$this->config->setAppValue(Application::APP_ID, self::DEDUPE_CONFIG_KEY, json_encode($recent, JSON_THROW_ON_ERROR));
		return false;
	}

	private function sendWebhook(string $botName, string $botUrl, string $botSecret, string $body): void {
		$random = $this->secureRandom->generate(64);
		$signature = hash_hmac('sha256', $random . $body, $botSecret);
		$backend = rtrim($this->config->getSystemValueString('overwrite.cli.url'), '/') . '/';

		$client = $this->clientService->newClient();
		$options = [
			'verify' => $this->certificateManager->getAbsoluteBundlePath(),
			'nextcloud' => [
				'allow_local_address' => true,
			],
			'headers' => [
				'Content-Type' => 'application/json',
				'X-Nextcloud-Talk-Random' => $random,
				'X-Nextcloud-Talk-Signature' => $signature,
				'X-Nextcloud-Talk-Backend' => $backend,
				'OCS-APIRequest' => 'true',
			],
			'timeout' => 5,
			'body' => $body,
		];

		try {
			$response = $client->post($botUrl, $options);
			$this->logger->debug('Smart Picker Commands slash bridge invoked Talk bot webhook', [
				'app' => Application::APP_ID,
				'botName' => $botName,
				'statusCode' => $response->getStatusCode(),
			]);
		} catch (\Throwable $error) {
			$this->logger->warning('Smart Picker Commands slash bridge failed to invoke Talk bot ' . $botName, [
				'app' => Application::APP_ID,
				'botName' => $botName,
				'exceptionClass' => $error::class,
				'exceptionCode' => $error->getCode(),
				'exceptionMessage' => $error->getMessage(),
			]);
		}
	}
}
