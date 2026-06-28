<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */

script(OCA\SmartCommands\AppInfo\Application::APP_ID, 'admin');
?>
<div class="section" id="smartcommands-admin">
	<h2><?php p($l->t('Bot commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Who handles the generic /bot command. Personal choices win over group defaults, which win over the server default.')); ?>
	</p>

	<h3><?php p($l->t('Server default')); ?></h3>
	<select class="smartcommands-admin-bot" id="smartcommands-admin-server-default">
		<option value="" <?php if ($_['serverDefault'] === '') { p('selected'); } ?>>
			<?php p($l->t('No server default (uses the bot in the room)')); ?>
		</option>
		<?php foreach ($_['bots'] as $botId): ?>
			<option value="<?php p($botId); ?>" <?php if ($_['serverDefault'] === $botId) { p('selected'); } ?>>
				/<?php p($botId); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<h3><?php p($l->t('Group defaults')); ?></h3>
	<table class="grid">
		<?php foreach ($_['groups'] as $groupId): ?>
			<tr>
				<td><?php p($groupId); ?></td>
				<td>
					<select class="smartcommands-admin-bot smartcommands-admin-group" data-group="<?php p($groupId); ?>">
						<option value="" <?php if (($_['groupDefaults'][$groupId] ?? '') === '') { p('selected'); } ?>>
							<?php p($l->t('No group default')); ?>
						</option>
						<?php foreach ($_['bots'] as $botId): ?>
							<option value="<?php p($botId); ?>" <?php if (($_['groupDefaults'][$groupId] ?? '') === $botId) { p('selected'); } ?>>
								/<?php p($botId); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<span id="smartcommands-admin-status" aria-live="polite"></span>
</div>
