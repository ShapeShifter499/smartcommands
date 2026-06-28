<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Listener;

use OCA\SmartCommands\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<RenderReferenceEvent>
 */
class ReferenceRenderListener implements IEventListener {
	public function handle(Event $event): void {
		if ($event instanceof BeforeTemplateRenderedEvent && !$event->isLoggedIn()) {
			return;
		}

		if (!$event instanceof RenderReferenceEvent && !$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		Util::addScript(Application::APP_ID, 'smartcommands');
		Util::addStyle(Application::APP_ID, 'smartcommands');
	}
}
