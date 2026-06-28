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
	public function setDefaultBot(string $bot = ''): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
		}

		$bot = strtolower(trim($bot));
		if ($bot !== '' && !in_array($bot, $this->targetRegistry->registeredBotIds(), true)) {
			return new JSONResponse(['error' => 'Unknown bot.'], Http::STATUS_BAD_REQUEST);
		}

		if ($bot === '') {
			$this->config->deleteUserValue($user->getUID(), Application::APP_ID, 'default_bot_target');
		} else {
			$this->config->setUserValue($user->getUID(), Application::APP_ID, 'default_bot_target', $bot);
		}

		return new JSONResponse(['bot' => $bot]);
	}
}
