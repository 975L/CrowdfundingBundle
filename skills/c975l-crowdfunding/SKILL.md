---
name: c975l-crowdfunding
description: "Use this skill when working on crowdfunding campaigns or the lottery of a c975L site — a campaign and its counterparts, contributing through the basket, the news an author publishes, the campaign's media and videos, and the lottery tied to a campaign with its prizes, its tickets and its draw. Covers what the basket copies of a counterpart, when a contributor row is written, and why a ticket is drawn only once. Triggers on: Crowdfunding, CrowdfundingCounterpart, CrowdfundingContributor, CrowdfundingContributorCounterpart, CrowdfundingNews, CrowdfundingMedia, CrowdfundingVideo, Lottery, LotteryPrize, LotteryTicket, LotteryVideo, CrowdfundingBasketItemProvider, CrowdfundingService, CrowdfundingCounterpartService, LotteryService, crowdfunding_index, crowdfunding_display, lottery_display, lottery_draw_prize, drawWinner, generateTicketsForContributor, generateTicketNumber, validateAddition, validateCheckout, onBasketValidated, onBasketPaid, limitedQuantity, orderedQuantity, lotteryTickets, amountGoal, amountAchieved, beginDate, endDate, CrowdfundingEmailTemplateProvider, CrowdfundingEmailFactory, CrowdfundingEmailSender, crowdfunding_contribution, lottery_tickets, lottery_ticket_winner, CrowdfundingSitemapProvider, CrowdfundingBackupPathProvider, CrowdfundingBlockOwnerResolver, c975l:crowdfunding:migrate-legacy-tables, shop_crowdfunding, crowdfunding_media, CrowdfundingGuidedProjectProvider, ProcedureProvider, procedures.json, filmer-tirage-loterie, publier-actualite-campagne, crowdfunding_narration."
---

# c975L CrowdfundingBundle — campaigns, counterparts and the lottery

> A campaign collects money against counterparts, and may run a lottery whose tickets are earned by contributing. The money itself is never handled here: contributing is putting a counterpart in PaymentBundle's basket.

**Package:** `c975l/crowdfunding-bundle` · **Bundle:** `c975L\CrowdfundingBundle\`

**Key source paths** (relative to the package root):
`src/Entity/Crowdfunding.php`, `src/Entity/CrowdfundingCounterpart.php`, `src/Entity/CrowdfundingContributor.php`, `src/Entity/Lottery.php`, `src/Entity/LotteryPrize.php`, `src/Entity/LotteryTicket.php`, `src/Service/CrowdfundingBasketItemProvider.php`, `src/Service/LotteryService.php`, `src/Service/CrowdfundingService.php`, `src/Controller/CrowdfundingController.php`, `src/Controller/LotteryController.php`, `src/Controller/Management/CrowdfundingCrudController.php`, `src/Email/CrowdfundingEmailTemplateProvider.php`, `src/Email/CrowdfundingEmailFactory.php`, `src/Email/CrowdfundingEmailSender.php`, `src/Management/CrowdfundingSitemapProvider.php`, `src/Management/CrowdfundingBlockOwnerResolver.php`, `src/Management/CrowdfundingGuidedProjectProvider.php`, `src/Management/ProcedureProvider.php`, `config/procedures.json`, `src/Command/MigrateLegacyTablesCommand.php`, `templates/crowdfunding/`, `templates/lottery/`, `templates/emails/slots/`

**Related skills:** `c975l-payment-items` and `c975l-payment-checkout` in `c975l/payment-bundle`; `c975l-config` and `c975l-management` in `c975l/core-bundle`.

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

`Crowdfunding` holds the goal and what was collected — both in cents, as every amount of the ecosystem — the dates it runs between, the author's presentation, and six collections its edit screen composes in place: counterparts, medias, videos, news, contributors and lotteries. It implements `HasBlocksInterface`, so its public page is composed in the back office with UiBundle's own kinds on top of the sections above.

- `Repository\CrowdfundingRepository::findOneBySlug()` is the lookup the page goes through: it fetches the seven collections in one query, where the magic finder would issue one per collection and per campaign.
- `Service\CrowdfundingService::addNews()` is the only write a visitor reaches — the campaign's author, or an admin, publishes a follow-up from the public page.
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

A `Lottery` hangs from a campaign, is reached at `/shop/lottery/{identifier}`, and holds up to five `LotteryPrize` rows ranked 1 to 5. `Listener\LotteryListener` draws its public identifier on the first save — `XXX-9999-9999`, from an alphabet without the vowels that read alike.

Tickets are never bought: `Service\LotteryService::generateTicketsForContributor()` writes one per unit taken, times what the counterpart entitles to, once for each lottery the campaign runs. `generateTicketNumber()` retries a taken number up to twenty times rather than looping on a table whose numbers are all used.

**`drawWinner()` is where the care is.** It answers null for a rank the lottery has no prize for, hands back the winner a prize already holds rather than redrawing it, and **takes out of the draw** every ticket already holding one of that lottery's prizes — by identity, not by id, two tickets not yet flushed both carrying a null one. A prize is drawn once, and one ticket never wins twice.

The draw itself is a `POST` on `lottery_draw_prize`, refused to anybody but the `site-role-admin` bar. The wheel that spins in front of it is the `lottery` Stimulus controller, registered lazily by `assets/controllers.js`.

## The three emails

Composed in the back office, not in a Twig file: `Email\CrowdfundingEmailTemplateProvider` declares `crowdfunding_contribution`, `lottery_tickets` and `lottery_ticket_winner` as `EmailTemplate` rows, seeded by `c975l:ui:email-templates:ensure` and rendered by UiBundle's renderer, which falls back on the declaration when a row was deleted.

What the code computes lives in `templates/emails/slots/` — the counterparts taken, the ticket numbers, the prize won — and is handed to the template under its slot name. The sentences around them belong to the admin.

Each goes out in the language the contribution was made in: `CrowdfundingContributor::getLocale()` is read off the basket at payment, a lottery being drawn long after the contributor's own visit, and `Email\CrowdfundingEmailSender` switches the translator over for the whole build.

## Traps

- **The date columns are `DATETIME_MUTABLE`.** A `\DateTimeImmutable` handed to any setter of this bundle is refused by Doctrine at flush. The one exception is Vich's own `setUpdatedAt()`, which takes an immutable.
- **The tickets are announced after the flush.** `onBasketPaid()` dispatches `LotteryTicketsMessage` once the contributor has the id the database assigned; dispatched before, the message is handed a null it declares an int.
- **Labels are resolved in the `crowdfunding` domain**, this bundle's own. It does not depend on ShopBundle, and a key read in the `shop` domain shows raw on a site running a campaign without a shop.
- **The legacy tables.** A site installed before the rename runs `c975l:crowdfunding:migrate-legacy-tables` once: it renames the `shop_crowdfunding*` tables, moves the media folders and rewrites the stored filenames. Idempotent — running it on a fresh install is a no-op.

## Guided projects and procedures

`Management\CrowdfundingGuidedProjectProvider` contributes the back-office parcours, in the 9000 block, all of them walking the single CRUD to the fieldset they are about. Their labels and descriptions are resolved in the `crowdfunding` domain, their spoken `narration` in `crowdfunding_narration`, which stops at `en` and `fr`.

What no parcours can reach is written as a procedure instead, read by `Management\ProcedureProvider` from `config/procedures.json`: `filmer-tirage-loterie`, because a draw happens on the lottery's public page and has no second take, and `publier-actualite-campagne`, because the CRUD declares no `news` collection and the form only exists on the campaign's public page.

- A step pointing at a field nested in a collection entry is preceded by one opening the entry: EasyAdmin folds each one onto its title, and highlighting a folded field outlines nothing.
- A nested field is reached by a `row_attr` marker set in its form type, never by its indexed id, which changes with the entry's position.

## Components

The public pages are drawn from components rather than from one template: `<twig:c975LCrowdfunding:Crowdfunding:CounterParts/>`, `<twig:c975LCrowdfunding:Crowdfunding:AmountAchieved/>`, `<twig:c975LCrowdfunding:Lottery:Lottery/>`, `<twig:c975LCrowdfunding:Lottery:Prizes/>` and their siblings under `templates/components/`. A site overrides one of them rather than the page holding it.
