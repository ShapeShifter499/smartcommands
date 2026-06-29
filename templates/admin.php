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

	<h3><?php p($l->t('Bridges')); ?></h3>
	<p class="settings-hint">
		<?php p($l->t('Forward slash-style commands to the matching Talk bot. Disable a bridge if routing is handled elsewhere.')); ?>
	</p>
	<label>
		<input type="checkbox" class="smartcommands-bridge-toggle" data-bridge="slash" <?php if ($_['slashBridge']) { p('checked'); } ?>>
		<?php p($l->t('Slash-message bridge')); ?>
	</label>
	<br>
	<label>
		<input type="checkbox" class="smartcommands-bridge-toggle" data-bridge="event" <?php if ($_['eventBridge']) { p('checked'); } ?>>
		<?php p($l->t('Event-bot bridge')); ?>
	</label>

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

	<h3><?php p($l->t('Global commands')); ?></h3>
	<p class="settings-hint">
		<?php p($l->t('Instance-wide commands you author here. They belong to no bot, appear in the Smart Picker for every user, and are shown in any conversation.')); ?>
	</p>
	<div id="smartcommands-global-editor">
		<table class="grid">
			<thead>
				<tr>
					<th><?php p($l->t('Insert text')); ?></th>
					<th><?php p($l->t('Label')); ?></th>
					<th><?php p($l->t('Description')); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="smartcommands-global-commands">
				<?php foreach ($_['globalCommands'] as $command): ?>
					<tr class="smartcommands-cmd-row">
						<td><input type="text" class="smartcommands-cmd-insert" value="<?php p($command['insert']); ?>"></td>
						<td><input type="text" class="smartcommands-cmd-label" value="<?php p($command['label']); ?>"></td>
						<td><input type="text" class="smartcommands-cmd-desc" value="<?php p($command['description']); ?>"></td>
						<td><button type="button" class="smartcommands-cmd-remove"><?php p($l->t('Remove')); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<button type="button" id="smartcommands-global-add"><?php p($l->t('Add command')); ?></button>
		<button type="button" id="smartcommands-global-save"><?php p($l->t('Save global commands')); ?></button>
		<span id="smartcommands-global-status" aria-live="polite"></span>
		<template id="smartcommands-global-template">
			<tr class="smartcommands-cmd-row">
				<td><input type="text" class="smartcommands-cmd-insert"></td>
				<td><input type="text" class="smartcommands-cmd-label"></td>
				<td><input type="text" class="smartcommands-cmd-desc"></td>
				<td><button type="button" class="smartcommands-cmd-remove"><?php p($l->t('Remove')); ?></button></td>
			</tr>
		</template>
	</div>

	<h3><?php p($l->t('Registered commands')); ?></h3>
	<p class="settings-hint">
		<?php p($l->t('Commands each bot has published to the Smart Picker. Published by each bot\'s own account, so this view is read-only.')); ?>
	</p>
	<?php if ($_['manifests'] === []): ?>
		<p class="settings-hint"><?php p($l->t('No bot has published commands yet.')); ?></p>
	<?php else: ?>
		<?php foreach ($_['manifests'] as $manifest): ?>
			<div class="smartcommands-manifest">
				<h4 class="smartcommands-manifest__head">
					<?php p($manifest['name']); ?>
					<code class="smartcommands-manifest__id">/<?php p($manifest['id']); ?></code>
					<?php if ($manifest['owner'] !== ''): ?>
						<span class="smartcommands-manifest__owner"><?php p($l->t('owner: %s', [$manifest['owner']])); ?></span>
					<?php endif; ?>
					<button type="button" class="smartcommands-manifest__delete"
						data-owner="<?php p($manifest['owner']); ?>" data-id="<?php p($manifest['id']); ?>">
						<?php p($l->t('Delete')); ?>
					</button>
				</h4>
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
