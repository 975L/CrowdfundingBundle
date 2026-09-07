# Upgrade

## v1.1

### The legacy-tables command is gone [BC-Break]

`c975l:crowdfunding:migrate-legacy-tables` renamed the `shop_*` tables, moved the
`medias/shop/crowdfundings|counterparts` folders and rewrote the stored filenames for a site installed
before the rename. The one site concerned has been migrated, so the command and the README walkthrough
are removed. A site that never ran it renames its own tables and folders by hand, following
*Data compatibility with existing ShopBundle installations* in the README.

## v1.0.0

What follows concerns a site that was running this bundle from its `dev` branch before the first tag, or
one whose crowdfunding pages still come from ShopBundle.

### The labels are this bundle's own, in a `crowdfunding` domain [BC-Break]

Every label was resolved in the **`shop`** domain, whose catalogue lives in ShopBundle — which this bundle
has not depended on since 23/07/2026. A site running a campaign without the shop showed raw keys
(`label.counterparts`, `label.i_contribute`…) on every page it served. The bundle now ships
`translations/crowdfunding.{en,fr,es}.xlf` and reads its own domain.

Two consequences for a site that had customised those wordings:

- an override of `translations/shop.<locale>.xlf` **stops applying** to the crowdfunding pages. Move the
  keys it holds to a `translations/crowdfunding.<locale>.xlf` of your own — the keys themselves have not
  changed, only the domain they are read in.
- a template of your own overriding one of this bundle's and calling `|trans({}, 'shop')` renders the raw
  key from now on. Change the domain it names to `'crowdfunding'`.

Nothing to run, and no database change: the catalogue is read from the package.

### The three emails are composed in the back office [BC-Break] [Needs db update]

`Service\EmailService` and `Service\EmailServiceInterface` are **gone**, with the three Twig bodies of
`templates/emails/` and their standalone `layout.html.twig`. The contribution, lottery-ticket and
winning-ticket emails are now `EmailTemplate` rows an admin composes, declared by
`Email\CrowdfundingEmailTemplateProvider` and sent through UiBundle's own `EmailService`.

- **An app implementing `EmailServiceInterface`, or having redeclared its alias, no longer compiles.**
  What replaced it is `Email\CrowdfundingEmailSender`, which takes a template name, a subject key, an
  address, the slot context and a locale.
- **An app overriding `@c975LCrowdfunding/emails/*.html.twig` loses that override silently**, the file
  it overrode no longer existing. The wording is edited in the back office from now on; what the code
  computes — the counterparts, the ticket numbers, the prize — stays in
  `templates/emails/slots/`, which an app may still override.
- **Seed the rows**, then rewrite them in the back office if you want to:

```bash
php bin/console c975l:ui:email-templates:ensure
```

  Nothing breaks if you skip it: the renderer falls back on the declaration itself. The health check
  reports the rows a site is missing.

- **One migration**, which adds `crowdfunding_contributor.locale`. A contribution written before it
  carries none, and its emails go out in the site's own language as they did:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### A campaign page is composed in the back office [Needs db update]

`Crowdfunding` implements `HasBlocksInterface`, and its edit screen gained a **Blocs** tab. The page renders
that collection after its own sections, so a campaign says what it needs — a banner, the arguments, a
gallery, a FAQ — without a template written for it.

One migration, which adds the `crowdfunding_crowdfunding_block` join table:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

Nothing changes on an existing campaign until an admin adds a block to it.

### The checkout now refuses a basket that can no longer be ordered [BC-Break]

`CrowdfundingBasketItemProvider::validateCheckout()` answered `null` and checked nothing. It now applies
the campaign's dates and the counterpart's run to the whole basket, as ShopBundle does for its products.

A basket filled before a campaign ended, or holding more of a counterpart than its run has left, is
refused at the top of the checkout instead of being paid for. This is the intended behaviour, but a site
that had counted on the old one — a campaign extended by hand after its end date, say — sees orders it
used to take being turned away. Nothing to run.

### The Stimulus barrel starts its own app [BC-Break]

`assets/controllers.js` exported `register(app)`, the scheme that assumed an `assets/bootstrap.js` on the
site's side — abandoned everywhere else in the ecosystem. It now starts its own app and registers the
`lottery` controller lazily, and the bundle announces it through `Service\ScriptProvider` and
`Management\ImportmapProvider`.

- **Remove the two lines from `assets/bootstrap.js`** if you added them, the import and the
  `registerc975lCrowdfunding(app)` call: the export they name is gone, and the file would stop compiling.
- **The `importmap.php` entry is written for you** on the next `composer update`; check it with
  `php bin/console c975l:config:check-importmap`.
- Nothing else to do: the barrel is loaded by `bundle_scripts()`, which your layout already renders for the
  other c975L bundles. Until this release, the `lottery` controller was never loaded at all and the draw
  wheel did not answer a click.

`assets/js/translations.{en,fr,es}.js` were merged into a single `translations.js` keyed by locale. A site
importing one of the three files directly changes its import; nothing in the bundle does.

### `EmailServiceInterface` and the three email bodies are on their way out

`Service\EmailService` still builds its three emails from `@c975LCrowdfunding/emails/*.html.twig`, the only
transactional emails of the ecosystem not yet composable in the back office. When they move to an
`EmailTemplateProviderInterface`, the interface and those Twig bodies go with them — a site implementing the
former, or overriding the latter, will have that override cease to apply. Nothing to do today; this is here
so it is not discovered on the day.
