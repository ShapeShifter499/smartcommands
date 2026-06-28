// Plain JS (no build step): saves the personal default-agent choice.
document.addEventListener('DOMContentLoaded', () => {
	const select = document.getElementById('smartcommands-default-agent')
	const status = document.getElementById('smartcommands-default-agent-status')
	if (!select) {
		return
	}

	select.addEventListener('change', async () => {
		status.textContent = '…'
		try {
			const response = await fetch(OC.generateUrl('/apps/smartcommands/api/personal/default-agent'), {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ agent: select.value }),
			})
			status.textContent = response.ok ? t('smartcommands', 'Saved') : t('smartcommands', 'Could not save')
		} catch (error) {
			status.textContent = t('smartcommands', 'Could not save')
		}
		setTimeout(() => { status.textContent = '' }, 3000)
	})
})
