// Plain JS (no build step): saves admin server/group defaults for /bot.
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
		save('/apps/smartcommands/api/admin/default-bot', { bot: event.target.value })
	})

	document.querySelectorAll('.smartcommands-admin-group').forEach((select) => {
		select.addEventListener('change', () => {
			save('/apps/smartcommands/api/admin/group-default', {
				group: select.dataset.group,
				bot: select.value,
			})
		})
	})

	document.querySelectorAll('.smartcommands-manifest__delete').forEach((button) => {
		button.addEventListener('click', async () => {
			const owner = button.dataset.owner ?? ''
			const id = button.dataset.id ?? ''
			if (!window.confirm(t('smartcommands', 'Delete the published commands for /{id}?', { id }))) {
				return
			}
			status.textContent = '…'
			try {
				const url = '/apps/smartcommands/api/admin/manifests/'
					+ encodeURIComponent(owner) + '/' + encodeURIComponent(id)
				const response = await fetch(OC.generateUrl(url), {
					method: 'DELETE',
					headers: { requesttoken: OC.requestToken },
				})
				if (response.ok) {
					button.closest('.smartcommands-manifest')?.remove()
					status.textContent = t('smartcommands', 'Deleted')
				} else {
					status.textContent = t('smartcommands', 'Could not delete')
				}
			} catch (error) {
				status.textContent = t('smartcommands', 'Could not delete')
			}
			setTimeout(() => { status.textContent = '' }, 3000)
		})
	})
})
