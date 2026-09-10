# Changelog

## v1.3.1

Larger type in the counterpart rows

- The tier's name reads larger on a counterpart row, and its kind and flag badges with it (10/09/2026)
- The quantities, delivery, low-stock warning and the open panel's details step up to the same scale (10/09/2026)

## v1.3

Hidden campaigns, a recycle bin and a page preview

- **The campaigns CRUD sits behind `site-role-editor`**: index to delete, form, QR code and the two site buttons, as every other c975L screen composing what a site publishes - the editing pencil already read that bar, so an editor saw a way in and a 403 at the click. The recycle bin's restore and permanent deletion stay on `site-role-admin`, see UPGRADE.md (09/09/2026)
- `MenuProvider` names the editor's role on its entry rather than letting it default to the admin's, which would have kept the screen out of the menu of the very people who may reach it (09/09/2026)
- **`$medias`, `$counterparts`, `$videos` and `$lotteries` carry `orphanRemoval`**: a media, a tier, a video or a draw taken off the campaign's form used to keep its row with a null campaign, and its file on the disk, see UPGRADE.md (09/09/2026)
- A counterpart a contributor already paid for is put back on save, with a message naming it: its removal would reach the database as a DELETE the contributors' own rows refuse (09/09/2026)
- A draw a ticket was sold on is put back the same way: a lottery cascades its removal to its tickets, so nothing would have refused taking the winner with it (09/09/2026)
- The news listener stamps `modification` on `preUpdate` rather than `preFlush`, which Doctrine hands the whole identity map: a news the campaign page had only read was re-dated by any later flush of the same request. `prePersist` stamps it too, the column being not-null (09/09/2026)
- The QR code answers 404 on a campaign that is not there, where the url naming a deleted one gave a fatal on its slug - `CrowdfundingCrudControllerTest` covers both cases (09/09/2026)
- The opening date is only printed where the campaign carries one: `beginDate` is nullable and Twig's date filter turns a null into today, so a campaign without dates announced it opened the very day it was read (09/09/2026)
- The `crowdfunding-publish` parcours reopens the campaign before showing the QR code, the save two steps earlier having returned to the listing where the code is not drawn (09/09/2026)
- The `crowdfunding-blocks` parcours ends on the fourth kind, `crowdfunding_campaigns`, which is placed on an ordinary page of the site rather than on a campaign's own (09/09/2026)
- **New block kind `crowdfunding_campaigns`**: the campaigns a visitor may read, listed on any page of the site with the head every listing carries - eyebrow, title, paragraph, link and colored flat - where the bundle's three other kinds only ever compose a campaign's own page. The cards are the listing's own (`Crowdfunding:Crowdfunding`), read live from the repository at render time, so a campaign opened or closed in the back office shows up without touching the page (09/09/2026)
- `CrowdfundingBlockExtension::getCampaigns()` behind `crowdfunding_block_campaigns(max)`: the same query `/crowdfunding` runs, so a listing block and that page never name two different sets - and unlike `crowdfunding_block_campaign()` it reads nothing off the route, a listing standing on a page that is not a campaign (09/09/2026)
- The zigzag rule under every section is gone: the page spaces its bands with the ecosystem's own step now, where that band was what used to say a section had ended - and on a flat it cut across the flat itself (09/09/2026)
- The use of the funds and the author read on a tinted band rather than a black slab, in two columns of equal weight, their prose left-aligned and measured where centering it across the band changed direction on every line (09/09/2026)
- The rail offers the one way in and no price beside it: the three tiers it listed said what they cost a screen above the rows saying it in full (09/09/2026)
- The winning tickets of a draw read on one line: what a prize card says about the draw is pinned to its bottom, where a description of three lines and one of six sent them to two different heights (09/09/2026)
- **The tiers are read as rows, one open at a time**: `<details name="counterparts">` rather than a grid of cards, HTML's own exclusive accordion, the same row ShopBundle gives a product's items - the price is a column of its own, so it falls on one line whatever a tier is named (09/09/2026)
- A tier's picture is the row's own left column, full height and reserved even on a tier that has none, where it was a thumbnail dropped in the card (09/09/2026)
- A tier's price is the other end of the row, full height in the campaign's own hue: the two flats are what a closed row is read between (09/09/2026)
- The open row reads darker than the closed ones, the same way a product's item does in the shop (09/09/2026)
- **`CounterpartsBlockType` drops its `columns` setting**: rows have no column count to choose, see UPGRADE.md (09/09/2026)
- The funding rail is a card named *Campaign*, and the campaign's draws are read in full under it - the lottery moved out of the story column, and the footer the funding used to name it in is gone (09/09/2026)
- The grid of tiers never was a grid: `crowdfunding-counterparts--cols{n}` was written on the row itself where the sheet expected it on an ancestor, and the hardcoded section carried no class at all, so both fell back on UiBundle's own wrapping flex and the cards came out of unequal widths (09/09/2026)
- `CrowdfundingCounterpart:Quantity` says the quantities alone: the delivery is read on the row's summary, without opening anything, and the two rules the card needed around it are gone (09/09/2026)
- **The draw's page moved under the campaigns**: `/crowdfunding/lottery/{identifier}` and its `/draw/{rank}` endpoint, where both hung under the `/shop/` prefix inherited from ShopBundle - the old url answers 404, see UPGRADE.md (09/09/2026)
- The drawn ticket takes its gold back: `Prize` set a `class` variable that the card's own empty `class` prop shadowed, a component's slot being rendered in the component's context (09/09/2026)
- A prize card reads from the top: its content dropped `card-data`, the class pinning a card's tail to the bottom of its body, the cards keeping the common height `.cards` stretches them to (09/09/2026)
- The winning ticket and the name beside it are centered in their card (09/09/2026)
- New `ComponentSlotTest`: no template of the bundle prints a `class` variable between the tags of a component, where the component's own prop answers to that name (09/09/2026)
- The skill says what `drawDate` is: a naive `DATETIME` the pages convert from PHP's own timezone, so a `date.timezone` on UTC shifts every draw date read on a Paris site (09/09/2026)
- `CrowdfundingNews` is `Stringable`: a news folds onto its title in the back office rather than onto its class name (09/09/2026)
- New guided project `crowdfunding-news` (9045), walking the *News* panel: a news is published from the campaign's public page and only corrected there (09/09/2026)
- **The news are edited in the back office**: a *News* fieldset on the campaign's screen, where a follow-up is corrected and removed - the campaign page only ever added one, and nothing could undo a typo (09/09/2026)
- New `Listener\CrowdfundingNewsListener`: the dates of a news are stamped wherever it is written, the back office not going through `CrowdfundingService::addNews()` (09/09/2026)
- The publication date is defaulted rather than imposed: a news reaching the listener with one already set keeps it (09/09/2026)
- `Crowdfunding::$news` cascades the persist and removes its orphans: without them the back office wrote nothing and a deleted news only lost its campaign (09/09/2026)
- `CrowdfundingNewsType` asks for no `config` option any more - required, it made the new collection throw the moment the campaign's form rendered - and names its own domain (09/09/2026)
- The news section carries the "Edit" pencil, pointing at that fieldset, even while the campaign has no news yet (09/09/2026)
- The three labels of a counterpart card name the `crowdfunding` domain, `trans_default_domain` not reaching a component's slot content (08/09/2026)
- `TranslationDomainTest` holds that rule for every template of the bundle (08/09/2026)
- The hero and the flats paint edge to edge again in the campaign's flex column (08/09/2026)
- The hero's title and the line above it take the ink of the caption they sit in (08/09/2026)
- The hero's gradient reaches the line above the title, which a bright opening image left unreadable (08/09/2026)
- The use of the funds is said once again: `showUse="false"` passed the string, which Twig reads as true (08/09/2026)
- The rail says the percent beside the amounts, the bar's own label being drawn white across a track it does not fill (08/09/2026)
- The rail sticks under the site's header rather than under a guessed 84px (08/09/2026)
- A tier carries a way to join only while the campaign can be joined, the rail saying it once for the whole page (08/09/2026)
- Every collection sorted on a value an editor types breaks its tie on the id: two medias at the same position, two news of the same day, two tiers at the same price, two prizes at the same rank (08/09/2026)
- A campaign media says what it is for: `CrowdfundingMedia::$kind`, an *Opening image* field and a *Slider* field on the campaign's screen, each section of the page reading its own (08/09/2026) [BC-Break, see UPGRADE.md]
- The *Opening image* field takes one file, its "Add" link closing as soon as the campaign holds one (08/09/2026)
- The guided walk of the medias splits where the screen does: the opening image, then the slider's plates (08/09/2026)
- The sticky funding strip is gone: it repeated the rail's own amounts and gauge on the same screen (08/09/2026)
- New `Twig\Extension\CrowdfundingEditExtension` and its `crowdfunding_edit_url()`: the sections the page hardcodes carry the same "Edit" pencil a composed block has (08/09/2026)
- A campaign's video plays again: its `src` goes through `asset()` like every image around it (09/09/2026)
- The campaign page keeps the ecosystem's vertical step, `--section-space` between its bands and `--section-space-tight` inside the story (09/09/2026)
- The counterparts and the draws are declared `cacheable: false` rather than vetoing their own entry, `CrowdfundingBlockCacheTagProvider` carrying the slider alone (09/09/2026)
- `CrowdfundingCacheInvalidationListener` drops the campaign tag on a campaign and its medias alone, a tier or a contribution being read by no cached render (09/09/2026)
- **A campaign is hidden before it is opened**: `Crowdfunding::$hidden`, a *Hidden* switch on its screen, and a hidden campaign out of the listing, of the sitemap, of the linkable routes and of the basket, its page answering 404 (09/09/2026) [BC-Break, see UPGRADE.md]
- **New `crowdfunding_preview` route** (`/crowdfunding/{slug}/preview`): the campaign's own page behind the admin role, rendered without the block cache and with a banner saying which of the two it is (09/09/2026)
- The blocks of this bundle compose the preview as they compose the public page, `CrowdfundingBlockExtension` reading its campaign off either route (09/09/2026)
- **The campaign screen has a recycle bin**: deleting only moves a campaign to it, where its page answers 410 and where it is restored or deleted for good (09/09/2026) [BC-Break, see UPGRADE.md]
- A campaign deleted for good leaves a `gone` Redirect at its url, and the redirects pointing at it become `gone` rows rather than dangling (09/09/2026)
- Deleting a campaign for good erases the contributions it received, `Crowdfunding::$contributors` cascading the removal like everything else it holds (09/09/2026)
- A draw answers 410 or 404 through the campaign it belongs to, its url being a way round what the campaign page hides (09/09/2026)
- *View on site* and *Preview* on the campaign's list and its own screen, each shown on the state it belongs to (09/09/2026)
- *Delete* is on the edit screen too, this version of EasyAdmin putting none there (09/09/2026)
- The *Hidden* column is off the recycle bin's view, where every row carries it by definition (09/09/2026)
- The campaign screen is named in the editor's own language, EasyAdmin falling back on the class name (09/09/2026)
- New guided projects `crowdfunding-publish` and `crowdfunding-trash`, walking the opening of a campaign and the two gestures that remove it (09/09/2026)
- The admin menu section carries an icon, the public index its own and a `_blank` target, and the CRUD entry is named *Campaigns* rather than repeating the section (09/09/2026)
- New `Controller\Management\CrowdfundingCrudControllerTest`, holding what the two trash actions refuse: a forged token, and a campaign that is not in the bin (09/09/2026)

## v1.2.0

A campaign page composed from three block kinds of its own

- **Three block kinds of its own**: `crowdfunding_slider`, `crowdfunding_counterparts`, `crowdfunding_lottery` (08/09/2026)
- Each reads the campaign of the route being rendered rather than storing which one to show (08/09/2026)
- **New `CrowdfundingBlockExtension`**: `crowdfunding_block_campaign()` and `crowdfunding_block_sheet_kinds()` (08/09/2026)
- `crowdfunding/display.html.twig` renders the blocks amid its sections, each stepping aside once a block takes it over (08/09/2026)
- A campaign carrying no block reads as it always did, nothing to migrate (08/09/2026)
- **New `CrowdfundingFundingExtension`**: `crowdfunding_funding_state()` answers where a campaign stands (08/09/2026)
- The strip, the rail and the tier buttons read that one answer, so none can contradict another (08/09/2026)
- **New `Crowdfunding:FundingBar` component**: the amounts, the gauge, the contributors, the days left and the button to contribute (08/09/2026)
- The funding rail carries the first tiers within reach and the draw a contribution enters (08/09/2026)
- It sticks only from 1024px up, two sticky boxes having no room on a phone (08/09/2026)
- **New `Crowdfunding:FundingTopBar` component**: the amounts, the gauge and the button, stuck under the site's header (08/09/2026)
- **New `Crowdfunding:Hero` component**: the campaign's opening image with its name over it (08/09/2026)
- It stands aside for a composed `banner_title` (08/09/2026)
- **New `Crowdfunding:UseFor` component**: what the money pays for, on the band it shares with the author (08/09/2026)
- `Crowdfunding:Presentation` takes a `showUse` prop, defaulting to true (08/09/2026)
- `Lottery:Lottery` takes a `showTicketsCount` prop, a setting of the lottery block (08/09/2026)
- A counterpart card takes a highlighted tier and a low-stock threshold, both settings of the counterparts block (08/09/2026)
- A tier shows its picture at the head of its card (08/09/2026)
- The chronicle reads on two columns from 1024px up (08/09/2026)
- The slider's render is cached under a campaign tag, dropped by `CrowdfundingCacheInvalidationListener` (08/09/2026)
- The counterparts and the draws veto their own entry through `CrowdfundingBlockCacheTagProvider` (08/09/2026)
- **New `CrowdfundingShowcaseProvider`**: the slider carries a silhouette in the block picker (08/09/2026)
- The front-end "Edit this block" button resolves a campaign's edit screen, via `CrowdfundingBlockEditUrlProvider` (08/09/2026)
- New `CrowdfundingRepository::findByBlockIds()` (08/09/2026)
- New `sass/_crowdfunding-blocks.scss`, and the compiled stylesheets rebuilt from it (08/09/2026)
- The translation test reads `config/services.yaml` too, the block kinds naming their labels there (08/09/2026)
- **`vich/uploader-bundle` moves from `^2.9` to `^3.0`**, and `c975l/core-bundle` to `^1.25` (08/09/2026) [BC-Break]
- CoreBundle overrides Vich's storage and namer, whose 3.0 signatures 2.x has no type for (08/09/2026)
- The upgrade changes nothing here: this bundle only carries `Mapping\Attribute` on its entities (08/09/2026)
- **New `CrowdfundingFilesHealthCheckProvider` (kind `files-crowdfunding`)**: every file a campaign, a counterpart or a lottery names is checked against the disk (08/09/2026)
- A counterpart and a lottery have no screen of their own, so their rows link back to the campaign (08/09/2026)
- New `MediaRepository::findWithFilename()` (08/09/2026)
- Tests for the two Twig extensions, the three block form types, the cache services, the three providers and the two repositories (08/09/2026)
- The README documents the composed campaign page, and the shipped skill its three kinds (08/09/2026)

## v1.1.1

Run phpmd and lizard in the CI and its local replay

- The workflow runs `composer mess` and `composer lizard`, with `phpmd` added to setup-php's tools and lizard pinned on a setup-python step (08/09/2026)
- `bin/ci.sh` installs phpmd beside the other tools and lizard in a virtualenv, and prints both versions (08/09/2026)

## v1.1.0

Drop the legacy-tables migration command

- `MigrateLegacyTablesCommand` and its README walkthrough are removed (07/09/2026) [BC-Break]
- The winner's name and the draw date are written as text nodes instead of `innerHTML` (07/09/2026)
- `phpunit.xml.dist` fails on a notice as well (07/09/2026)
- `composer mess` names how many files PDepend could not read (07/09/2026)

## v1.0.0

Guided projects, admin procedures and the fixes the review turned up

- Six guided projects in the 9000 block `GuidedProjectProviderInterface` reserves this bundle - the campaign, its media, a counterpart, its blocks, the lottery and the draw's video - where it was the one bundle of the ecosystem contributing none (07/09/2026)
- A `filmer-tirage-loterie` procedure, read by the dashboard's assistant: a draw is written and mailed on the very click that ends it, so the recording has to be running before it, and no guided project can say that - the panel only lives in the back office, and the draw happens on the lottery's public page (07/09/2026)
- The four collections of the campaign form carry a `data-crowdfunding-*` marker and the lottery's two a `data-lottery-*` one, a `CollectionField` printing no id its steps could point at (07/09/2026)
- The menu entry and the public link carry the `description` and the `narration` the onboarding tour reads and speaks, where both showed a bare label (07/09/2026)
- `translations/crowdfunding_narration.{en,fr}.xlf`, the spoken half of the catalogue the films of the back office read (07/09/2026)
- A `publier-actualite-campagne` procedure: the CRUD declares no `news` collection, so the form exists nowhere in the back office and an admin looking for it in management never finds it (07/09/2026)
- The counterpart parcours opens the collection entry before pointing at `limitedQuantity`, which `CrowdfundingCounterpartType` now marks with a `data-counterpart-quantity` row attribute: two steps ran on the same highlight, the second outlining a folded field (07/09/2026)
- `CrowdfundingBasketItemProvider::validateAddition()` refuses a campaign with no dates, and the CRUD requires both: the columns are nullable, the button read as open and the click threw on `format()` (07/09/2026)
- `CrowdfundingController::display()` reads the campaign's owner nullsafe: a campaign with no `user_id` was fatal for every logged-in visitor, admins included, and fine for anonymous ones (07/09/2026)
- The AssetMapper path is registered unconditionally, where a leftover `vich_uploader` guard subordinated the draw button's JavaScript to an unrelated extension (07/09/2026)
- The draw's error path calls `Handlers.displayMessage()` alone: a call to a `showError()` no controller declares threw, leaving the drum spinning and every Draw button disabled until the page was reloaded (07/09/2026)
- `label.lottery` reads "Lottery" in the English catalogue, where it was the last French target left in it (07/09/2026)
- The campaign's slider follows UiBundle's own contract, `media` and `fallbackAlt` where it passed `slides` (07/09/2026)
- `Entity\Media` answers what UiBundle's slider and image read off any media: the mime type deduced from the stored name, and the texts and dimensions this hierarchy stores none of (07/09/2026)
- The bundle ships its own `translations/crowdfunding.{en,fr,es}.xlf` and reads a `crowdfunding` domain: every label was resolved in ShopBundle's `shop` domain, a bundle this package has not depended on since 23/07/2026 - a site running a campaign without the shop showed raw keys on every page (07/09/2026) [BC-Break]
- The email subject prefix reads its `label.shop` from PaymentBundle's catalogue, the bundle that declares the `shop-name` key beside it (07/09/2026)
- Fourteen timestamps built a `DateTimeImmutable` for a `DATETIME_MUTABLE` column, which Doctrine refuses at flush: adding a counterpart to the basket, registering a contributor, generating lottery tickets and drawing a prize all threw (07/09/2026)
- `EmailService` skips the "shop-email-bcc" and "shop-email-reply-to" addresses when their key is blank, and names "shop-email-from" when that one is: a site having filled only the sender saw every contribution and lottery email die inside Symfony's `Address` constructor (07/09/2026)
- `EmailService` called `ConfigServiceInterface::has()`, a method that interface does not declare - the six `shop-email-*-name` keys are read with `get() ?? ''` instead (07/09/2026)
- `phpstan.dist.neon` dropped an `identifier: phpDoc.parseError` carrying no `path`, which silenced that identifier over the whole bundle while no file in `src/` needed it (07/09/2026)
- `composer.json` gained the `audit-deps` script and put it first in `qa`, and the workflow its *Avis de sécurité des dépendances* step, as the bundles in production have (07/09/2026)
- `composer qa` runs `mess` and `lizard` as ShopBundle does, and `phpmd.xml.dist` drops `NPathComplexity` the same way: the mess detector's configuration was shipped without any script calling it, and neither complexity threshold was ever read (07/09/2026)
- `c975l/core-bundle` is required in `^1.23` and `c975l/payment-bundle` in `^6.8`, the versions published today (07/09/2026)
- `.gitattributes` keeps the whole development toolchain out of the Composer archive, where only three paths were listed (07/09/2026)
- The lint configurations are CoreBundle's to the byte again, and `.markdownlint.json`, `.stylelintrc.json`, `LICENSE` and `.github/FUNDING.yml` are shipped, all four missing (07/09/2026)
- Added `UPGRADE.md`, which every other bundle of the ecosystem carries (07/09/2026)
- `assets/controllers.js` starts its own Stimulus app and registers the `lottery` controller lazily, `Service\ScriptProvider` announces the barrel to UiBundle and `Management\ImportmapProvider` declares its importmap entry: the barrel still exported the abandoned `register(app)`, nothing announced it, and the draw wheel never answered a click (07/09/2026) [BC-Break]
- `assets/js/handlers.js` keeps only what is its own and borrows `getLanguage()`/`translate()` from `@c975l/ui-bundle/handlers.js`, which it duplicated word for word (07/09/2026)
- The three `assets/js/translations.{en,fr,es}.js` are one `translations.js` keyed by locale, as SiteBundle and PaymentBundle already did (07/09/2026)
- `CrowdfundingController` no longer freezes its two public pages for an hour: a contribution, a threshold reached or a news published only showed up once the `max-age` had run out, while what the pages hold is already cached by fragment (07/09/2026)
- `LotteryService::drawWinner()` answers null for a rank the lottery has no prize for, where it read the prize off a `first()` that had answered false; and a ticket that already won is taken out of the draw rather than drawn and redrawn, which never ended once every ticket had won (07/09/2026)
- `CrowdfundingBasketItemProvider::onBasketPaid()` announces the lottery tickets after the flush: dispatched before, the message was handed the contributor's id before the database had assigned one (07/09/2026)
- `.crowdfunding .amount` and `.price-counterpart` take `--font-title-weight` where they asked for `bold`: a site whose title family ships one weight only had the browser fabricate a fake bold for them (07/09/2026)
- Added `Management\LinkableRouteProvider`, so a site's navbar can point at the campaign index or at a campaign by name: this bundle owns no `Page`, and its pages were unreachable from a menu (07/09/2026)
- Added `Management\CrowdfundingStatusProvider`, reporting the prizes whose draw date has passed and which hold no winner, with the oldest of them - the one number of this bundle a maintainer acts on the morning they read it (07/09/2026)
- Added `skills/c975l-crowdfunding/SKILL.md` and its `SkillsTest`, the bundle documentation the six other satellites already shipped for the coding agents of the sites installing them (07/09/2026)
- The three campaign emails are composable in the back office: `Email\CrowdfundingEmailTemplateProvider` declares them as `EmailTemplate` rows, `Email\CrowdfundingEmailFactory` builds the request and `Email\CrowdfundingEmailSender` sends it through UiBundle's `EmailService`. `Service\EmailService`, `EmailServiceInterface`, the three Twig bodies and their standalone `layout.html.twig` are gone - this bundle was the last of the ecosystem still building its emails from files nobody but a developer could touch (07/09/2026) [BC-Break]
- `CrowdfundingContributor` carries the language the contribution was made in, read off the basket: a lottery email goes out from a draw an admin clicked, months later, and nothing else remembered what language the contributor read the campaign in (07/09/2026) **Needs db update**
- `Crowdfunding` owns Blocks: its page is composed in the back office with UiBundle's own kinds (bandeau, arguments, galerie, FAQ) without a template written for it, as a book's and a gallery's already were - `Management\CrowdfundingBlockOwnerResolver` lets the block move screen walk back to the campaign (07/09/2026) **Needs db update**
- The three public templates draw PaymentBundle's `<twig:c975LPayment:Basket:TestMode/>`, where they named ShopBundle's own component - a bundle this package does not depend on, so the pages threw on a site without the shop (07/09/2026)
- `CrowdfundingBasketItemProvider::validateCheckout()` applies the rules `validateAddition()` holds, plus the one it cannot: the whole basket quantity against what the run has left. It answered `null`, so a basket filled while a campaign was running was paid for after it ended, and a counterpart was oversold by clicks that each passed on their own (07/09/2026) [BC-Break]
- Added `Management\CrowdfundingBackupPathProvider`, which declares `public/medias/crowdfunding` to ConfigBundle's backup: nothing of what the back office uploads was saved anywhere, that mechanism archiving only what a bundle declares (07/09/2026)
- The ten repositories dropped the maker's commented-out example methods, and `composer.json` declares the Symfony and Doctrine packages `src/` actually imports (07/09/2026)
- Added the tests the satellites share - `ManagementTargetsTest`, `MenuProviderTest`, `StylesheetProviderTest` - and a `TranslationDomainTest` locking the catalogue against the code that names it (07/09/2026)
- KnpPaginatorBundle leaves the bundle's dependencies, nothing here having ever used it (25/08/2026)
- Rector caches in `.rector.cache` inside the repository, no longer in the directory shared by every repo - `composer rector` drops `--clear-cache` (25/08/2026)
- `bin/ci.sh` is CoreBundle's, leaving that cache out of the copy so the replay starts cold (25/08/2026)
- The counterpart, media and video fields no longer carry a `placeholder`, their label saying what is asked (24/08/2026)
- A lot of a lottery and a counterpart are drawn as `<article>`, each standing for one thing of its own (`Card:Card`'s `tag` prop) - the look does not move, every rule under `.card` being written on the class (24/08/2026)
- Removed `phpstan-baseline.neon`, as CoreBundle has none: what it hid is either fixed below or a motivated exception in `phpstan.dist.neon` (21/08/2026)
- `CrowdfundingCounterpart` gained the constructor initialising `$contributorCounterparts`, read by `getContributors()` but never written - calling it on a counterpart built with `new` would fatal (21/08/2026)
- `CrowdfundingFormFactoryInterface::create()`, `CrowdfundingServiceInterface::createForm()` answer `FormInterface`, the type the form factory actually returns (21/08/2026) [BC-Break]
- `CrowdfundingServiceInterface` declares `addNews()`, which the controller already called on it (21/08/2026)
- `CrowdfundingController` reads the visitor's id off the c975L `UserInterface`, Symfony's own carrying no `getId()` (21/08/2026)
- `LotteryService`, `CrowdfundingService`, `CrowdfundingCounterpartService` and the two lottery handlers drop the magic finders for `find()`, `findBy()` and `findOneBy()` (21/08/2026)
- `LotteryService::generateTicketNumber()` looked its number up wrapped in an array, which asked the database for an `IN` on one value (21/08/2026)
- The three services no longer inject what they never read: the entity manager, the two media repositories, the paginator and the lottery repository (21/08/2026)
- `CrowdfundingContributorCounterpart::$quantity` and `CrowdfundingCounterpart::$currency` drop the null their NOT NULL columns never hold (21/08/2026)
- `CrowdfundingSitemapProvider` drops the `?? []` on a `findAllSorted()` that answers an array, and `LotteryTicketsMessageHandler` its dead `empty()` guard (21/08/2026)
- The contributor is handed to PaymentBundle at `onBasketValidated()` and taken back at `onBasketPaid()` instead of travelling in the session: that hook is now also reached from the payment webhook - a request carrying no session of the contributor - so the contributor, their counterparts and their lottery tickets were silently lost for everyone who did not come back to the site before the webhook landed (21/08/2026) [BC-Break]
- `CrowdfundingBasketItemProvider` no longer injects `RequestStack`, having nothing left to read off the current request (21/08/2026)
- The counterparts no longer draw the basket count PaymentBundle's `Item:Quantity` carried, a component that bundle removed (20/08/2026)
- The smileys, the calendar icons and the `no-product-image.webp` are served from this bundle, the templates no longer pointing at `bundles/c975lshop/images/`, a folder ShopBundle renamed (19/08/2026)
- Added `sass/`, its compiled `public/css/styles.min.css` and `Service\StylesheetProvider`, carrying the campaign and lottery rules that lived in ShopBundle (19/08/2026)
- The README documents the assets to install and the stylesheet it now ships (19/08/2026)
- Composer's archive cache is carried from one run to the next, so a run whose resolved versions have not moved reaches the network for metadata alone - the archives are indexed on their own content, which owes nothing to a `composer.lock` this bundle still does not version (17/08/2026)
- The workflow runs on a push to main and on pull requests only, under a `concurrency` group that cancels a run the next push has superseded: dev carried the same commit, and the two twin runs resolved and downloaded the same packages at the same second (17/08/2026)
- `COMPOSER_TOKEN` is gone from the setup-php step: it never reached the archive downloads, which codeload.github.com serves through a cross-host redirect that drops the Authorization header, and no bundle of the Symfony ecosystem passes one either (17/08/2026)
- The workflow's `GITHUB_TOKEN` is pinned to `contents: read` rather than inheriting the repository's default write permissions: the checkout is the only step that reads it (17/08/2026)
- The Codacy token is declared on the job rather than on its own step, where the step's `if` could not read it: the condition was always false, so the coverage was never uploaded (17/08/2026)
- The templates state their page summary as `summarySocialNetwork`, the name both layouts read since UiBundle's was aligned on SiteBundle's (13/08/2026)
- The `Standard Symfony` step, absent from the workflow though `.php-cs-fixer.dist.php` was there, now runs in the CI (03/08/2026)
- Added the `qa` Composer script and its steps, which the CI workflow now calls (03/08/2026)
- Added `bin/ci.sh`, replaying the CI checks on dependencies freshly resolved from Packagist (03/08/2026)
- `php` is now required in `>=8.4` instead of `>=8.0` (30/07/2026) [BC-Break]
- The `symfony/*` requirements are now constrained to `^8.0` instead of `*` (30/07/2026) [BC-Break]
- The third-party requirements left in `*` are now bounded on their installed version (30/07/2026)
- The `c975l/*` requirements are now bounded on their major (30/07/2026)
- `Crowdfunding::$user`, `CrowdfundingCounterpart::$user`, `Lottery::$user` and `LotteryPrize::$user` are now typed `c975L\ConfigBundle\Contract\UserInterface` instead of `App\Entity\User` (30/07/2026) [BC-Break]
- `UserTrait` now assigns the logged-in user only when it implements `c975L\ConfigBundle\Contract\UserInterface` (30/07/2026)
- `CrowdfundingContributionHandler` now calls `find()` instead of Doctrine's `findOneById()` magic finder, which Doctrine ORM will drop (30/07/2026)
- Added `.codacy.yaml`, `phpcs.xml.dist` and `eslint.config.mjs` (30/07/2026)
- Applied PSR-12 to the codebase (30/07/2026)
- Added `.php-cs-fixer.dist.php`, applying the Symfony coding standards (30/07/2026)
- Added `phpstan.dist.neon`, running the static analysis at level 5 (30/07/2026)
- Added `phpstan-baseline.neon`, freezing the errors that predate the analysis (30/07/2026)
- Added the `CI` GitHub Actions workflow, running PSR-12, the static analysis, the tests and the coverage upload (30/07/2026)
- Move Media/CrowdfundingMedia/CrowdfundingCounterpartMedia/CrowdfundingVideo/LotteryVideo entities from ShopBundle into this bundle's own table (23/07/2026)
- Drop c975l/shop-bundle dependency from composer.json (23/07/2026)
- Register vich_uploader mappings for crowdfundings/crowdfundingsCounterparts in bundle config (23/07/2026)
- Added the Codacy grade badge to the README (30/07/2026)

## v0.1

- Bundle created, extracted from ShopBundle (crowdfunding + lottery, same table names, no data migration) (22/07/2026)
