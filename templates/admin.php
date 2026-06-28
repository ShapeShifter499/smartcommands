<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */

script(OCA\SmartCommands\AppInfo\Application::APP_ID, 'admin');
?>
<div class="section" id="smartcommands-admin">
	<h2><?php p($l->t('Agent commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Who handles the generic /agent command. Personal choices win over group defaults, which win over the server default.')); ?>
	</p>

	<h3><?php p($l->t('Server default')); ?></h3>
	<select class="smartcommands-admin-agent" id="smartcommands-admin-server-default">
		<option value="" <?php if ($_['serverDefault'] === '') { p('selected'); } ?>>
			<?php p($l->t('Built-in fallback (nymble)')); ?>
		</option>
		<?php foreach ($_['agents'] as $agentId): ?>
			<option value="<?php p($agentId); ?>" <?php if ($_['serverDefault'] === $agentId) { p('selected'); } ?>>
				/<?php p($agentId); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<h3><?php p($l->t('Group defaults')); ?></h3>
	<table class="grid">
		<?php foreach ($_['groups'] as $groupId): ?>
			<tr>
				<td><?php p($groupId); ?></td>
				<td>
					<select class="smartcommands-admin-agent smartcommands-admin-group" data-group="<?php p($groupId); ?>">
						<option value="" <?php if (($_['groupDefaults'][$groupId] ?? '') === '') { p('selected'); } ?>>
							<?php p($l->t('No group default')); ?>
						</option>
						<?php foreach ($_['agents'] as $agentId): ?>
							<option value="<?php p($agentId); ?>" <?php if (($_['groupDefaults'][$groupId] ?? '') === $agentId) { p('selected'); } ?>>
								/<?php p($agentId); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<span id="smartcommands-admin-status" aria-live="polite"></span>
</div>
