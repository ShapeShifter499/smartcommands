<?php

declare(strict_types=1);

namespace OCA\SmartCommands\Migration;

use Closure;
use OCA\SmartCommands\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

/**
 * Renames the "agent" vocabulary to "bot" in stored data, so existing
 * installs keep their configuration after the app generalised away from
 * "agents" to any bot:
 *
 *   - app config:  default_agent_target        -> default_bot_target
 *                  group_default_agent_targets  -> group_default_bot_targets
 *   - user config: default_agent_target        -> default_bot_target
 *   - manifests:   app key "agent:<user>:<id>"  -> "bot:<user>:<id>"
 *
 * Old keys are removed once copied. The step is idempotent: existing target
 * keys are never overwritten, so re-running it is a no-op.
 */
class Version000600Date20260628000000 implements IMigrationStep {
	public function __construct(
		private IConfig $config,
		private IDBConnection $db,
	) {
	}

	public function name(): string {
		return 'Rename agent vocabulary to bot in stored data';
	}

	public function description(): string {
		return 'Renames the stored default_bot_target / group-default app values, per-user defaults, and the "bot:" manifest keys from their previous "agent" names, so existing configuration survives the upgrade.';
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateAppValue('default_agent_target', 'default_bot_target');
		$this->migrateAppValue('group_default_agent_targets', 'group_default_bot_targets');
		$this->migrateManifestKeys($output);
		$this->migrateUserValues($output);
	}

	private function migrateAppValue(string $old, string $new): void {
		$value = $this->config->getAppValue(Application::APP_ID, $old, '');
		if ($value === '') {
			return;
		}
		if ($this->config->getAppValue(Application::APP_ID, $new, '') === '') {
			$this->config->setAppValue(Application::APP_ID, $new, $value);
		}
		$this->config->deleteAppValue(Application::APP_ID, $old);
	}

	private function migrateManifestKeys(IOutput $output): void {
		$count = 0;
		foreach ($this->config->getAppKeys(Application::APP_ID) as $key) {
			if (!str_starts_with($key, 'agent:')) {
				continue;
			}
			$value = $this->config->getAppValue(Application::APP_ID, $key, '');
			$newKey = 'bot:' . substr($key, strlen('agent:'));
			if ($value !== '' && $this->config->getAppValue(Application::APP_ID, $newKey, '') === '') {
				$this->config->setAppValue(Application::APP_ID, $newKey, $value);
			}
			$this->config->deleteAppValue(Application::APP_ID, $key);
			$count++;
		}
		if ($count > 0) {
			$output->info(sprintf('Migrated %d Smart Picker Commands manifest(s) from "agent:" to "bot:"', $count));
		}
	}

	private function migrateUserValues(IOutput $output): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid', 'configvalue')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('default_agent_target')));

		// Buffer the rows and close the cursor before writing: setUserValue/
		// deleteUserValue write to the same "preferences" table, which raises
		// "Commands out of sync" on deployments using unbuffered queries if a
		// result set over that table is still open.
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$count = 0;
		foreach ($rows as $row) {
			$userId = (string)$row['userid'];
			$value = (string)$row['configvalue'];
			if ($value !== '' && $this->config->getUserValue($userId, Application::APP_ID, 'default_bot_target', '') === '') {
				$this->config->setUserValue($userId, Application::APP_ID, 'default_bot_target', $value);
			}
			$this->config->deleteUserValue($userId, Application::APP_ID, 'default_agent_target');
			$count++;
		}

		if ($count > 0) {
			$output->info(sprintf('Migrated %d personal Smart Picker Commands default(s)', $count));
		}
	}
}
