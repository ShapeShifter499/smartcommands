<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Reference;

use OCA\SmartCommands\AppInfo\Application;
use OCA\SmartCommands\Service\TargetRegistry;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IURLGenerator;
use OCP\IL10N;

class SmartCommandsProvider extends ADiscoverableReferenceProvider {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private TargetRegistry $targetRegistry,
	) {
	}

	public function getId(): string {
		return 'smartcommands';
	}

	public function getTitle(): string {
		// Include the slash targets so the composer's provider search matches
		// partial command typing such as "/emb" against this entry's title.
		$botIds = $this->targetRegistry->registeredBotIds();
		if ($botIds === []) {
			return $this->l10n->t('Bot commands');
		}

		$targets = implode(', ', array_map(static fn (string $id): string => '/' . $id, $botIds));
		return $this->l10n->t('Bot commands') . ' (' . $targets . ')';
	}

	public function getOrder(): int {
		return 45;
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}

	public function matchReference(string $referenceText): bool {
		return str_starts_with($referenceText, 'bot-command://');
	}

	public function resolveReference(string $referenceText): ?IReference {
		if (!$this->matchReference($referenceText)) {
			return null;
		}

		$command = substr($referenceText, strlen('bot-command://'));
		$reference = new Reference($referenceText);
		$reference->setTitle($this->l10n->t('Bot command: %s', [$command]));
		$reference->setDescription($this->l10n->t('Bot command selected from Smart Picker'));
		$reference->setUrl($referenceText);
		$reference->setRichObject('bot-command', [
			'id' => $referenceText,
			'name' => $command,
			'description' => $this->l10n->t('Bot command'),
		]);

		return $reference;
	}

	public function getCachePrefix(string $referenceId): string {
		return 'smartcommands';
	}

	public function getCacheKey(string $referenceId): ?string {
		return null;
	}
}
