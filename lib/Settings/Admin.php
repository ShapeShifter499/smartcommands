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
			'agents' => $this->targetRegistry->registeredAgentIds(),
			'serverDefault' => $this->config->getAppValue(Application::APP_ID, 'default_agent_target', ''),
			'groups' => $groupIds,
			'groupDefaults' => $this->targetRegistry->groupDefaults(),
		]);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 80;
	}
}
