<?php

declare(strict_types=1);

return [
	'routes' => [
		[
			'name' => 'manifest#commands',
			'url' => '/api/commands',
			'verb' => 'GET',
		],
		[
			'name' => 'manifest#upsertBot',
			'url' => '/api/bots/{botId}',
			'verb' => 'PUT',
		],
		[
			'name' => 'manifest#deleteBot',
			'url' => '/api/bots/{botId}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'personalSettings#setDefaultBot',
			'url' => '/api/personal/default-bot',
			'verb' => 'PUT',
		],
		[
			'name' => 'adminSettings#setDefaultBot',
			'url' => '/api/admin/default-bot',
			'verb' => 'PUT',
		],
		[
			'name' => 'adminSettings#setGroupDefault',
			'url' => '/api/admin/group-default',
			'verb' => 'PUT',
		],
		[
			'name' => 'adminSettings#deleteManifest',
			'url' => '/api/admin/manifests/{owner}/{botId}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'adminSettings#setBridge',
			'url' => '/api/admin/bridge',
			'verb' => 'PUT',
		],
	],
];
