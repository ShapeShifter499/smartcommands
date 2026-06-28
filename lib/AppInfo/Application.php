<?php

declare(strict_types=1);

namespace OCA\SmartCommands\AppInfo;

use OCA\SmartCommands\Listener\ReferenceRenderListener;
use OCA\SmartCommands\Listener\TalkBotInvokeListener;
use OCA\SmartCommands\Listener\TalkSlashCommandBridgeListener;
use OCA\SmartCommands\Reference\SmartCommandsProvider;
use OCA\Talk\Events\BotInvokeEvent;
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
		$context->registerEventListener(BotInvokeEvent::class, TalkBotInvokeListener::class);
		$context->registerEventListener(\OCA\Talk\Events\MessageParseEvent::class, TalkSlashCommandBridgeListener::class);
		$context->registerEventListener(\OCA\Talk\Events\BeforeChatMessageSentEvent::class, TalkSlashCommandBridgeListener::class);
		$context->registerEventListener(\OCA\Talk\Events\ChatMessageSentEvent::class, TalkSlashCommandBridgeListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
