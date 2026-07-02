<?php

declare(strict_types=1);

namespace OCA\SmartCommands\AppInfo;

use OCA\SmartCommands\Listener\ReferenceRenderListener;
use OCA\SmartCommands\Reference\SmartCommandsProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'smartcommands';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerReferenceProvider(SmartCommandsProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, ReferenceRenderListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, ReferenceRenderListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
