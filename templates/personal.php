<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */

script(OCA\SmartCommands\AppInfo\Application::APP_ID, 'personal');
style(OCA\SmartCommands\AppInfo\Application::APP_ID, 'settings');
?>
<div class="section" id="smartcommands-personal">
	<h2><?php p($l->t('Bot commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Which bot should handle the generic /bot command when you use it in Talk.')); ?>
	</p>
	<select id="smartcommands-default-bot">
		<option value="" <?php if ($_['current'] === '') { p('selected'); } ?>>
			<?php
			if ($_['serverDefault'] !== '') {
				p($l->t('Server default (%s)', [$_['serverDefault']]));
			} else {
				p($l->t('Server default (the bot in the room)'));
			}
			?>
		</option>
		<?php foreach ($_['bots'] as $botId): ?>
			<option value="<?php p($botId); ?>" <?php if ($_['current'] === $botId) { p('selected'); } ?>>
				/<?php p($botId); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<span id="smartcommands-default-bot-status" aria-live="polite"></span>
</div>

<div class="section" id="smartcommands-available">
	<h2><?php p($l->t('Available commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Everything you can use in the Smart Picker: instance-wide global commands plus what each bot has published. These are shown read-only here; a bot command only routes when that bot is present in your conversation.')); ?>
	</p>
	<p class="settings-hint">
		<?php p($l->t('Global commands are managed by your administrators. A bot\'s own commands are managed by that bot. You cannot edit either from here.')); ?>
	</p>
	<?php if ($_['globalCommands'] === [] && $_['available'] === []): ?>
		<p class="settings-hint"><?php p($l->t('No commands are available yet.')); ?></p>
	<?php else: ?>
		<?php if ($_['globalCommands'] !== []): ?>
			<details class="smartcommands-manifest">
				<summary class="smartcommands-manifest__head"><?php p($l->t('Global commands')); ?></summary>
				<table class="grid">
					<?php foreach ($_['globalCommands'] as $command): ?>
						<tr>
							<td><code><?php p($command['insert']); ?></code></td>
							<td><?php p($command['label']); ?></td>
							<td class="smartcommands-manifest__desc"><?php p($command['description']); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</details>
		<?php endif; ?>
		<?php foreach ($_['available'] as $manifest): ?>
			<details class="smartcommands-manifest">
				<summary class="smartcommands-manifest__head">
					<?php p($manifest['name']); ?>
					<code class="smartcommands-manifest__id">/<?php p($manifest['id']); ?></code>
				</summary>
				<?php if ($manifest['commands'] === []): ?>
					<p class="settings-hint"><?php p($l->t('No commands.')); ?></p>
				<?php else: ?>
					<table class="grid">
						<?php foreach ($manifest['commands'] as $command): ?>
							<tr>
								<td><code><?php p($command['insert']); ?></code></td>
								<td><?php p($command['label']); ?></td>
								<td class="smartcommands-manifest__desc"><?php p($command['description']); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
