# Flarum Facebook Page Auto-Post

Automatically publishes an excerpt and link to your Facebook Page whenever a new discussion is created on your Flarum 2 forum.

---

## Features

- Posts the discussion title, a short excerpt, and a direct link to your Facebook Page
- Toggle on/off from the Admin panel — no code changes needed
- Stores the Page Access Token securely in Flarum's settings store
- Logs success and errors to Flarum's application log (`storage/logs/`)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP |  8.3+ |
| Flarum | ^2.0 |
| PHP extension | `curl` |

---

## Installation

```bash
composer require ernestdefoe/flarum-facebook-post
php flarum migrate
php flarum cache:clear
```

---

## Facebook Setup

This extension requires a Facebook Developer App and a Page Access Token. Follow every step carefully — skipping any step is the most common cause of errors.

### Step 1 — Create a Facebook Developer Account

1. Go to **developers.facebook.com**
2. Click **Get Started** and log in with the Facebook account that manages your Page
3. Complete the free developer registration

---

### Step 2 — Create a Facebook App

1. Go to **My Apps → Create App**
2. When asked what the app will do, select **Other → Next**
3. For app type select **None → Next**
4. Give it any name (e.g. "My Forum") and click **Create App**

---

### Step 3 — Add Facebook Login to the App

1. Inside the App Dashboard, click **Add Product**
2. Find **Facebook Login** and click **Set Up → Web**
3. Enter your forum URL and save

This step is required before permissions can be granted to tokens.

---

### Step 4 — Get Your Page ID (Important — read carefully)

The numeric ID in your Facebook Page URL (`profile.php?id=XXXXXXX`) is **not** always the correct API Page ID. To get the real API Page ID:

1. In the App Dashboard go to **Tools → Graph API Explorer**
2. Click **Generate Access Token** and add these permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
   - `business_management`
3. Approve and log in
4. Make sure the method is set to **GET**
5. In the query box type `me/businesses` and click **Submit**
6. You will see one or more Business Accounts listed — note the `id` of each one
7. For each business ID, run: `{business-id}/owned_pages?fields=id,name,access_token`
8. Find your Page in the results — the `id` field is your real **API Page ID** and the `access_token` field is your **Page Access Token**

> **Why this matters:** New Page Experience pages have a different internal API ID from the number shown in the browser URL. Using the wrong ID causes `(#100) The global id is not allowed` errors.

---

### Step 5 — Extend the Token

The token from Step 4 may be short-lived. To get a long-lived token:

1. Copy the `access_token` from the `owned_pages` results
2. Go to **developers.facebook.com/tools/debug/accesstoken** and paste it in
3. Click **Extend Access Token** and copy the new token

---

### Step 6 — Configure the Extension

1. Go to your Flarum Admin panel → **Extensions → Facebook Page Auto-Post**
2. Fill in:
   - **Facebook Page ID** — the `id` from the `owned_pages` results (Step 4)
   - **Page Access Token** — the extended token from Step 5
3. Toggle **Enable Facebook Auto-Post** to ON
4. Click **Save**

---

## How It Works

```
New discussion created (Flarum)
        │
        ▼
PostDiscussionToFacebook::handle()
        │
        ├── Is this post number 1? (first post = new discussion only)
        ├── Is the extension enabled?
        ├── Are Page ID + Access Token set?
        │
        ▼
POST https://graph.facebook.com/v19.0/{pageId}/feed
   { message, access_token }
        │
        ▼
Facebook Page Feed ✓
```

Only the **first post** of each discussion triggers a Facebook update. Replies are ignored.

Facebook automatically generates a link preview from the discussion URL's Open Graph tags.

---

## Troubleshooting

| Symptom | Likely Cause |
|---|---|
| Nothing posted, no log entry | Extension not enabled or token/page ID not saved |
| `API error (HTTP 400): Malformed access token` | Token was copied incorrectly — re-copy it with no extra spaces |
| `API error (HTTP 400): The global id is not allowed` | Wrong Page ID — use the `id` from `owned_pages`, not the URL number |
| `API error (HTTP 403): Missing permission` | Token does not have `pages_manage_posts` + `pages_read_engagement` — regenerate with all required permissions |
| `API error (HTTP 400): #240 Requires a valid user` | Token has expired — generate a new one and extend it |
| `me/accounts returns empty` | Page is managed via Business Suite — use `me/businesses` then `{business-id}/owned_pages` instead |
| cURL errors | Server cannot reach `graph.facebook.com` on port 443 |

Logs are written to `storage/logs/flarum.log`. Search for `[FacebookPost]` to find relevant entries.

---

## License

MIT © Ernestdefoe
