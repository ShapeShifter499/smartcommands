<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Settings;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Personal implements ISettings {
	public function __construct(
		private IConfig $config,
		private IUserSession $userSession,
		private TargetRegistry $targetRegistry,
	) {
	}

	public function getForm(): TemplateResponse {
		$user = $this->userSession->getUser();
		$userId = $user === null ? '' : $user->getUID();
		$current = $userId === '' ? '' : $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			'default_bot_target',
			'',
		);
		return new TemplateResponse(Application::APP_ID, 'personal', [
			'bots' => $this->targetRegistry->registeredBotIds(),
			'current' => $current,
			'serverDefault' => $this->targetRegistry->resolveAlias('bot'),
			'globalCommands' => $this->targetRegistry->globalCommands(),
			// Orphaned manifests (publishing account deleted) no longer route, so
			// presenting them as available would mislead. liveManifests() is the
			// shared, fail-closed source of truth for that exclusion (also used by
			// the Smart Picker endpoint); the admin view keeps them flagged.
			'available' => $this->targetRegistry->liveManifests(),
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 80;
	}
}
