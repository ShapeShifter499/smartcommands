<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Controller;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\CommandList;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * No NoAdminRequired annotations: these endpoints are admin-only and stay
 * CSRF-protected for browser sessions.
 */
class AdminSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IGroupManager $groupManager,
		private TargetRegistry $targetRegistry,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function setDefaultBot(string $bot = ''): JSONResponse {
		$bot = strtolower(trim($bot));
		if ($bot !== '' && !in_array($bot, $this->targetRegistry->registeredBotIds(), true)) {
			return new JSONResponse(['error' => 'Unknown bot.'], Http::STATUS_BAD_REQUEST);
		}

		if ($bot === '') {
			$this->config->deleteAppValue(Application::APP_ID, 'default_bot_target');
		} else {
			$this->config->setAppValue(Application::APP_ID, 'default_bot_target', $bot);
		}

		return new JSONResponse(['bot' => $bot]);
	}

	public function setGroupDefault(string $group = '', string $bot = ''): JSONResponse {
		$group = trim($group);
		if ($group === '' || !$this->groupManager->groupExists($group)) {
			return new JSONResponse(['error' => 'Unknown group.'], Http::STATUS_BAD_REQUEST);
		}

		$bot = strtolower(trim($bot));
		if ($bot !== '' && !in_array($bot, $this->targetRegistry->registeredBotIds(), true)) {
			return new JSONResponse(['error' => 'Unknown bot.'], Http::STATUS_BAD_REQUEST);
		}

		$this->targetRegistry->setGroupDefault($group, $bot);

		return new JSONResponse(['group' => $group, 'bot' => $bot]);
	}

	public function deleteManifest(string $owner = '', string $botId = ''): JSONResponse {
		$owner = trim($owner);
		$botId = trim($botId);
		if ($owner === '' || $botId === '') {
			return new JSONResponse(['error' => 'owner and botId are required.'], Http::STATUS_BAD_REQUEST);
		}

		if (!$this->targetRegistry->deleteManifest($owner, $botId)) {
			return new JSONResponse(['error' => 'Manifest not found.'], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(['deleted' => true]);
	}

	/**
	 * Replaces the admin-authored global command list. Accepts the same command
	 * shape as a bot manifest; an empty list clears the global commands.
	 */
	public function setGlobalCommands(): JSONResponse {
		$commands = $this->request->getParams()['commands'] ?? null;
		if (!is_array($commands)) {
			return new JSONResponse(['error' => 'commands must be an array.'], Http::STATUS_BAD_REQUEST);
		}

		$normalized = CommandList::normalize($commands);
		$this->targetRegistry->setGlobalCommands($normalized);

		return new JSONResponse(['commands' => $normalized]);
	}

	public function setBridge(string $bridge = '', bool $enabled = true): JSONResponse {
		$keys = [
			'slash' => 'talk_slash_bridge_enabled',
			'event' => 'talk_event_bridge_enabled',
		];
		if (!isset($keys[$bridge])) {
			return new JSONResponse(['error' => 'Unknown bridge.'], Http::STATUS_BAD_REQUEST);
		}

		$this->config->setAppValue(Application::APP_ID, $keys[$bridge], $enabled ? '1' : '0');

		return new JSONResponse(['bridge' => $bridge, 'enabled' => $enabled]);
	}
}
