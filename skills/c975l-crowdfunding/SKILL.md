---
name: c975l-crowdfunding
description: "Use this skill when working on crowdfunding campaigns or the lottery of a c975L site — a campaign and its counterparts, contributing through the basket, the news an author publishes, the campaign's media and videos, and the lottery tied to a campaign with its prizes, its tickets and its draw. Covers what the basket copies of a counterpart, when a contributor row is written, and why a ticket is drawn only once. Triggers on: Crowdfunding, CrowdfundingCounterpart, CrowdfundingContributor, CrowdfundingContributorCounterpart, CrowdfundingNews, CrowdfundingMedia, KIND_HERO, KIND_SLIDE, getHeroes, getSlides, mediasOfKind, CrowdfundingVideo, Lottery, LotteryPrize, LotteryTicket, LotteryVideo, CrowdfundingBasketItemProvider, CrowdfundingService, CrowdfundingCounterpartService, LotteryService, crowdfunding_index, crowdfunding_display, crowdfunding_preview, lottery_display, lottery_draw_prize, drawWinner, generateTicketsForContributor, generateTicketNumber, validateAddition, validateCheckout, onBasketValidated, onBasketPaid, limitedQuantity, orderedQuantity, lotteryTickets, amountGoal, amountAchieved, beginDate, endDate, hidden, isDeleted, setIsDeleted, deleteEntity, deletePermanently, restore, trashAction, writeGoneRedirect, CrowdfundingEmailTemplateProvider, CrowdfundingEmailFactory, CrowdfundingEmailSender, crowdfunding_contribution, lottery_tickets, lottery_ticket_winner, CrowdfundingSitemapProvider, CrowdfundingBackupPathProvider, CrowdfundingBlockOwnerResolver, crowdfunding_media, CrowdfundingGuidedProjectProvider, ProcedureProvider, procedures.json, filmer-tirage-loterie, publier-actualite-campagne, crowdfunding_narration, crowdfunding_slider, crowdfunding_counterparts, crowdfunding_lottery, crowdfunding_campaigns, crowdfunding_block_campaign, crowdfunding_block_campaigns, crowdfunding_block_sheet_kinds, crowdfunding_funding_state, CrowdfundingBlockExtension, CrowdfundingFundingExtension, CrowdfundingEditExtension, crowdfunding_edit_url, SliderBlockType, CounterpartsBlockType, LotteryBlockType, CampaignsBlockType, CrowdfundingBlockCacheTagProvider, CrowdfundingBlockCacheInvalidator, CrowdfundingCacheInvalidationListener, CrowdfundingBlockEditUrlProvider, CrowdfundingShowcaseProvider, CrowdfundingFilesHealthCheckProvider, files-crowdfunding, FundingBar, Hero, UseFor, highlightedSlug, lowStockThreshold, showTicketsCount, showUse, trans_default_domain, orphanRemoval, updateEntity, site-role-editor, site-role-admin."
---

# c975L CrowdfundingBundle — campaigns, counterparts and the lottery

> A campaign collects money against counterparts, and may run a lottery whose tickets are earned by contributing. The money itself is never handled here: contributing is putting a counterpart in PaymentBundle's basket.

**Package:** `c975l/crowdfunding-bundle` · **Bundle:** `c975L\CrowdfundingBundle\`

**Key source paths** (relative to the package root):
`src/Entity/Crowdfunding.php`, `src/Entity/CrowdfundingCounterpart.php`, `src/Entity/CrowdfundingContributor.php`, `src/Entity/Lottery.php`, `src/Entity/LotteryPrize.php`, `src/Entity/LotteryTicket.php`, `src/Service/CrowdfundingBasketItemProvider.php`, `src/Service/LotteryService.php`, `src/Service/CrowdfundingService.php`, `src/Controller/CrowdfundingController.php`, `src/Controller/LotteryController.php`, `src/Controller/Management/CrowdfundingCrudController.php`, `src/Email/CrowdfundingEmailTemplateProvider.php`, `src/Email/CrowdfundingEmailFactory.php`, `src/Email/CrowdfundingEmailSender.php`, `src/Management/CrowdfundingSitemapProvider.php`, `src/Management/CrowdfundingBlockOwnerResolver.php`, `src/Management/CrowdfundingGuidedProjectProvider.php`, `src/Management/ProcedureProvider.php`, `src/Management/CrowdfundingBlockEditUrlProvider.php`, `src/Management/CrowdfundingFilesHealthCheckProvider.php`, `src/Twig/Extension/CrowdfundingBlockExtension.php`, `src/Twig/Extension/CrowdfundingFundingExtension.php`, `src/Twig/Extension/CrowdfundingEditExtension.php`, `src/Entity/CrowdfundingMedia.php`, `src/Service/CrowdfundingBlockCacheTagProvider.php`, `src/Service/CrowdfundingBlockCacheInvalidator.php`, `src/Service/CrowdfundingShowcaseProvider.php`, `src/Listener/CrowdfundingCacheInvalidationListener.php`, `src/Form/Block/`, `templates/blocks/`, `config/services.yaml`, `config/procedures.json`, `templates/crowdfunding/`, `templates/lottery/`, `templates/emails/slots/`

**Related skills:** `c975l-payment-items` and `c975l-payment-checkout` in `c975l/payment-bundle`; `c975l-blocks`, `c975l-config` and `c975l-management` in `c975l/core-bundle`.

## The one rule

**This bundle never takes money.** A counterpart is a sellable item like any other, contributed to the basket through `Contract\BasketItemProviderInterface` under the kind `crowdfunding`. Everything about paying — the gateway, the webhook, when an order is real — belongs to PaymentBundle.

What this bundle owns is what happens on either side of that payment, in `Service\CrowdfundingBasketItemProvider`:

| Hook | What it does here |
| --- | --- |
| `validateAddition()` | refuses a counterpart of a campaign with no dates, not started, ended, or whose run is out |
| `validateCheckout()` | the same rules on the whole basket, plus the quantity against what the run has left |
| `onBasketValidated()` | hands the contributor's name and message to PaymentBundle, which keeps them **on the basket** |
| `onBasketPaid()` | writes the `CrowdfundingContributor`, bumps `orderedQuantity`, credits `amountAchieved`, draws the lottery tickets |

**Nothing travels in the session.** The webhook confirms a payment on a request of its own, carrying no session of the contributor: what `onBasketValidated()` returns is given back to `onBasketPaid()` by PaymentBundle. A contributor who never came back to the site is registered all the same.

## A campaign

`Crowdfunding` holds the goal and what was collected — both in cents, as every amount of the ecosystem — the dates it runs between, the author's presentation, and the collections its edit screen composes in place: counterparts, its medias under two fields of their own (see below), videos, news, contributors and lotteries. It implements `HasBlocksInterface`, so its public page is composed in the back office with UiBundle's own kinds **and three of its own** — see below.

- `Repository\CrowdfundingRepository::findOneBySlug()` is the lookup the page goes through: it fetches the seven collections in one query, where the magic finder would issue one per collection and per campaign.
- `Service\CrowdfundingService::addNews()` is the only write a visitor reaches — the campaign's author, or anyone holding `site-role-editor`, publishes a follow-up from the public page. That form only ever *adds*: a news is corrected and removed from the CRUD's `news` collection. The three dates are `Listener\CrowdfundingNewsListener`'s, both paths going through it — `prePersist` for `creation`, `publishedDate` and the first `modification`, `preUpdate` for the later ones — and `publishedDate` is only defaulted so an editor backdating an entry keeps the day they typed.
- `Management\CrowdfundingSitemapProvider` declares the index and one url per campaign; a finished campaign is rated as the archive it has become.
- Lotteries are deliberately kept out of the sitemap: their url is keyed by a 13-character identifier rather than a readable slug, and a drawn lottery is over.

## A counterpart

`CrowdfundingCounterpart` is what a contribution buys. Three fields decide whether it can still be taken:

| Field | Meaning |
| --- | --- |
| `limitedQuantity` | the run; `0` withdraws it from the page without deleting what was ordered, `null` is unlimited |
| `orderedQuantity` | what has been taken, bumped at `onBasketPaid()` and never typed by an admin |
| `lotteryTickets` | how many lottery tickets one unit earns, held between 0 and 10 |

**The basket keeps a copy, never a reference.** `toBasketData()` freezes the counterpart's title, price and picture the day it is added: a counterpart edited afterwards must not change what the visitor is buying.

## The lottery

A `Lottery` hangs from a campaign, is reached at `/crowdfunding/lottery/{identifier}`, and holds up to five `LotteryPrize` rows ranked 1 to 5. `Listener\LotteryListener` draws its public identifier on the first save — `XXX-9999-9999`, from an alphabet without the vowels that read alike.

`drawDate` is a naive `DATETIME` — the wall time typed in the back office, in no timezone of its own — while the pages print it through `|date(fmt, app.session.get('user_timezone', 'Europe/Paris'))`, which converts it **from PHP's own default timezone**. The two only agree while PHP runs in the timezone the campaign was typed in: a `date.timezone` left on UTC shifts every draw date by the offset, and shifts nothing in the database. Read a wrong hour on the page as a `date.timezone` to check before touching a row.

Tickets are never bought: `Service\LotteryService::generateTicketsForContributor()` writes one per unit taken, times what the counterpart entitles to, once for each lottery the campaign runs. `generateTicketNumber()` retries a taken number up to twenty times rather than looping on a table whose numbers are all used.

**`drawWinner()` is where the care is.** It answers null for a rank the lottery has no prize for, hands back the winner a prize already holds rather than redrawing it, and **takes out of the draw** every ticket already holding one of that lottery's prizes — by identity, not by id, two tickets not yet flushed both carrying a null one. A prize is drawn once, and one ticket never wins twice.

The draw itself is a `POST` on `lottery_draw_prize`, refused to anybody but the `site-role-admin` bar. The wheel that spins in front of it is the `lottery` Stimulus controller, registered lazily by `assets/controllers.js`.

## The campaign page and its three kinds

The page is no longer a fixed sequence: `templates/crowdfunding/display.html.twig` renders the funding, the campaign's own sections and the composed blocks, and **each hardcoded section steps aside as soon as a block takes it over**, read off `crowdfunding_block_sheet_kinds()`. A campaign carrying no block still reads in full, so nothing has to be migrated.

| Kind | Takes over | Form | Template |
| --- | --- | --- | --- |
| `crowdfunding_slider` | the campaign's slider | `Form\Block\SliderBlockType` | `templates/blocks/Slider.html.twig` |
| `crowdfunding_counterparts` | `<twig:c975LCrowdfunding:Crowdfunding:CounterParts/>` | `Form\Block\CounterpartsBlockType` | `templates/blocks/Counterparts.html.twig` |
| `crowdfunding_lottery` | `<twig:c975LCrowdfunding:Lottery:Lotteries/>` | `Form\Block\LotteryBlockType` | `templates/blocks/Lottery.html.twig` |

`crowdfunding_counterparts` lays the tiers as rows, not as a grid: one `<details name="counterparts">` per tier, so only one opens at a time — HTML's own exclusive accordion, the same row ShopBundle gives a product's items, so the block holds no column count. The picture is the row's full-height left column, reserved even on a tier that has none, and the price is a column of its own, on one line whatever a tier is named.

**None of them stores what to show, only how.** The medias, the tiers and the draws are read from the campaign's own rows at render time through `crowdfunding_block_campaign()` (`Twig\Extension\CrowdfundingBlockExtension`), which resolves the campaign off the `crowdfunding_display` or `crowdfunding_preview` route being rendered — so a block never goes stale against the funding, no form ever asks which campaign to show, and one of these kinds placed anywhere else renders nothing at all.

Where the campaign stands is worked out once by `crowdfunding_funding_state()` (`Twig\Extension\CrowdfundingFundingExtension`): whether it opened, whether it closed, how far it got, how many days are left. The funding rail and each tier's button read that same answer, so none can contradict another.

**Caching.** `crowdfunding_slider` is cached under the tag `Service\CrowdfundingBlockCacheInvalidator` drops whenever a campaign or one of its medias changes (`Listener\CrowdfundingCacheInvalidationListener`) — a tier or a contribution is left out, no cached render being drawn from either — UiBundle's own invalidation only ever knows the changed `Block`. The two others are declared **`cacheable: false`** in `config/services.yaml`, so `Service\CrowdfundingBlockCacheTagProvider` carries the slider alone: a cache entry never expires and no event fires the day a campaign ends, so a cached run of tiers would keep offering to contribute to a closed campaign, and the draws print their dates in the visitor's own timezone beside a button only an admin may see.

## The listing kind, on any page of the site

`crowdfunding_campaigns` is the one kind of this bundle placed on an ordinary page rather than on a campaign's own — a site's home page, say. It draws one `<twig:c975LCrowdfunding:Crowdfunding:Crowdfunding/>` per campaign, the very card `/crowdfunding` shows.

| Kind | Shows | Form | Template |
| --- | --- | --- | --- |
| `crowdfunding_campaigns` | one `Crowdfunding:Crowdfunding` card per campaign | `Form\Block\CampaignsBlockType` | `templates/blocks/Campaigns.html.twig` |

Being a section of a page and not a slot of a campaign, it declares **no `contexts`** — offered everywhere, where the three above name `crowdfunding` — and it is the only kind here carrying a head of its own: anchor, eyebrow, title, paragraph, link and colored flat (UiBundle's `HasAnchorFieldTrait` and `HasBackgroundFieldTrait`), so the section and its cards are one row in the back office instead of a `text_section` that has to be moved, hidden and translated beside it. Its only other field is a maximum, empty meaning every visible campaign.

The rows are read live by `crowdfunding_block_campaigns()`, which runs the very query the `/crowdfunding` page runs (`CrowdfundingRepository::findAllSorted()`) and reads nothing off the route — unlike `crowdfunding_block_campaign()`, whose null answer off a campaign page is what makes the three kinds above render nothing there. So a campaign opened, closed or reordered in the back office shows up without anyone touching the page. Declared `cacheable: false` for the reason the tiers are: each card counts the days left against today.

The template loops the cards itself rather than embedding `components/Crowdfunding/Crowdfundings.html.twig`: that one is the whole content of the `/crowdfunding` page, writing its own `<section id="crowdfundings">` and mounting its own editing pencil, both of which UiBundle's Blocks wrapper already draws around a block.

`Service\CrowdfundingShowcaseProvider` draws the slider alone in the block picker's showcase; the counterparts and the lottery are left out, both wrapping PaymentBundle's basket around a tier a visitor could click. `Management\CrowdfundingBlockEditUrlProvider` resolves the campaign's edit screen behind the front-end "Edit this block" button, through `Repository\CrowdfundingRepository::findByBlockIds()`.

## Opening, previewing and deleting a campaign

Three gestures, the same ones ShopBundle gives a product and SiteBundle gives a page.

**`Crowdfunding::$hidden`** — a campaign is written before it is opened. Hidden, it is out of `Repository\CrowdfundingRepository::findAllSorted()` (which the listing, the sitemap and the menu targets are all drawn from) and out of the basket (`Service\CrowdfundingBasketItemProvider::validateAddition()`), and its page answers 404. The column defaults to `false` so campaigns already online stay online; the property defaults to `true` so a new one starts hidden.

**`crowdfunding_preview`** (`/crowdfunding/{slug}/preview`, `CrowdfundingController::preview()`, behind `site-role-editor`) — that very page, with a banner and without the block cache (`BlockRenderContext::disableCache()`), so an editor reads what was just saved and nothing of it reaches a visitor's cache entry.

**The bar** — the whole CRUD sits behind `site-role-editor`, as SiteBundle's pages do: index, form, entity permission, QR code, *view on site* and *preview*. Only `restore()` and `deletePermanently()` are stricter, on `site-role-admin`, and so is `LotteryController::drawPrize()`.

**The recycle bin** — `CrowdfundingCrudController::deleteEntity()` writes `isDeleted` and removes nothing; `Crowdfunding::setIsDeleted(true)` hides the campaign with it, and its page answers 410. The index's `?trash=1` view is where `restore()` (comes back hidden) and `deletePermanently()` live, both `GET`, both csrf-guarded, both refusing a campaign that is not in the bin. Deleting for good erases the campaign and everything cascading from it, `Crowdfunding::$contributors` included (their counterparts and the lottery tickets go with them - without that cascade the database refused the DELETE outright), writes a `gone` Redirect at the campaign's url so the 410 outlives the row, turns the redirects pointing at it into `gone` rows, and leaves a path an admin already redirected alone.

## What a media is for

A campaign holds one collection of images and each row carries the use it was uploaded under, `CrowdfundingMedia::$kind`:

| Kind | Field on the campaign's screen | Read by |
| --- | --- | --- |
| `hero` | *Opening image* | `Crowdfunding:Hero`, the listing's thumbnail, the `og:image` |
| `slide` | *Slider* | the page's slider and the `crowdfunding_slider` block |

The field a file is dropped on is what sets the kind — `Crowdfunding::addHero()` and `addSlide()` write it, `getHeroes()` and `getSlides()` read it back through `mediasOfKind()` — so no form ever asks and no site names a kind. The opening image's field takes one file: `allowAdd()` is closed as soon as the campaign holds one, and laying another on the existing row is what replaces it.

`Twig\Extension\CrowdfundingEditExtension` gives the page's hardcoded sections the pencil a composed block gets from `CrowdfundingBlockEditUrlProvider`: `crowdfunding_edit_url(campaign, 'field')` writes UiBundle's `data-block-edit-url` on the section, and the campaign's form opens on that very field through `focusField`. It answers `null` to anyone but an editor, so nothing of it reaches a visitor's page.

## The files a campaign declares

`Management\CrowdfundingFilesHealthCheckProvider` (kind `files-crowdfunding`) checks every file a row of this bundle names against the disk: a campaign's pictures and video, each counterpart's picture, a lottery's video. Everything the check does is in UiBundle's `AbstractDeclaredFilesHealthCheckProvider` — this only names the rows, through `Repository\MediaRepository::findWithFilename()`. A counterpart and a lottery have no screen of their own, so their rows link back to the campaign they hang off.

## The three emails

Composed in the back office, not in a Twig file: `Email\CrowdfundingEmailTemplateProvider` declares `crowdfunding_contribution`, `lottery_tickets` and `lottery_ticket_winner` as `EmailTemplate` rows, seeded by `c975l:ui:email-templates:ensure` and rendered by UiBundle's renderer, which falls back on the declaration when a row was deleted.

What the code computes lives in `templates/emails/slots/` — the counterparts taken, the ticket numbers, the prize won — and is handed to the template under its slot name. The sentences around them belong to the admin.

Each goes out in the language the contribution was made in: `CrowdfundingContributor::getLocale()` is read off the basket at payment, a lottery being drawn long after the contributor's own visit, and `Email\CrowdfundingEmailSender` switches the translator over for the whole build.

## Traps

- **The date columns are `DATETIME_MUTABLE`.** A `\DateTimeImmutable` handed to any setter of this bundle is refused by Doctrine at flush. The one exception is Vich's own `setUpdatedAt()`, which takes an immutable.
- **The tickets are announced after the flush.** `onBasketPaid()` dispatches `LotteryTicketsMessage` once the contributor has the id the database assigned; dispatched before, the message is handed a null it declares an int.
- **Labels are resolved in the `crowdfunding` domain**, this bundle's own. It does not depend on ShopBundle, and a key read in the `shop` domain shows raw on a site running a campaign without a shop.
- **Nothing reads "the first media".** The opening image is `getHeroes()|first` and the slider is `getSlides()`, each filed under its own kind: a template falling back on `medias|first` opens the page on whichever plate an editor happened to sort first, and puts the same image in both places.
- **A counterpart already subscribed to, and a draw a ticket was sold on, cannot be removed from the form.** The campaign's five collections carry `orphanRemoval`, so taking one off the form is a DELETE; `CrowdfundingCrudController::updateEntity()` puts back a counterpart whose `getContributors()` is not empty and a lottery whose `getTickets()` is not empty, saying so in a flash. The counterpart would be refused by the database over `crowdfunding_contributor_counterpart`; the lottery would not be refused at all, cascading its own removal to its tickets and its drawn winner.
- **Between the tags of a component, every `|trans` names its domain.** What a template writes inside `<twig:…>…</twig:…>` is compiled as an embedded template, which the file's `trans_default_domain` does not reach, and the key prints raw; `TranslationDomainTest` holds the rule.

## Guided projects and procedures

`Management\CrowdfundingGuidedProjectProvider` contributes the back-office parcours, in the 9000 block, all of them walking the single CRUD to the fieldset they are about. Their labels and descriptions are resolved in the `crowdfunding` domain, their spoken `narration` in `crowdfunding_narration`, which stops at `en` and `fr`.

What no parcours can reach is written as a procedure instead, read by `Management\ProcedureProvider` from `config/procedures.json`: `filmer-tirage-loterie`, because a draw happens on the lottery's public page and has no second take, and `publier-actualite-campagne`, because a news is *published* from the campaign's public page - the correcting half does have a parcours, `crowdfunding-news`, the *News* panel being on the CRUD.

- A step pointing at a field nested in a collection entry is preceded by one opening the entry: EasyAdmin folds each one onto its title, and highlighting a folded field outlines nothing.
- A nested field is reached by a `row_attr` marker set in its form type, never by its indexed id, which changes with the entry's position.

## Components

The public pages are drawn from components rather than from one template: `<twig:c975LCrowdfunding:Crowdfunding:CounterParts/>`, `<twig:c975LCrowdfunding:Crowdfunding:AmountAchieved/>`, `<twig:c975LCrowdfunding:Lottery:Lottery/>`, `<twig:c975LCrowdfunding:Lottery:Prizes/>` and their siblings under `templates/components/`. A site overrides one of them rather than the page holding it.

Three of them draw the campaign page's own frame and are **rendered by the page, never composed as blocks**: `<twig:c975LCrowdfunding:Crowdfunding:Hero/>`, the opening image, which stands aside for a composed `banner_title`; `<twig:c975LCrowdfunding:Crowdfunding:FundingBar/>`, the rail beside the story; and `<twig:c975LCrowdfunding:Crowdfunding:UseFor/>`, what the money pays for, which `<twig:c975LCrowdfunding:Crowdfunding:Presentation/>` then drops through its `showUse` prop rather than saying twice. A campaign whose editor forgot to place the funding would be a campaign nobody can fund, which is why these are not kinds.
