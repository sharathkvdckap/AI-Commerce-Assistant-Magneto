# Klizer_AiCommerceAssistant (Magento module)

Magento 2 storefront module that embeds the **AI Commerce Assistant** on catalog search: header AI icon, clarifying questions, and matchable product PLP. Product data always comes from Magento; conversation logic runs on the Node API.
 
## Quick Magento setup

1. Start the Node API on `:3001` (see Node README).
2. Enable this module:

```bash
cd /var/www/html/magento248p4
php bin/magento module:enable Klizer_AiCommerceAssistant
php bin/magento setup:upgrade
php bin/magento cache:flush
```

3. **Stores → Configuration → Klizer → AI Commerce Assistant**
   - Enable: **Yes**
   - AI API Base URL: `http://127.0.0.1:3001`
   - Redirect to External Assistant UI: **No** (on-site AI PLP)

4. Storefront: use the **AI** icon in header search, or submit a natural-language query.

## Module layout

```text
Klizer/AiCommerceAssistant/
├── Block/Header/Launcher.php     # Header AI icon
├── Block/Result/Assistant.php    # AI PLP block
├── Controller/Ajax/              # start / message proxies
├── Controller/Context/           # context search proxy
├── Controller/Index/             # empty-query AI entry page
├── Model/Api/Client.php          # HTTP client → Node
├── view/frontend/                # templates, JS, CSS
└── docs/                         # wireframes
```
## Further reading

- **[magento/DEMO.md](./magento/DEMO.md)** (Magento) **demo screenshots** & videos (local run, Magento, apparel, semantic, industrial, fitness)