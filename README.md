# CrowdfundingBundle

Symfony bundle for crowdfunding campaigns — counterparts, contributors, news, media, and a lottery tied to campaigns. Checkout goes through [c975L/PaymentBundle](https://github.com/975L/PaymentBundle).

> **BUNDLE UNDER DEVELOPMENT — USE AT YOUR OWN RISK**

---

## Features

- Crowdfunding campaigns with counterparts, contributors, news, videos
- Lottery tied to a crowdfunding campaign (prizes, tickets, winner draw)
- Plugs into PaymentBundle's Basket/checkout engine via `BasketItemProviderInterface`
- EasyAdmin CRUD for crowdfunding

---

## Requirements

- PHP >= 8.0
- [c975L/ConfigBundle](https://github.com/975L/ConfigBundle)
- [c975L/UiBundle](https://github.com/975L/UiBundle)
- [c975L/PaymentBundle](https://github.com/975L/PaymentBundle) — owns the Basket/checkout engine
- [c975L/ShopBundle](https://github.com/975L/ShopBundle) — owns the shared `Media` base class (Doctrine `SINGLE_TABLE`
  inheritance requires every media subclass, including this bundle's, to live in the same bundle as `Media` itself;
  see ShopBundle's `Entity/Media.php`). This is the one intentional exception to "satellites never depend on each
  other" in this ecosystem — splitting it further would require moving `Media` itself to UiBundle.

---

## Installation

```bash
composer require c975l/crowdfunding-bundle
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console c975l:config:load-all
```

Add to `config/routes.yaml`:

```yaml
c975_l_crowdfunding:
    resource: "@c975LCrowdfundingBundle/"
    type: attribute
```

---

## Data compatibility with existing ShopBundle installations

This bundle was extracted from ShopBundle (07/2026). Entity classes kept their original table names
(`shop_crowdfunding`, `shop_crowdfunding_counterpart`, `shop_lottery`, ...) — only the PHP namespace changed
(`c975L\ShopBundle\Entity\*` → `c975L\CrowdfundingBundle\Entity\*`), so existing data is read as-is, no migration
needed for the entities that stayed in this bundle.
