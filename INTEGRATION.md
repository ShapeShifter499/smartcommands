# Integrating a bot/agent with Smart Picker Commands

This is a hand-off for an automated agent or bot (e.g. an OpenClaw or Hermes
harness) that wants its commands to show up in the Nextcloud Talk **Smart
Picker** (the `/` menu) and to receive slash commands from Talk rooms.
The `smartcommands` app is the discovery layer.

> Previously this app was called `agentcommands` and used `/agent`,
> `/api/agents/...`, and `agent:` storage keys. It is now `smartcommands`,
> `/bot`, and `/api/bots/...`. **Anything published to the old app does not
> carry over to the new one — re-publish using the calls below.**

---

## Identity rule (read first)

A command manifest is owned by a Nextcloud **user account**, and you may only
publish under your **own** account:

- Your `botId` **must equal your Nextcloud user id**.
- That id must match `^[A-Za-z0-9_-]{1,64}$`.
- Authenticate with an **app password** for that account (HTTP Basic auth).

Throughout, `BASE = https://CLOUD/index.php/apps/smartcommands`
(you can drop `/index.php` if the instance uses pretty URLs).

---

## 1. Publish (or replace) your command list

`PUT {BASE}/api/bots/{yourUserId}` — overwrites your entire list each time.

```bash
curl -u 'YOURUID:APP_PASSWORD' -X PUT \
  -H 'Content-Type: application/json' \
  'https://CLOUD/index.php/apps/smartcommands/api/bots/YOURUID' \
  -d '{
    "name": "Your Display Name",
    "commands": [
      { "id": "status", "label": "Status", "description": "Show current status", "insert": "/YOURUID status" },
      { "id": "resume", "label": "Resume", "description": "Resume the session",  "insert": "/YOURUID resume" }
    ]
  }'
```

Per-command fields:

| field         | required | notes                                                        |
|---------------|----------|--------------------------------------------------------------|
| `id`          | yes      | `^[A-Za-z0-9_-]{1,64}$`; keep unique within your own list     |
| `insert`      | yes      | exact text inserted into the composer when picked (≤1000)    |
| `label`       | no       | short title shown in the picker (≤80; defaults to `id`)      |
| `description` | no       | one-line help under the label (≤240)                         |

Limits / behaviour:
- ≤100 commands per manifest.
- Commands missing a valid `id` or a non-empty `insert` are dropped silently.
- If no valid command survives, the call returns **HTTP 400**.
- `200/201` on success; `403` if `botId` ≠ your authenticated user id.

## 2. Update / delete

- **Update:** just `PUT` again with the new full list (no need to delete first).
- **Delete:** `DELETE {BASE}/api/bots/{yourUserId}` (same auth).

## 3. Inspect what is published (optional)

`GET {BASE}/api/commands` →
`{ "bots": [ ...manifests... ], "filteredByRoom": null }`.
Add `?room=TALK_ROOM_TOKEN` to get only the bots whose Talk bot is enabled in
that conversation.

---

## 4. How commands actually reach you

Publishing only puts entries in the picker. To **receive** a command you also
need a standard Nextcloud Talk **webhook bot**, installed and added to the room
(`occ talk:bot:install` + `occ talk:bot:setup`).

Delivery is **Talk's own bot webhook mechanism** — Talk sends every chat
message in the room (slash-prefixed ones included) to your bot's URL as a
signed ActivityStreams `Create` (`X-Nextcloud-Talk-Random` /
`X-Nextcloud-Talk-Signature`), the same protocol as normal Talk messages.
This app does not forward or duplicate anything (its 0.2.x–0.7.x "bridge"
listeners that did are gone as of 0.8.0). Your bot decides which messages to
act on:

- handle `/{yourUserId} <args>` (users address you explicitly), and
- for the generic `/bot <args>` — every bot in the room receives it — ask
  the app who the resolved target is, and answer only if it is you:

  ```
  GET /apps/smartcommands/api/generic-target?room=ROOM_TOKEN&sender=SENDER_USER_ID
  → {"generic": {"name": "Ember Nymbrand", "target": "ember", "source": "personal"},
     "ambiguous": false}
  ```

  Authenticate as your bot's Nextcloud account (app password). Pass the
  message's `sender` (the actor id from the webhook) so the sender's personal
  default wins; the resolution order is personal → group → server default →
  the single bot in the room. `generic` is `null` with `"ambiguous": true`
  when several bots are present and nothing is configured — stay quiet then.
  This is the same resolution the Smart Picker hint shows users, so the
  behavior matches the promise.

Reply via the normal Talk bot message API.

---

## 5. Gotchas

- The publish/delete endpoints accept **app-password Basic auth directly** — no
  CSRF token and no `OCS-APIRequest` header are required.
- Manifests **persist**; only re-publish when your command list changes, not on
  every restart.
- You can only ever read/edit/delete **your own** manifest (the server enforces
  `botId == authenticated user`). An admin can delete any stale manifest from
  the app's admin settings.
