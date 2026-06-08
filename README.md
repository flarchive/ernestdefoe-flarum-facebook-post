# Flarum Facebook Auto-Post

[![Floxum](https://floxum.com/extension/ernestdefoe/flarum-facebook-post/badge/name)](https://floxum.com/extension/ernestdefoe/flarum-facebook-post)
[![Version](https://floxum.com/extension/ernestdefoe/flarum-facebook-post/badge/highest-version)](https://floxum.com/extension/ernestdefoe/flarum-facebook-post)
[![Downloads](https://floxum.com/extension/ernestdefoe/flarum-facebook-post/badge/downloads)](https://floxum.com/extension/ernestdefoe/flarum-facebook-post)
[![Review](https://floxum.com/extension/ernestdefoe/flarum-facebook-post/badge/review)](https://floxum.com/extension/ernestdefoe/flarum-facebook-post)
[![License](https://floxum.com/extension/ernestdefoe/flarum-facebook-post/badge/license)](https://floxum.com/extension/ernestdefoe/flarum-facebook-post)

Automatically publishes an excerpt and link to a Facebook **Page** or **Group** whenever a new discussion is created on your Flarum 2 forum.

---

## Features

- Posts the discussion title, a short excerpt, and a link to your chosen Facebook destination
- **Image always displays** — posts as a photo upload so the image appears regardless of Facebook domain verification
- Supports both **Facebook Pages** (Page Access Token) and **Facebook Groups** (User Access Token)
- Toggle on/off from the Admin panel — no code changes needed
- Credentials stored securely in Flarum's settings store
- Optional tag filter — only post discussions in specific categories
- Works with [ernestdefoe/og-image](https://github.com/ernestdefoe/og-image) to use a default image when the post has no embedded images
- Logs success and errors to Flarum's application log (`storage/logs/`)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.3+ |
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

## Configuration Overview

In the Flarum Admin panel go to **Extensions → Facebook Auto-Post** and set:

| Setting | Description |
|---|---|
| **Enable Facebook Auto-Post** | Master on/off switch |
| **Destination Type** | `Facebook Page` or `Facebook Group` |
| **Facebook Page ID** | Numeric Page ID *(Page destination only)* |
| **Page Access Token** | Never-expiring Page token *(Page destination only)* |
| **Facebook Group ID** | Numeric Group ID *(Group destination only)* |
| **Group Access Token** | Long-lived User Access Token *(Group destination only)* |

Only the fields for the selected Destination Type are used — the others are ignored at runtime.

---

## Facebook Setup — Page

This section covers posting to a **Facebook Page** you manage.

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

1. Inside the App Dashboard click **Add Product**
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
4. Set the method to **GET**, type `me/businesses` in the query box and click **Submit**
5. Note the `id` of each Business Account listed
6. For each business ID, run: `{business-id}/owned_pages?fields=id,name,access_token`
7. Find your Page — the `id` field is your **API Page ID** and `access_token` is your **Page Access Token**

> **Why this matters:** New Page Experience pages have a different internal API ID from the number shown in the browser URL. Using the wrong ID causes `(#100) The global id is not allowed` errors.

---

### Step 5 — Extend the Page Token

The token from Step 4 may be short-lived. To get a long-lived token:

1. Copy the `access_token` from the `owned_pages` results
2. Go to **developers.facebook.com/tools/debug/accesstoken** and paste it in
3. Click **Extend Access Token** and copy the new token

Page Access Tokens generated this way do not expire as long as the user who generated them remains an admin of the Page.

---

### Step 6 — Configure the Extension (Page)

1. Go to **Admin → Extensions → Facebook Auto-Post**
2. Set **Destination Type** to **Facebook Page**
3. Fill in:
   - **Facebook Page ID** — the `id` from the `owned_pages` results
   - **Page Access Token** — the extended token from Step 5
4. Toggle **Enable Facebook Auto-Post** to ON and click **Save**

---

## Facebook Setup — Group

This section covers posting to a **Facebook Group** you administer.

### Step 1 — Create a Developer App

Follow the same Steps 1–3 from the Page setup above. If you already have an app from the Page setup, you can reuse it.

---

### Step 2 — Connect the App to Your Group

Facebook requires the app to be installed on the Group before it can post to it:

1. Go to your Facebook Group
2. Click **More → Settings → Apps** (or **Manage Group → Apps** depending on your group layout)
3. Click **Add Apps** and search for your app by name
4. Add it and confirm

> If you do not see an Apps section, make sure the Group type allows third-party integrations. Private groups may restrict this.

---

### Step 3 — Get Your Group ID

The Group ID is the numeric segment of the Group URL:

```
https://www.facebook.com/groups/123456789012345
                                └─────────────┘ this is your Group ID
```

If your group uses a custom URL (e.g. `/groups/mygroupname`), go to the group, open the page source, and search for `"groupID"` to find the numeric value.

---

### Step 4 — Get a User Access Token with publish_to_groups

1. In the App Dashboard go to **Tools → Graph API Explorer**
2. Make sure your app is selected in the top-right dropdown
3. Click **Generate Access Token** and add the permission:
   - `publish_to_groups`
4. Approve and log in (you must log in as a Group admin or member)
5. Copy the token shown — this is your **User Access Token**

---

### Step 5 — Extend the User Token

User Access Tokens expire after approximately 60 days even when extended. To get the maximum lifespan:

1. Copy your User Access Token
2. Go to **developers.facebook.com/tools/debug/accesstoken** and paste it in
3. Click **Extend Access Token** and copy the result

> **Important:** Unlike Page tokens, long-lived User Access Tokens cannot be made permanent. You will need to regenerate and update this token every ~60 days. Set a calendar reminder.

---

### Step 6 — Configure the Extension (Group)

1. Go to **Admin → Extensions → Facebook Auto-Post**
2. Set **Destination Type** to **Facebook Group**
3. Fill in:
   - **Facebook Group ID** — the numeric ID from Step 3
   - **Group Access Token** — the extended User Access Token from Step 5
4. Toggle **Enable Facebook Auto-Post** to ON and click **Save**

---

## How It Works

```
New discussion created (Flarum)
        │
        ▼
PostDiscussionToFacebook::handle()
        │
        ├── Is this post number 1? (only new discussions, not replies)
        ├── Is the extension enabled?
        ├── Destination Type = page or group?
        ├── Are the matching ID + token set?
        ├── Does the discussion pass the tag filter?
        │
        ▼
Is an image available?
(first <img> in post content, or ernestdefoe/og-image default image)
        │
        ├── YES ──► POST /{id}/photos  { url, caption, access_token }
        │                  │
        │           success? ──► Photo post with image on Facebook ✓
        │           failure? ──► falls back to link post (see below)
        │
        └── NO ───► POST /{id}/feed  { message, link, access_token }
                           │
                    Facebook Page/Group feed post with link preview ✓
```

Only the **first post** of each discussion triggers a Facebook update. Replies are ignored.

### Why photo posts?

Facebook suppresses link preview images for API posts from apps that have not completed Meta Business Manager domain verification. Posting via the `/photos` endpoint uploads the image directly to Facebook, so the image always appears regardless of whether your domain is verified.

If the photo upload fails (e.g. the image URL is not publicly accessible), the extension automatically falls back to a standard link post and logs the reason.

---

## Troubleshooting

| Symptom | Likely Cause |
|---|---|
| Nothing posted, no log entry | Extension not enabled or credentials not saved |
| `API error (HTTP 400): Malformed access token` | Token copied incorrectly — re-copy with no extra spaces |
| `API error (HTTP 400): The global id is not allowed` | Wrong Page ID — use the `id` from `owned_pages`, not the URL number |
| `API error (HTTP 403): Missing permission` | Page token missing `pages_manage_posts` / `pages_read_engagement` — regenerate |
| `API error (HTTP 400): #240 Requires a valid user` | Token has expired — generate and extend a new one |
| `API error (HTTP 200)` but nothing appears in Group | App not connected to the Group — complete Step 2 of the Group setup |
| `API error (HTTP 403): publish_to_groups not granted` | Token missing `publish_to_groups` — regenerate with that permission |
| `API error (HTTP 400): User must be an admin` | The account that generated the Group token is not an admin of the Group |
| `me/accounts returns empty` | Page managed via Business Suite — use `me/businesses` then `{business-id}/owned_pages` |
| cURL errors | Server cannot reach `graph.facebook.com` on port 443 |
| Post created but no image | Post has no embedded images and no default image is set — install `ernestdefoe/og-image` and configure a default image URL |
| `Photo post API error` then `Falling back to link post` in logs | Image URL is not publicly accessible or Facebook could not fetch it — check the URL works without authentication |

Logs are written to `storage/logs/flarum.log`. Search for `[FacebookPost]` to find relevant entries.

---

## Image display

When a new discussion is posted the extension looks for an image in this order:

1. The first `<img>` found in the post content
2. The **Default OG Image** from the [ernestdefoe/og-image](https://github.com/ernestdefoe/og-image) extension settings

If an image is found it is posted via Facebook's `/photos` endpoint, which uploads the image directly and guarantees it displays on the Page or Group post. If no image is found (or the photo upload fails) it falls back to a standard link post, which relies on Facebook scraping the OG tags from your forum URL.

To ensure a fallback image is always available, install `ernestdefoe/og-image` and set a **Default OG Image URL** in its settings.

---

## Support

Questions, bug reports, and feature requests:

- **Support forum:** https://ernestdefoe.online
- **Issues:** https://github.com/ernestdefoe/flarum-facebook-post/issues

## License

MIT © Ernestdefoe
