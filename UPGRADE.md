# Upgrade

## v1.3

### The campaigns are administered by the editor's role [BC-Break]

The whole campaigns CRUD sat behind `site-role-admin`, where every other c975L screen composing what a site
publishes sits behind `site-role-editor` - the pencil UiBundle draws over a campaign's sections read the
editor's bar already, so an editor saw a way in on every section and a 403 at the click.

`CrowdfundingCrudController` now names `site-role-editor` on its index, its form, its entity permission,
the QR code and the *view on site* / *preview* buttons, and `CrowdfundingController::preview()` and the
news form of the public page follow. The recycle bin's own two actions - `restore` and
`deletePermanently` - stay on `site-role-admin`, as they do in SiteBundle, and so does the parcours
walking to them and `LotteryController::drawPrize()`.

**On a site where the two roles differ, this widens who may open, edit and delete a campaign.** Nothing
has to be migrated, but the two keys are worth re-reading from the dashboard before deploying.

### The campaign's collections delete what is taken off the form

`$medias`, `$counterparts`, `$videos` and `$lotteries` carry `orphanRemoval` now, as `$news` already did:
removing one of them from the campaign's form used to leave the row in the table with a null
`crowdfunding_id`, and its file on the disk with it.

Two are put back by `CrowdfundingCrudController::updateEntity()`, which says so in a message rather than
letting the save go through: a counterpart a contributor already paid for, whose removal the rows of
`crowdfunding_contributor_counterpart` would have the database refuse, and a draw a ticket was sold on -
that one refuses nothing on its own, a lottery cascading its removal to its tickets and its drawn winner.

The orphans a site already accumulated are not cleaned up on their own. `crowdfunding_media` is a
single-table hierarchy of four types, so a purge must name the ones that belong to a campaign - a bare
`crowdfunding_id IS NULL` would take out every counterpart picture and every lottery video, whose column
is null by right:

```sql
DELETE FROM crowdfunding_media WHERE crowdfunding_id IS NULL AND owner_type IN ('crowdfunding', 'crowdfunding_video');
```

### The counterparts block drops its `columns` setting [BC-Break]

The tiers of a campaign are read as rows now, one open at a time (`<details name="counterparts">`), so
there is no column count to choose any more: `Form\Block\CounterpartsBlockType` no longer offers
`columns`, and `templates/blocks/Counterparts.html.twig` no longer writes a
`crowdfunding-counterparts--cols{n}` class.

Nothing has to be migrated: a `crowdfunding_counterparts` block already placed keeps the value it stored,
which is simply never read again. A site whose own stylesheet targets `.crowdfunding-counterparts .cards`,
`.crowdfunding-counterpart .card-img` or `.crowdfunding .price-counterpart` has to be rewritten against
the row's own classes - `.crowdfunding-counterpart__summary`, `__visual`, `__price`, `__panel`.

The `label.block_columns` and `label.counterpart_entitles` keys are gone from the three catalogues.

### The funding rail holds two cards [BC-Break]

`Crowdfunding:FundingBar` renders a UiBundle card titled *Campaign* instead of a bare `<aside>`, and
`Lottery:Lotteries` - the draws in full, prizes included - moved out of the story column to sit under it.
`templates/crowdfunding/display.html.twig` wraps both in `<div class="crowdfunding-rail">`, which is what
sticks from 1024px up. A `crowdfunding_lottery` block placed by an editor still takes the section over
wherever they put it, exactly as before.

A site that overrode `Crowdfunding/FundingBar.html.twig` keeps its own version, and with it the footer the
lottery used to be written in - nothing breaks, the draw is simply said twice. A site styling
`.crowdfunding-funding` as a box has to move those rules onto `.crowdfunding-rail .card`, the card now
bringing the outline, the ground and the corners; `.crowdfunding-funding__lottery` and
`__lottery-title` no longer exist.

### The draw's page moved under the campaigns [BC-Break]

A draw is read at `/crowdfunding/lottery/{identifier}` and drawn at
`/crowdfunding/lottery/{identifier}/draw/{rank}`, where both hung under `/shop/` - a prefix inherited from
the ShopBundle the bundle was extracted from, and which said nothing of what the page shows.

`/shop/lottery/{identifier}` answers **404** from now on: nothing is left behind at the old url. Every
link already sent - the ticket e-mails first of all - points there, so if any of them still matters,
declare the redirection in your own application:

```yaml
# config/routes.yaml
lottery_display_legacy:
    path: /shop/lottery/{identifier}
    controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController::redirectAction
    defaults:
        route: lottery_display
        permanent: true
    requirements:
        identifier: '^[a-zA-Z0-9\-]{13}$'
```

Templates and e-mails naming the route rather than the path (`path('lottery_display', ...)`) follow the
move on their own - the route names have not changed.

### A campaign is hidden before it is opened, and deleted in two steps [BC-Break]

The campaign screen gains what a product and a page have carried for a while: a **Hidden** switch and a
**recycle bin**. A hidden campaign is out of the listing, out of the sitemap, out of the linkable routes
and out of the basket, its page answering 404 - an editor reads it through the new **Preview**
action (`/crowdfunding/{slug}/preview`, admin only, rendered without the block cache). Deleting a
campaign no longer removes anything: it moves it to the recycle bin, where its page answers 410 and where
it is restored or deleted for good, that second deletion leaving a `gone` Redirect behind at its url.

Two columns, no migration shipped - generate one in your own application (`doctrine:migrations:diff`), or
run it by hand:

```sql
ALTER TABLE crowdfunding_crowdfunding
    ADD hidden TINYINT(1) DEFAULT 0 NOT NULL,
    ADD is_deleted TINYINT(1) DEFAULT 0 NOT NULL;
```

Both default to `false`, so **the campaigns already online stay online**. Only campaigns created from now
on start hidden, and have to be opened once their page is composed.

**What the second deletion erases.** `Crowdfunding::$contributors` now cascades the removal, as everything
else the campaign holds already did: deleting a campaign for good takes its contributors, their
counterparts and the lottery tickets drawn from them with it. Nothing of a funded campaign's history
survives that gesture - the recycle bin is where a campaign is kept, and it removes nothing.

### A campaign media says what it is for [BC-Break]

`CrowdfundingMedia` carries a `kind`, and a campaign no longer holds one collection of images read
differently by each section. Two uses, each with a field of its own on the campaign's screen:

| Kind | Field | What reads it |
| --- | --- | --- |
| `hero` | *Opening image* | the image the page opens on, the catalogue's thumbnail, the `og:image` |
| `slide` | *Slider* | the images the page's slider runs through |

**A file has one use.** Nothing falls back on "the first media" any more: a campaign with no `hero` opens
without an image rather than on whichever plate was sorted first. To show the same image both as the
opening and in the slider, upload it on each of the two fields.

Two steps, in this order.

1. **The column.** The bundle ships no migration - generate one in your own application
   (`doctrine:migrations:diff`), or run it by hand:

   ```sql
   ALTER TABLE crowdfunding_media ADD kind VARCHAR(50) DEFAULT NULL;
   ```

2. **The rows.** Every existing media has `kind = NULL`, which no section reads: without this the hero,
   the slider, the catalogue thumbnails and the `og:image` all go blank. Run the backfill in the same
   deployment as the column - it files every image as a slide, then promotes each campaign's first one,
   read in the order the slider used to be:

   ```sql
   UPDATE crowdfunding_media SET kind = 'slide' WHERE owner_type = 'crowdfunding';

   UPDATE crowdfunding_media m
       JOIN (
           SELECT id FROM (
               SELECT id, ROW_NUMBER() OVER (PARTITION BY crowdfunding_id ORDER BY `position`, id) AS rn
               FROM crowdfunding_media
               WHERE owner_type = 'crowdfunding'
           ) ranked WHERE rn = 1
       ) first ON first.id = m.id
   SET m.kind = 'hero';
   ```

   A file has one use, so that first image leaves the slider as it becomes the opening - where the page
   used to show it in both. Upload it again on the *Slider* field of the campaigns that want it there.

Only `owner_type = 'crowdfunding'` rows are concerned: a counterpart's image, a campaign's videos and a
lottery's videos are each the only file of their kind on their owner and carry no kind.

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
