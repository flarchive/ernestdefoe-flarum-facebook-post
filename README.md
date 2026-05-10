# Flarum Facebook Page Auto-Post

Automatically publishes a link + excerpt to your Facebook Page whenever a new discussion is created on your Flarum 2 forum.

---

## Features

- Posts the discussion title, a short excerpt, and a direct link to your Facebook Page
- Toggle on/off from the Admin panel — no code changes needed
- Stores the Page Access Token securely in Flarum's encrypted settings store
- Logs success and errors to Flarum's application log (`storage/logs/`)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.1 |
| Flarum | ^2.0 |
| PHP extension | `curl` |

---

## Installation

### 1. Install via Composer

```bash
composer require ernestdefoe/flarum-facebook-post
php flarum migrate
php flarum cache:clear
```

### 2. Build the admin JS

```bash
cd extensions/yourvendor-facebook-post/js
npm install
npm run build
```

---

## Configuration

### Step A — Create a Meta App

1. Go to [Meta for Developers](https://developers.facebook.com) and create a new App (type: **Business**).
2. Add the **Facebook Login for Business** and **Pages API** products.

### Step B — Generate a Page Access Token

1. In the App Dashboard → **Tools → Graph API Explorer**.
2. Select your App and your **Facebook Page** from the dropdowns.
3. Request these permissions:
   - `pages_manage_posts`
   - `pages_read_engagement`
4. Click **Generate Access Token** and go through the OAuth flow.
5. **Convert to a long-lived / never-expiring token** using the Token Debugger:
   - Exchange the short-lived token for a long-lived User token (60 days).
   - Use the long-lived User token to request a **Page token** — Page tokens do not expire.

```
GET https://graph.facebook.com/v19.0/{page-id}?fields=access_token&access_token={long-lived-user-token}
```

### Step C — Configure the Extension

1. In Flarum Admin → **Extensions → Facebook Page Auto-Post**.
2. Fill in:
   - **Facebook Page ID** — the numeric ID of your Page.
   - **Page Access Token** — the never-expiring Page token from Step B.
3. Toggle **Enable Facebook Auto-Post** to ON.
4. Save.

---

## How It Works

```
New Post event (Flarum)
        │
        ▼
PostDiscussionToFacebook::handle()
        │
        ├── Is this post number 1? (first post = new discussion)
        ├── Is the extension enabled?
        ├── Are Page ID + Access Token set?
        │
        ▼
POST https://graph.facebook.com/v19.0/{pageId}/feed
   { message, link, access_token }
        │
        ▼
Facebook Page Feed ✓
```

Only the **first post** of each discussion triggers a Facebook update. Replies are ignored.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Nothing posted | Verify extension is enabled and token/page ID are saved |
| `API error (HTTP 190)` | Token is expired — regenerate a never-expiring Page token |
| `API error (HTTP 200)` | Token lacks `pages_manage_posts` permission |
| cURL errors | Ensure the server can reach `graph.facebook.com` (port 443) |

Logs are written to `storage/logs/flarum.log`.

---

## License

MIT © Ernestdefoe
