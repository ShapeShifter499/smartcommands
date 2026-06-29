<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */

script(OCA\SmartCommands\AppInfo\Application::APP_ID, 'personal');
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

<div class="section" id="smartcommands-personal-editor" data-user-id="<?php p($_['userId']); ?>">
	<h2><?php p($l->t('Your bot commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Publish the commands your bot offers in the Smart Picker. They are owned by your account and appear as /%s.', [$_['userId']])); ?>
	</p>
	<label class="smartcommands-field">
		<?php p($l->t('Display name')); ?>
		<input type="text" id="smartcommands-bot-name" value="<?php p($_['ownName']); ?>">
	</label>
	<table class="grid">
		<thead>
			<tr>
				<th><?php p($l->t('Insert text')); ?></th>
				<th><?php p($l->t('Label')); ?></th>
				<th><?php p($l->t('Description')); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody id="smartcommands-bot-commands">
			<?php foreach ($_['ownCommands'] as $command): ?>
				<tr class="smartcommands-cmd-row">
					<td><input type="text" class="smartcommands-cmd-insert" value="<?php p($command['insert']); ?>"></td>
					<td><input type="text" class="smartcommands-cmd-label" value="<?php p($command['label']); ?>"></td>
					<td><input type="text" class="smartcommands-cmd-desc" value="<?php p($command['description']); ?>"></td>
					<td><button type="button" class="smartcommands-cmd-remove"><?php p($l->t('Remove')); ?></button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<button type="button" id="smartcommands-cmd-add"><?php p($l->t('Add command')); ?></button>
	<button type="button" id="smartcommands-bot-save"><?php p($l->t('Save commands')); ?></button>
	<button type="button" id="smartcommands-bot-delete"><?php p($l->t('Delete all')); ?></button>
	<span id="smartcommands-bot-status" aria-live="polite"></span>
	<template id="smartcommands-cmd-template">
		<tr class="smartcommands-cmd-row">
			<td><input type="text" class="smartcommands-cmd-insert"></td>
			<td><input type="text" class="smartcommands-cmd-label"></td>
			<td><input type="text" class="smartcommands-cmd-desc"></td>
			<td><button type="button" class="smartcommands-cmd-remove"><?php p($l->t('Remove')); ?></button></td>
		</tr>
	</template>
</div>

<div class="section" id="smartcommands-available">
	<h2><?php p($l->t('Available commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Everything you can use in the Smart Picker: instance-wide global commands plus what each bot has published. These are shown read-only here; a bot command only routes when that bot is present in your conversation.')); ?>
	</p>
	<?php if ($_['globalCommands'] === [] && $_['available'] === []): ?>
		<p class="settings-hint"><?php p($l->t('No commands are available yet.')); ?></p>
	<?php else: ?>
		<?php if ($_['globalCommands'] !== []): ?>
			<div class="smartcommands-manifest">
				<h3 class="smartcommands-manifest__head"><?php p($l->t('Global commands')); ?></h3>
				<table class="grid">
					<?php foreach ($_['globalCommands'] as $command): ?>
						<tr>
							<td><code><?php p($command['insert']); ?></code></td>
							<td><?php p($command['label']); ?></td>
							<td class="smartcommands-manifest__desc"><?php p($command['description']); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>
		<?php foreach ($_['available'] as $manifest): ?>
			<div class="smartcommands-manifest">
				<h3 class="smartcommands-manifest__head">
					<?php p($manifest['name']); ?>
					<code class="smartcommands-manifest__id">/<?php p($manifest['id']); ?></code>
				</h3>
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
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
