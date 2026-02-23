# Akeneo Extension Catalog - User Guide

A web application for browsing and deploying UI extensions to your Akeneo PIM instance.

---

## Table of Contents

1. [Setup](#1-setup)
2. [Connecting Your PIM](#2-connecting-your-pim)
3. [Browsing the Catalog](#3-browsing-the-catalog)
4. [Deploying an Extension](#4-deploying-an-extension)
5. [Updating an Extension](#5-updating-an-extension)
6. [Removing an Extension](#6-removing-an-extension)
7. [Extension Catalog Format](#7-extension-catalog-format)
8. [API Constraints & Limits](#8-api-constraints--limits)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- An Akeneo PIM instance (with admin access to register a custom app)

### Installation

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### Configuration

Copy or edit the `.env` file at the project root:

```env
APP_ENV=dev
APP_SECRET=change_me_to_a_random_string

# Akeneo OAuth credentials (from your custom app in Akeneo PIM)
CLIENT_ID=your-client-id
CLIENT_SECRET=your-client-secret

# Database (SQLite by default)
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

To obtain `CLIENT_ID` and `CLIENT_SECRET`:

1. Log into your Akeneo PIM as an administrator
2. Go to **Connect > App Store > Custom Apps**
3. Create a new custom app with:
   - **Activate URL:** `http://your-app-host/activate`
   - **Callback URL:** `http://your-app-host/callback`
4. Copy the generated Client ID and Client Secret into your `.env`

### Starting the Server

```bash
php -S localhost:8000 -t public/
```

---

## 2. Connecting Your PIM

The app uses OAuth 2.0 to connect to your PIM. The flow is initiated from the PIM side:

1. In your Akeneo PIM, go to **Connect > App Store**
2. Find and install your custom app
3. The PIM redirects you to the app's activate URL
4. You are redirected back to the PIM to authorize the `manage_extensions` scope
5. After granting access, you land on the extension catalog

Your session stays active until you close the browser. If it expires, reinstall the app from the PIM's App Store.

---

## 3. Browsing the Catalog

The catalog page (`/catalog`) lists all available extensions fetched from a GitHub-hosted `catalog.json` file.

Each extension card shows:

- **Display name** and technical name
- **Version** number
- **Position** (where the extension appears in the PIM UI)
- **Description** (if provided)
- **Deployment status** badge (if previously deployed)

### Refreshing the Catalog

The catalog is cached for 5 minutes. To force a refresh, click the **Refresh Catalog** button at the top of the page.

### Deployment Status Badges

| Badge | Meaning |
|-------|---------|
| **Deployed** (green) | Extension is active on your PIM |
| **Pending** (orange) | Deployment is in progress |
| **Failed** (red) | Last operation failed (error details shown below the badge) |
| **Deleted** (gray) | Extension was removed from your PIM |

---

## 4. Deploying an Extension

1. Browse the catalog and find the extension you want
2. Click the **Deploy** button on its card
3. The app downloads the extension's JavaScript bundle from GitHub and uploads it to your PIM via the API
4. On success, the status changes to **Deployed**
5. On failure, the status changes to **Failed** with an error message

---

## 5. Updating an Extension

When a new version of an extension is available in the catalog:

1. Find the already-deployed extension in the catalog
2. Click the **Update** button
3. The app downloads the new version and pushes it to your PIM
4. The deployment status reflects the result

---

## 6. Removing an Extension

1. Find the deployed extension in the catalog
2. Click the **Undeploy** button
3. Confirm the removal in the dialog
4. The extension is removed from your PIM and the status changes to **Deleted**

You can redeploy a removed extension at any time by clicking **Deploy** again.

---

## 7. Extension Catalog Format

Extensions are defined in a `catalog.json` file hosted on GitHub. Each entry has the following fields:

```json
{
  "slug": "my-extension",
  "name": "my_extension_name",
  "type": "sdk_script",
  "position": "pim.product-grid.action-bar",
  "version": "1.0.0",
  "defaultLabel": "My Extension",
  "labels": {
    "en_US": "My Extension",
    "fr_FR": "Mon Extension"
  },
  "description": "What this extension does",
  "distPath": "my-extension/dist/my-extension-v1.0.0.js",
  "originalFileName": "my-extension.js",
  "customVariables": {
    "TARGET_URL": "https://target-pim.example.com",
    "TARGET_TOKEN": "your-api-token"
  },
  "documentationUrl": "https://github.com/example/my-extension#readme"
}
```

### Field Reference

| Field | Required | Default | Description |
|-------|----------|---------|-------------|
| `slug` | Yes | - | Unique identifier (used in URLs and database) |
| `name` | Yes | - | Technical name sent to the PIM API (snake_case) |
| `type` | No | `sdk_script` | Extension type |
| `position` | No | `""` | UI location in the PIM (e.g. `pim.product-grid.action-bar`) |
| `version` | No | `1.0.0` | Semantic version |
| `defaultLabel` | No | value of `name` | Display name shown in the PIM |
| `labels` | No | `{}` | Locale-specific display names (e.g. `en_US`, `fr_FR`) |
| `description` | No | `null` | Description shown in the catalog UI |
| `distPath` | Yes | - | Path to the JavaScript bundle in the GitHub repo |
| `originalFileName` | No | basename of `distPath` | Filename used in the upload |
| `customVariables` | No | `null` | JSON object of default custom variables sent as `configuration[custom_variables]` on deploy. Displayed in the catalog UI for users to copy. |
| `documentationUrl` | No | `null` | URL to the extension's documentation. Displayed as a link in the catalog UI. |

---

## 8. API Constraints & Limits

These constraints are enforced by the Akeneo PIM API and apply to **all extensions**.

### Label Length

| Field | Max Length |
|-------|-----------|
| `defaultLabel` | **30 characters** |
| `labels` (per locale) | **30 characters** |

Labels exceeding 30 characters are automatically truncated by the app. To avoid unexpected truncation, keep all labels within this limit in your `catalog.json`.

### Required Fields for Deployment

The following fields must be non-empty for the PIM API to accept a deployment:

| Field | Sent As | Notes |
|-------|---------|-------|
| `name` | `name` | Technical extension name, must not be blank |
| `type` | `type` | Extension type (typically `sdk_script`) |
| `position` | `position` | UI position string |
| `defaultLabel` | `configuration[default_label]` | Display label, max 30 chars |
| JavaScript file | `file` | The built extension bundle |

### Optional Fields

| Field | Sent As | Notes |
|-------|---------|-------|
| `version` | `version` | Semantic version string |
| `labels` | `configuration[labels][locale]` | One field per locale, max 30 chars each |
| `customVariables` | `configuration[custom_variables]` | JSON-encoded object of custom variables |

### API Endpoint Reference

| Action | Method | Endpoint |
|--------|--------|----------|
| Deploy | POST | `/api/rest/v1/ui-extensions` |
| Update | POST | `/api/rest/v1/ui-extensions/{uuid}` (with `_method=PATCH`) |
| Undeploy | DELETE | `/api/rest/v1/ui-extensions/{uuid}` |

Full API documentation: https://api.akeneo.com/advanced-extensions/api-deployment.html

---

## 9. Troubleshooting

### Common Errors

| Error Message | Cause | Solution |
|---------------|-------|----------|
| "Please connect your PIM first." | Session expired or not authenticated | Reinstall the app from the PIM's App Store |
| "Extension not found." | Extension slug missing from catalog | Click **Refresh Catalog**, or check catalog.json |
| "No existing deployment found to update/remove." | No prior deployment in the database | Deploy the extension first |
| "Validation failed." | One or more fields rejected by the PIM API | Check the detailed error: labels may be too long, required fields may be missing, or the extension name may conflict with an existing one |

### Session Expires

Sessions are tied to your browser. If you see "Please connect your PIM first", go back to your PIM's App Store and reinstall the app to start a new session.

### Catalog Not Updating

The catalog is cached for 5 minutes. Click **Refresh Catalog** to force a re-fetch from GitHub. If changes still don't appear, verify that the `catalog.json` was pushed to the `main` branch.

### Database Reset

If the database gets into a bad state:

```bash
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

This clears all deployment records. Extensions already deployed on your PIM are not affected.
