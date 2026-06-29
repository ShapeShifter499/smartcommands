<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Settings;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
		private TargetRegistry $targetRegistry,
	) {
	}

	public function getForm(): TemplateResponse {
		$groupIds = array_map(
			static fn ($group): string => $group->getGID(),
			$this->groupManager->search(''),
		);
		sort($groupIds);

		return new TemplateResponse(Application::APP_ID, 'admin', [
			'bots' => $this->targetRegistry->registeredBotIds(),
			'serverDefault' => $this->config->getAppValue(Application::APP_ID, 'default_bot_target', ''),
			'groups' => $groupIds,
			'groupDefaults' => $this->targetRegistry->groupDefaults(),
			'manifests' => $this->targetRegistry->allManifests(),
			'slashBridge' => $this->config->getAppValue(Application::APP_ID, 'talk_slash_bridge_enabled', '1') === '1',
			'eventBridge' => $this->config->getAppValue(Application::APP_ID, 'talk_event_bridge_enabled', '1') === '1',
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 80;
	}
}
