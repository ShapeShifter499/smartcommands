<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Controller;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class PersonalSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
		private TargetRegistry $targetRegistry,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Browser-session endpoint: CSRF-protected on purpose (no NoCSRFRequired).
	 *
	 * @NoAdminRequired
	 */
	public function setDefaultAgent(string $agent = ''): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$agent = strtolower(trim($agent));
		if ($agent !== '' && !in_array($agent, $this->targetRegistry->registeredAgentIds(), true)) {
			return new JSONResponse(['error' => 'Unknown agent.'], Http::STATUS_BAD_REQUEST);
		}

		if ($agent === '') {
			$this->config->deleteUserValue($user->getUID(), Application::APP_ID, 'default_agent_target');
		} else {
			$this->config->setUserValue($user->getUID(), Application::APP_ID, 'default_agent_target', $agent);
		}

		return new JSONResponse(['agent' => $agent]);
	}
}
