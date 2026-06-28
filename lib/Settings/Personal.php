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
		$current = $user === null ? '' : $this->config->getUserValue(
			$user->getUID(),
			Application::APP_ID,
			'default_agent_target',
			'',
		);

		return new TemplateResponse(Application::APP_ID, 'personal', [
			'agents' => $this->targetRegistry->registeredAgentIds(),
			'current' => $current,
			'serverDefault' => $this->targetRegistry->resolveAlias('agent'),
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 80;
	}
}
