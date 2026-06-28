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
