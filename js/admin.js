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

	document.querySelectorAll('.smartcommands-bridge-toggle').forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			save('/apps/smartcommands/api/admin/bridge', {
				bridge: checkbox.dataset.bridge,
				enabled: checkbox.checked,
			})
		})
	})

	// Global commands editor (admin-authored, instance-wide).
	const globalEditor = document.getElementById('smartcommands-global-editor')
	if (globalEditor) {
		const tbody = document.getElementById('smartcommands-global-commands')
		const template = document.getElementById('smartcommands-global-template')
		const globalStatus = document.getElementById('smartcommands-global-status')

		const setGlobalStatus = (text) => {
			globalStatus.textContent = text
			if (text !== '…' && text !== '') {
				setTimeout(() => { globalStatus.textContent = '' }, 3000)
			}
		}

		const slugify = (value, index) => {
			const slug = value.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64)
			return slug || ('cmd' + (index + 1))
		}

		tbody.addEventListener('click', (event) => {
			if (event.target.classList.contains('smartcommands-cmd-remove')) {
				event.target.closest('.smartcommands-cmd-row')?.remove()
			}
		})

		document.getElementById('smartcommands-global-add')?.addEventListener('click', () => {
			tbody.appendChild(template.content.cloneNode(true))
		})

		document.getElementById('smartcommands-global-save')?.addEventListener('click', async () => {
			const commands = []
			tbody.querySelectorAll('.smartcommands-cmd-row').forEach((row, index) => {
				const insert = row.querySelector('.smartcommands-cmd-insert').value.trim()
				if (insert === '') {
					return
				}
				const label = row.querySelector('.smartcommands-cmd-label').value.trim()
				commands.push({
					id: slugify(label || insert, index),
					label,
					description: row.querySelector('.smartcommands-cmd-desc').value.trim(),
					insert,
				})
			})
			setGlobalStatus('…')
			try {
				const response = await fetch(OC.generateUrl('/apps/smartcommands/api/admin/global-commands'), {
					method: 'PUT',
					headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
					body: JSON.stringify({ commands }),
				})
				setGlobalStatus(response.ok ? t('smartcommands', 'Saved') : t('smartcommands', 'Could not save'))
			} catch (error) {
				setGlobalStatus(t('smartcommands', 'Could not save'))
			}
		})
	}

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
