import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
  NcCustomPickerRenderResult,
  registerCustomPickerElement,
} from '@nextcloud/vue/functions/registerReference'

const PROVIDER_ID = 'smartcommands'

class SmartCommandsPicker extends HTMLElement {
  connectedCallback() {
    this.renderLoading()
    this.loadCommands()
  }

  async loadCommands() {
    try {
      const room = currentTalkRoomToken()
      const url = generateUrl('/apps/smartcommands/api/commands')
      const response = await axios.get(url, { params: room ? { room } : {} })
      this.renderCommands(response.data.bots ?? [], response.data.filteredByRoom ?? null)
    } catch (error) {
      this.renderError(error)
    }
  }

  renderLoading() {
    this.innerHTML = `<div class="smartcommands-picker">${escapeHtml(t('smartcommands', 'Loading commands...'))}</div>`
  }

  renderError() {
    this.innerHTML = `<div class="smartcommands-picker smartcommands-picker--error">${escapeHtml(t('smartcommands', 'Commands could not be loaded.'))}</div>`
  }

  renderCommands(bots, filteredByRoom) {
    if (bots.length === 0) {
      const message = filteredByRoom
        ? t('smartcommands', 'No bots are enabled in this conversation.')
        : t('smartcommands', 'No bot commands configured.')
      this.innerHTML = `<div class="smartcommands-picker">${escapeHtml(message)}</div>`
      return
    }

    const body = bots.flatMap((bot) => {
      const commands = bot.commands ?? []
      return [
        `<div class="smartcommands-picker__bot">${escapeHtml(bot.name ?? bot.id)}</div>`,
        ...commands.map((command) => this.renderCommand(command)),
      ]
    }).join('')

    this.innerHTML = `<div class="smartcommands-picker">${body}</div>`

    this.querySelectorAll('[data-bot-command]').forEach((button) => {
      button.addEventListener('click', () => {
        this.dispatchCommand(button.dataset.botCommand ?? '')
      })
    })
  }

  renderCommand(command) {
    const insert = command.insert ?? ''
    return `
      <button type="button" class="smartcommands-picker__command" data-bot-command="${escapeAttribute(insert)}">
        <span class="smartcommands-picker__label">${escapeHtml(command.label ?? command.id ?? insert)}</span>
        <span class="smartcommands-picker__description">${escapeHtml(command.description ?? insert)}</span>
      </button>
    `
  }

  dispatchCommand(commandText) {
    this.dispatchEvent(new CustomEvent('submit', {
      bubbles: true,
      composed: true,
      detail: commandText,
    }))
  }
}

customElements.define('smartcommands-picker', SmartCommandsPicker)
registerCustomPickerElement(PROVIDER_ID, (el) => {
  const picker = document.createElement('smartcommands-picker')
  el.appendChild(picker)
  return new NcCustomPickerRenderResult(picker)
}, (el) => {
  el.replaceChildren()
}, 'normal')

function currentTalkRoomToken() {
  // Talk web UI routes look like /index.php/call/{token} or /call/{token}.
  const match = window.location.pathname.match(/\/call\/([A-Za-z0-9]{1,64})(?:\/|$)/)
  return match ? match[1] : null
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll('`', '&#096;')
}
