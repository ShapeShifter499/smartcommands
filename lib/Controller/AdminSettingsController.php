<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Controller;

use OCA\SmartCommands\AppInfo\Application;
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
}
