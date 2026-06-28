// Plain JS (no build step): personal default-bot choice + own command editor.
document.addEventListener('DOMContentLoaded', () => {
	const select = document.getElementById('smartcommands-default-bot')
	const status = document.getElementById('smartcommands-default-bot-status')
	if (select) {
		select.addEventListener('change', async () => {
			status.textContent = '…'
			try {
				const response = await fetch(OC.generateUrl('/apps/smartcommands/api/personal/default-bot'), {
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ bot: select.value }),
				})
				status.textContent = response.ok ? t('smartcommands', 'Saved') : t('smartcommands', 'Could not save')
			} catch (error) {
				status.textContent = t('smartcommands', 'Could not save')
			}
			setTimeout(() => { status.textContent = '' }, 3000)
		})
	}

	// "Your bot commands" editor
	const editor = document.getElementById('smartcommands-personal-editor')
	if (!editor) {
		return
	}
	const userId = editor.dataset.userId ?? ''
	const tbody = document.getElementById('smartcommands-bot-commands')
	const template = document.getElementById('smartcommands-cmd-template')
	const nameInput = document.getElementById('smartcommands-bot-name')
	const editorStatus = document.getElementById('smartcommands-bot-status')
	const manifestUrl = '/apps/smartcommands/api/bots/' + encodeURIComponent(userId)

	function setStatus(text) {
		editorStatus.textContent = text
		if (text !== '…' && text !== '') {
			setTimeout(() => { editorStatus.textContent = '' }, 3000)
		}
	}

	function slugify(value, index) {
		const slug = value.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64)
		return slug || ('cmd' + (index + 1))
	}

	tbody.addEventListener('click', (event) => {
		if (event.target.classList.contains('smartcommands-cmd-remove')) {
			event.target.closest('.smartcommands-cmd-row')?.remove()
		}
	})

	document.getElementById('smartcommands-cmd-add')?.addEventListener('click', () => {
		tbody.appendChild(template.content.cloneNode(true))
	})

	document.getElementById('smartcommands-bot-save')?.addEventListener('click', async () => {
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
		if (commands.length === 0) {
			setStatus(t('smartcommands', 'Add at least one command with insert text'))
			return
		}
		setStatus('…')
		try {
			const response = await fetch(OC.generateUrl(manifestUrl), {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken },
				body: JSON.stringify({ name: nameInput.value.trim() || userId, commands }),
			})
			setStatus(response.ok ? t('smartcommands', 'Saved') : t('smartcommands', 'Could not save'))
		} catch (error) {
			setStatus(t('smartcommands', 'Could not save'))
		}
	})

	document.getElementById('smartcommands-bot-delete')?.addEventListener('click', async () => {
		if (!window.confirm(t('smartcommands', 'Delete all your published commands?'))) {
			return
		}
		setStatus('…')
		try {
			const response = await fetch(OC.generateUrl(manifestUrl), {
				method: 'DELETE',
				headers: { requesttoken: OC.requestToken },
			})
			if (response.ok) {
				tbody.replaceChildren()
				setStatus(t('smartcommands', 'Deleted'))
			} else {
				setStatus(t('smartcommands', 'Could not delete'))
			}
		} catch (error) {
			setStatus(t('smartcommands', 'Could not delete'))
		}
	})
})
