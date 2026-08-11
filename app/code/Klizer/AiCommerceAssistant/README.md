# Klizer_AiCommerceAssistant (Magento module)

Single Magento 2 module for:

- **Storefront AI search** — header AI icon, clarifying questions, matchable PLP  
- **Admin Search ROI analytics** — zero-result rate, hybrid/semantic share, CTR, top queries  

Product data always comes from Magento; conversation + analytics run on the Node API (`:3001`).

## Quick Magento setup

1. Start the Node API on `:3001` (see Node README). For analytics: `DATABASE_URL` + `npm run db:migrate:analytics`.
2. Enable this module:

```bash
cd /var/www/html/magento248p4
php bin/magento module:enable Klizer_AiCommerceAssistant
php bin/magento module:disable Klizer_AiCommerceAnalytics   # if the old split module was installed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

3. **Stores → Configuration → Klizer → AI Commerce Assistant**
   - Enable: **Yes**
   - AI API Base URL: `http://127.0.0.1:3001`
   - Redirect to External Assistant UI: **No** (on-site AI PLP)
   - See **Search Analytics** settings below
4. Storefront: use the **AI** icon in header search, or submit a natural-language query.  
5. Admin: **AI Commerce Assistant → Search ROI Dashboard**  
   URL: `/admin/aicommerceassistant/dashboard/index`

---

## Analytics (Search ROI)

Built into this module (formerly `Klizer_AiCommerceAnalytics`). One config section, one admin menu, shared API base URL.

### What it shows

| Metric | Meaning |
| ------ | ------- |
| **Searches** | AI searches in the selected window (+ unique users) |
| **Zero-result rate** | Share of searches that returned no products |
| **Hybrid / semantic share** | Searches that used hybrid or semantic recall (lift vs Magento-only) |
| **CTR** | Product **clicks ÷ impressions** from AI PLP cards |
| **Top queries** | Most common shopper queries (what they asked) |

Optional iframe / link opens the full React analytics UI (`/analytics`).

### Admin config

**Stores → Configuration → Klizer → AI Commerce Assistant → Search Analytics (Admin ROI)**

| Setting | Default | Notes |
| ------- | ------- | ----- |
| Enable Analytics Menu | Yes | Shows **AI Commerce Assistant → Search ROI Dashboard** |
| Full Analytics Dashboard URL | `http://localhost:5173/analytics` | Optional iframe + “Open full React dashboard” link |
| Default Window (days) | `30` | Summary window for rates and top queries |

Uses the same **AI API Base URL** as the storefront assistant (`http://127.0.0.1:3001`). Magento PHP must be able to HTTP-call that host.

### Magento screenshots & demo video

**Admin config — Search Analytics**

Stores → Configuration → Klizer → AI Commerce Assistant → Search Analytics (Admin ROI)

![Admin config for search analytics](https://i.ibb.co/ycpzSgKD/image.png)

**AI Commerce — Search analytics Dashboard**

Admin menu: **AI Commerce Assistant → Search ROI Dashboard**  
(`/admin/aicommerceassistant/dashboard/index`)

![Search analytics dashboard](https://i.ibb.co/4RBxpd70/AI-Commerce-Search-analytics-AI-Commerce-Assistant-Magento-Admin.png)

![AI Commerce Assistant analytics](https://i.ibb.co/NdK7wGBD/AI-Commerce-Assistant.png)

**Demo video**

[Jumpshare — Search analytics demo](https://jumpshare.com/share/0TF12THbX8IS9ukftblL)

### How data is collected

1. **Search log** — Node records each completed AI search (query, source, zero-result, etc.) when `/api/assistant/start` or `/api/assistant/message` returns products.  
2. **Impressions** — Magento AI PLP calls `aicommerceassistant/ajax/track` when matchable / alternative products are shown.  
3. **Clicks** — Magento AI PLP tracks when the shopper clicks a product card (before PDP).  
4. **Admin dashboard** — fetches `GET /api/analytics/summary?days=N` from the Node API and renders cards + top queries.

**CTR updates only after:** run an AI search that shows products (impressions), then click a product card (clicks), then refresh the admin dashboard.

### Node prerequisites

```bash
cd /path/to/AI-Commerce-Assistant/server
# DATABASE_URL must be set in .env
npm run db:migrate:analytics   # or full: npm run db:migrate
npm run dev
```

Useful Node endpoints:

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET` | `/api/analytics/status` | Enabled? |
| `GET` | `/api/analytics/summary?days=30` | ROI summary (used by Magento admin) |
| `GET` | `/api/analytics/searches` | Recent searches |
| `POST` | `/api/analytics/track` | Impressions & clicks (proxied by Magento) |

### Magento pieces (analytics)

| Path | Role |
| ---- | ---- |
| `Block/Adminhtml/Dashboard.php` | Loads summary from Node |
| `Controller/Adminhtml/Dashboard/Index.php` | Admin page (with Magento menu/header) |
| `Controller/Ajax/Track.php` | Storefront → Magento → `/api/analytics/track` |
| `view/adminhtml/` | Layout + `dashboard.phtml` |
| `etc/adminhtml/menu.xml` | Admin menu |
| Config path | `aicommerceassistant/analytics/*` |

---

## Module layout

```text
Klizer/AiCommerceAssistant/
├── Block/Header/Launcher.php          # Header AI icon
├── Block/Result/Assistant.php         # AI PLP block
├── Block/Adminhtml/Dashboard.php      # Admin ROI summary
├── Controller/Ajax/                   # start / message / track proxies
├── Controller/Adminhtml/Dashboard/    # Admin analytics page
├── Controller/Context/                # context search proxy
├── Controller/Index/                  # empty-query AI entry page
├── Model/Api/Client.php               # HTTP client → Node
├── view/frontend/                     # templates, JS, CSS
├── view/adminhtml/                    # dashboard layout + template
└── docs/                              # wireframes
```

## Note on AiCommerceAnalytics

`Klizer_AiCommerceAnalytics` was merged into this module. Disable/remove the old module to avoid duplicate menus.

## Further reading

- **[magento/DEMO.md](./magento/DEMO.md)** — demo screenshots & videos
- **[DEMO.md](./DEMO.md)** — alternate demo walkthrough (if present)
