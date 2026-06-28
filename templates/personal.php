<?php

declare(strict_types=1);

/** @var array $_ */
/** @var \OCP\IL10N $l */

script(OCA\SmartCommands\AppInfo\Application::APP_ID, 'personal');
?>
<div class="section" id="smartcommands-personal">
	<h2><?php p($l->t('Agent commands')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Which agent should handle the generic /agent command when you use it in Talk.')); ?>
	</p>
	<select id="smartcommands-default-agent">
		<option value="" <?php if ($_['current'] === '') { p('selected'); } ?>>
			<?php
			if ($_['serverDefault'] !== '') {
				p($l->t('Server default (%s)', [$_['serverDefault']]));
			} else {
				p($l->t('Server default (the bot in the room)'));
			}
			?>
		</option>
		<?php foreach ($_['agents'] as $agentId): ?>
			<option value="<?php p($agentId); ?>" <?php if ($_['current'] === $agentId) { p('selected'); } ?>>
				/<?php p($agentId); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<span id="smartcommands-default-agent-status" aria-live="polite"></span>
</div>
