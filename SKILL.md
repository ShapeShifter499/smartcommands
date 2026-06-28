---
name: smartcommands
description: Use when integrating an AI agent with the Smart Picker Commands Nextcloud app, publishing Smart Picker command manifests, setting up Talk bot webhooks, or verifying Talk slash-bridge behavior.
---

# Smart Picker Commands Skill

Use this repo as the Nextcloud-side bridge between Talk, Smart Picker command manifests, and bot webhooks.

## Ground Rules

- Smart Picker Commands ships with no default commands. The picker must stay empty until an authenticated Nextcloud user account for a bot publishes a manifest.
- Each bot setup is expected to have both a dedicated Nextcloud user account and a matching Talk bot account/record. The user account owns the Smart Picker manifest; the Talk bot account/record receives signed webhook calls and posts replies.
- Each bot's Nextcloud user account can only publish or delete the manifest whose id matches its authenticated Nextcloud user id.
- Do not store secrets in manifests, docs, commits, logs, or chat. Use Nextcloud app passwords for manifest publishing and Talk bot secrets for webhook signatures.
- Do not assume source changes are live. After copying a changed app checkout into Nextcloud, run `php occ upgrade` and restart the Nextcloud container or PHP-FPM process to clear old app/event-listener state.
- Do not add new command prefixes to docs until the app code actually routes them. Current slash-bridge aliases are `/bot`, `/nymble`, and `/aurel`.

## Attach A Bot

1. Create or identify a dedicated Nextcloud user account for the bot.
2. Create a Nextcloud app password for that account.
3. Install or update the bot's matching Talk webhook bot:

```bash
php occ talk:bot:install \
  -f webhook -f response -f reaction -- \
  "<bot name>" "<shared-secret>" "https://bot.example.com/nextcloud-talk-webhook" \
  "<description>"
```

4. Add the bot to the Talk room:

```bash
php occ talk:bot:list --output=json_pretty
php occ talk:bot:setup <bot-id> <room-token>
```

5. If the bot already exists and only its enabled features need changing, update it in place:

```bash
php occ talk:bot:state <bot-id> 1 \
  --feature webhook \
  --feature response \
  --feature reaction
```

## Publish Commands

Publish the command menu as the bot's Nextcloud user account. The `{bot-id}` path segment must match the authenticated Nextcloud user id. The inserted text should be whatever the matching Talk bot actually supports in Talk.

```bash
curl -u 'bot-user:app-password' \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -X PUT \
  'https://cloud.example.com/apps/smartcommands/api/bots/bot-user' \
  --data '{
    "name": "Bot Display Name",
    "commands": [
      {
        "id": "status",
        "label": "Status",
        "description": "Show current bot status.",
        "insert": "/bot status"
      }
    ]
  }'
```

Manifest rules:

- `id`: stable command id using letters, numbers, underscores, or hyphens.
- `label`: short Smart Picker label.
- `description`: short explanation for the picker.
- `insert`: exact text inserted into the composer.

Remove a manifest with:

```bash
curl -u 'bot-user:app-password' \
  -H 'OCS-APIRequest: true' \
  -X DELETE \
  'https://cloud.example.com/apps/smartcommands/api/bots/bot-user'
```

## Verify

Check the manifest endpoint:

```bash
curl -u 'bot-user:app-password' \
  -H 'OCS-APIRequest: true' \
  'https://cloud.example.com/apps/smartcommands/api/commands'
```

Then test from Talk:

1. Open a room containing the bot's Talk bot.
2. Open the Smart Picker and select the bot command.
3. Send the inserted text.
4. Confirm Nextcloud logs show the Smart Picker Commands slash bridge invoking the webhook with `statusCode: "200"`.

Useful log filter:

```bash
tail -n 120 /var/www/html/data/nextcloud.log \
  | grep -i "Smart Picker Commands slash bridge\|invalid signature\|statusCode"
```

## Optional OpenClaw Poller Fallback

Use `contrib/openclaw/nextcloud-talk-poller` only as a fallback when Talk app/event hooks do not reliably wake the bot webhook. It polls the Talk chat API, avoids moving the room read marker, waits a handoff grace period, skips replay if a bot already answered, signs an ActivityStreams `Create` payload with the Talk bot secret, and posts to the OpenClaw Nextcloud Talk webhook.

Install the generic examples outside the repo:

```bash
install -Dm755 contrib/openclaw/nextcloud-talk-poller ~/.local/bin/nextcloud-talk-poller
install -Dm644 contrib/openclaw/openclaw-nextcloud-talk-poller.service ~/.config/systemd/user/openclaw-nextcloud-talk-poller.service
install -Dm600 contrib/openclaw/nextcloud-talk-poller.env.example ~/.config/smartcommands/nextcloud-talk-poller.env
```

Configure the private env file with:

- `NEXTCLOUD_BASE_URL`
- `NEXTCLOUD_TALK_ROOM`
- `NEXTCLOUD_TALK_API_USER`
- `NEXTCLOUD_TALK_API_PASSWORD_FILE`
- `NEXTCLOUD_TALK_BOT_SECRET_FILE`
- `NEXTCLOUD_TALK_WEBHOOK_URL`
- `NEXTCLOUD_TALK_POLLER_STATE_DIR`
- `NEXTCLOUD_TALK_ALLOWED_SENDERS`
- `NEXTCLOUD_TALK_COMMAND_PREFIXES`

Keep `NEXTCLOUD_TALK_ALLOWED_SENDERS` narrow. Run `nextcloud-talk-poller --once --dry-run` before enabling the user service.

## Update Installed App

After editing this app source:

```bash
npm run build
php occ upgrade
```

Restart Nextcloud or PHP-FPM before live Talk tests. If Talk still behaves like old code, assume stale PHP/opcache/app-bootstrap state until restart proves otherwise.
