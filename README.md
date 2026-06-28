# Smart Picker Commands

Smart Picker Commands is a Nextcloud Smart Picker app for AI-agent bot commands.

The goal is to give Talk users a native `/` picker entry named **Agent commands**. The picker can list commands from OpenClaw first, and later from any agent service that publishes a simple manifest.

## Current Status

This is an early scaffold:

- registers a discoverable reference provider: `smartcommands`
- loads a custom Smart Picker element
- exposes a local command manifest endpoint at `/apps/smartcommands/api/commands`
- lets authenticated Nextcloud user accounts publish command manifests
- inserts Talk-ready command text from explicitly registered agent manifests
- experimentally bridges Talk messages like `/nymble status` to the matching configured Talk bot webhook
- can also follow Nextcloud's in-process bot pattern with a `nextcloudapp://smartcommands` event bot, similar to `nextcloud/command_bot`

The app does not ship opinionated default commands. The Smart Picker menu stays empty until an authenticated Nextcloud user account for an agent publishes a manifest. This keeps local command surfaces owned by the agents that actually support them.
The slash bridge handles the Talk behavior where slash-style messages can be stored as normal messages without waking configured bot webhooks: when Talk stores `/<agent> ...` (or the generic `/agent ...`), the app signs and forwards a standard Talk bot webhook payload to the matching bot configured in that conversation.

Valid slash targets are derived from the registered agent manifests — publishing a manifest for a new agent (e.g. `ember`) makes `/ember ...` routable with no app code change. The generic `/agent` alias resolves to the app value `default_agent_target` (default: `nymble`):

```bash
php occ config:app:set smartcommands default_agent_target --value nymble
```

The Smart Picker command list is room-aware: when opened inside a Talk conversation, only agents whose webhook bot is enabled in that conversation are listed. Outside a conversation context the full registry is shown.

Smart Picker Commands expects each agent to be set up with both a dedicated Nextcloud user account and a matching Talk bot account/record:

- the Nextcloud user account, usually named after the agent, owns the Smart Picker command manifest through username/app-password authentication
- the Talk bot account/record in the relevant room receives signed webhook calls and posts replies

For example, a `nymble` Nextcloud user publishes `/apps/smartcommands/api/agents/nymble`, while the `Nymble` Talk bot receives `/nymble ...` bridge webhooks in rooms where that bot is configured.

### Experimental Talk event bot bridge

Nextcloud's `command_bot` app uses a local Talk event bot instead of the deprecated `talk_commands` table. To try the same path, install Smart Picker Commands as a Talk event bot, set it up in the room, and keep the existing webhook bot such as `Nymble` configured in that same room:

```bash
SECRET="$(openssl rand -hex 64)"
php occ talk:bot:install --feature event \
  "Smart Picker Commands" "$SECRET" "nextcloudapp://smartcommands" \
  "Bridge /nymble-style Talk messages to configured agent webhook bots"

php occ talk:bot:list --output=json_pretty
php occ talk:bot:setup <agent-commands-bot-id> <room-token>
```

When the event bot receives a `/<registered-agent> ...` or `/agent ...` message, Smart Picker Commands looks for the matching webhook bot in that room and forwards the normal signed Talk bot payload to that bot's webhook URL.

### Talk bot administration notes

Configured webhook bots should usually enable all features that OpenClaw may use:

```bash
php occ talk:bot:state <bot-id> 1 \
  --feature webhook \
  --feature response \
  --feature reaction
```

Use `talk:bot:state` when only the enabled state or feature list needs to change. It avoids uninstalling and reinstalling a working bot registration.

After replacing the installed app checkout in a running Nextcloud container, run `php occ upgrade` and restart the Nextcloud container or PHP-FPM process before testing Talk events. Nextcloud can otherwise keep an old event-listener registration loaded through PHP/opcache even when the files and installed app version are already updated.

## Development

```bash
npm install
npm run build
```

Agents picking up this project should start with [`SKILL.md`](SKILL.md). It summarizes the no-defaults command policy, Talk bot setup, manifest publishing, and live verification steps.

For a local Nextcloud checkout, install the app directory as `apps/smartcommands`, then enable it:

```bash
php occ app:enable smartcommands
```

When replacing an already-installed checkout after an app version bump, run the upgrade step too:

```bash
php occ upgrade
```

## Optional OpenClaw Talk Poller Fallback

The normal path for Smart Picker Commands is still Nextcloud Talk events and signed Talk bot webhooks. The files in [`contrib/openclaw`](contrib/openclaw) are an optional OpenClaw-side fallback for rooms where Talk app/event hooks are delayed, stale, or do not reliably fire for command-looking messages.

The fallback poller:

- reads recent Talk room messages through the Talk chat OCS API without setting the read marker
- only replays messages from explicitly configured sender ids
- only replays messages whose text starts with configured command prefixes
- waits a handoff grace period so the normal Talk event bridge can answer first
- skips fallback replay when a bot reply appears before the next user message
- signs a standard ActivityStreams `Create` payload with the configured Talk bot secret
- posts that payload to the local OpenClaw Nextcloud Talk webhook
- stores processed message state in a private state file written with mode `0600`

Install the example files somewhere outside the app checkout:

```bash
install -Dm755 contrib/openclaw/nextcloud-talk-poller ~/.local/bin/nextcloud-talk-poller
install -Dm644 contrib/openclaw/openclaw-nextcloud-talk-poller.service ~/.config/systemd/user/openclaw-nextcloud-talk-poller.service
install -Dm600 contrib/openclaw/nextcloud-talk-poller.env.example ~/.config/smartcommands/nextcloud-talk-poller.env
```

Edit `~/.config/smartcommands/nextcloud-talk-poller.env` and set at least:

```text
NEXTCLOUD_BASE_URL=https://cloud.example.com
NEXTCLOUD_TALK_ROOM=room-token
NEXTCLOUD_TALK_API_USER=agent-user
NEXTCLOUD_TALK_API_PASSWORD_FILE=/path/to/agent-nextcloud-app-password.txt
NEXTCLOUD_TALK_BOT_SECRET_FILE=/path/to/talk-bot-shared-secret.txt
NEXTCLOUD_TALK_ALLOWED_SENDERS=users/example-user
NEXTCLOUD_TALK_COMMAND_PREFIXES=/agent,/agent-user
```

Then run a dry one-shot check before enabling the service:

```bash
nextcloud-talk-poller --once --dry-run
systemctl --user enable --now openclaw-nextcloud-talk-poller.service
```

Use this as a safety net, not as a replacement for the Smart Picker Commands app bridge. If the normal bridge starts answering reliably, the handoff grace and bot-reply check should keep the poller from producing duplicate replies.

## App Store Prep

Before publishing, replace the repository URLs in `appinfo/info.xml`, test against the target Nextcloud versions, add screenshots, add translations, and generate a signed release archive following Nextcloud app store requirements.

## Manifest Direction

The picker lists manifests published by authenticated Nextcloud user accounts. There is no built-in command manifest; each agent owns the labels and inserted command text it advertises. An agent's Nextcloud user account can only publish or delete the manifest whose id matches its authenticated Nextcloud user id, so one agent cannot overwrite another agent's command list.

Agent user accounts can publish or replace their command list with a normal app password:

```bash
curl -u 'agent-user:app-password' \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -X PUT \
  'https://cloud.example.com/apps/smartcommands/api/agents/agent-user' \
  --data '{
    "name": "Agent Display Name",
    "commands": [
      {
        "id": "help",
        "label": "Help",
        "description": "Show this agent command help.",
        "insert": "/agent-user help"
      }
    ]
  }'
```

The app stores manifests under the publishing Nextcloud user account, so an agent can update or delete its own list without admin rights:

```bash
curl -u 'agent-user:app-password' \
  -H 'OCS-APIRequest: true' \
  -X DELETE \
  'https://cloud.example.com/apps/smartcommands/api/agents/agent-user'
```

The manifest contract is intentionally small:

- `id`: stable command id, letters/numbers/underscore/hyphen only
- `label`: short picker label
- `description`: short picker description
- `insert`: text the picker inserts into the composer

Future versions can evolve this into one of these:

- admin-configured agent manifests
- a signed local JSON file
- a trusted service endpoint per agent
- a small app settings page for command registration
