<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Listener;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\RoomBotLookup;
use OCA\SmartCommands\Service\TargetRegistry;
use OCA\Talk\Events\BotInvokeEvent;
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
class TalkBotInvokeListener implements IEventListener {
	private const APP_BOT_URL = 'nextcloudapp://' . Application::APP_ID;

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
		if (!$event instanceof BotInvokeEvent || $event->getBotUrl() !== self::APP_BOT_URL) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'talk_event_bridge_enabled', '1') !== '1') {
			return;
		}

		$body = $event->getMessage();
		if (($body['type'] ?? '') !== 'Create') {
			return;
		}

		$content = json_decode((string)($body['object']['content'] ?? ''), true);
		if (!is_array($content)) {
			return;
		}

		$rawMessage = trim((string)($content['message'] ?? ''));
		if ($rawMessage === '' || $rawMessage[0] !== '/') {
			return;
		}

		$pattern = $this->targetRegistry->messagePattern();
		if ($pattern === null || !preg_match($pattern, $rawMessage, $matches)) {
			return;
		}

		$roomToken = (string)($body['target']['id'] ?? '');
		if ($roomToken === '') {
			return;
		}

		$target = strtolower($matches['target']);
		$actorRef = (string)(($body['actor'] ?? [])['id'] ?? '');
		[$actorType, $actorId] = array_pad(explode('/', $actorRef, 2), 2, '');
		$resolvedTarget = $this->targetRegistry->resolveAlias(
			$target,
			$actorType === 'users' ? $actorId : null,
		);
		$bot = $this->roomBotLookup->findBotForTarget($roomToken, $resolvedTarget);
		if ($bot === null) {
			$this->logger->debug('Smart Picker Commands event bot bridge found no matching webhook bot', [
				'app' => Application::APP_ID,
				'target' => $target,
				'resolvedTarget' => $resolvedTarget,
				'roomToken' => $roomToken,
			]);
			return;
		}

		$this->sendWebhook($bot['name'], $bot['url'], $bot['secret'], json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	private function sendWebhook(string $botName, string $botUrl, string $botSecret, string $body): void {
		$random = $this->secureRandom->generate(64);
		$signature = hash_hmac('sha256', $random . $body, $botSecret);
		$backend = rtrim($this->config->getSystemValueString('overwrite.cli.url'), '/') . '/';

		$client = $this->clientService->newClient();
		$promise = $client->postAsync($botUrl, [
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
		]);

		$promise->then(function () use ($botName): void {
			$this->logger->debug('Smart Picker Commands event bot bridge invoked Talk bot webhook', [
				'app' => Application::APP_ID,
				'botName' => $botName,
			]);
		}, function (\Throwable $error) use ($botName): void {
			$this->logger->warning('Smart Picker Commands event bot bridge failed to invoke Talk bot ' . $botName, [
				'app' => Application::APP_ID,
				'botName' => $botName,
				'exceptionClass' => $error::class,
				'exceptionCode' => $error->getCode(),
				'exceptionMessage' => $error->getMessage(),
			]);
		});
	}
}
