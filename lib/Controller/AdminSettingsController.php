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

	public function setDefaultAgent(string $agent = ''): JSONResponse {
		$agent = strtolower(trim($agent));
		if ($agent !== '' && !in_array($agent, $this->targetRegistry->registeredAgentIds(), true)) {
			return new JSONResponse(['error' => 'Unknown agent.'], Http::STATUS_BAD_REQUEST);
		}

		if ($agent === '') {
			$this->config->deleteAppValue(Application::APP_ID, 'default_agent_target');
		} else {
			$this->config->setAppValue(Application::APP_ID, 'default_agent_target', $agent);
		}

		return new JSONResponse(['agent' => $agent]);
	}

	public function setGroupDefault(string $group = '', string $agent = ''): JSONResponse {
		$group = trim($group);
		if ($group === '' || !$this->groupManager->groupExists($group)) {
			return new JSONResponse(['error' => 'Unknown group.'], Http::STATUS_BAD_REQUEST);
		}

		$agent = strtolower(trim($agent));
		if ($agent !== '' && !in_array($agent, $this->targetRegistry->registeredAgentIds(), true)) {
			return new JSONResponse(['error' => 'Unknown agent.'], Http::STATUS_BAD_REQUEST);
		}

		$this->targetRegistry->setGroupDefault($group, $agent);

		return new JSONResponse(['group' => $group, 'agent' => $agent]);
	}
}
