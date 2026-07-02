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
		// Deliberately NOT BeforeChatMessageSentEvent: it fires before the
		// comment has an id, so the forwarded webhook carries an empty
		// object.id. Receiving bots then cannot dedupe it against the real
		// id-bearing copy that ChatMessageSentEvent sends a moment later —
		// and all empty-id forwards collide with EACH OTHER in any id-keyed
		// dedupe, which silently swallowed commands (found 2026-07-02).
		$context->registerEventListener(\OCA\Talk\Events\ChatMessageSentEvent::class, TalkSlashCommandBridgeListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
