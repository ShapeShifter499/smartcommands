// Plain JS (no build step): saves admin server/group defaults for /agent.
document.addEventListener('DOMContentLoaded', () => {
	const status = document.getElementById('smartcommands-admin-status')

	async function save(url, body) {
		status.textContent = '…'
		try {
			const response = await fetch(OC.generateUrl(url), {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify(body),
			})
			status.textContent = response.ok ? t('smartcommands', 'Saved') : t('smartcommands', 'Could not save')
		} catch (error) {
			status.textContent = t('smartcommands', 'Could not save')
		}
		setTimeout(() => { status.textContent = '' }, 3000)
	}

	document.getElementById('smartcommands-admin-server-default')?.addEventListener('change', (event) => {
		save('/apps/smartcommands/api/admin/default-agent', { agent: event.target.value })
	})

	document.querySelectorAll('.smartcommands-admin-group').forEach((select) => {
		select.addEventListener('change', () => {
			save('/apps/smartcommands/api/admin/group-default', {
				group: select.dataset.group,
				agent: select.value,
			})
		})
	})
})
