<?php

namespace App\Support;

/**
 * Hover text for figures whose meaning is a formula, a cutoff, or a heuristic.
 * My Stats, Family Stats, and per-person stats share the bank strings.
 */
final class StatHints
{
    public const TRANSACTIONS = 'Posted transactions in this range. Pending holds are left out, because they are not a final amount.';

    public const AVERAGE_SPEND = 'The average size of an expense in this range. Income is left out. Each currency is averaged on its own.';

    public const ATM = 'Cash withdrawals. The bank sends these as other payments whose place or description mentions an ATM, bankomat, or withdrawal. After the withdrawal the money is cash, so later spending is outside these stats.';

    public const NET = 'Income minus expenses. Below zero means spending was larger than income.';

    public const SAVINGS_RATE = 'Net divided by income for this whole range. A card is shown only when that currency has income. Below zero means spending was larger than income.';

    public const SAVINGS_RATE_CHART = 'Net divided by income for each period. A period with no income is left blank. Each currency is its own line.';

    public const BILLS = 'A place counts as a bill when it appears in at least 3 different months and the amounts stay within 15% of their average. Bills is what was spent at those places. Everything else is the remaining expenses.';

    public const CARD_WEEKDAY = 'Card payments only. These are POS charges. Transfers, cash withdrawals, and income are left out.';

    public const RECURRING = 'A place that appears in at least 3 different months, with amounts within 15% of their average. This is a rough guess at subscriptions and regular bills.';

    public const LARGEST = 'The biggest amounts in this range, money in and money out together. A large deposit ranks with a large payment.';

    public const LEADERBOARD = 'Income and spending for each person in this range, across the whole household.';

    public const TOP_PLACES = 'The 10 places with the most spending in this range.';

    public const SPEND_OVER_TIME = 'The 5 places with the most spending, drawn once per period chosen in the filter.';

    public const CATEGORY = 'Expenses grouped by category. Spending with no category is shown as Uncategorized.';

    public const INCOME_EXPENSE = 'Money in and money out for each period. The period filter switches month, quarter, and year.';

    public const AVERAGE_BASKET = 'Total spent divided by the number of receipts.';

    public const VAT = 'Tax estimated from the prices. Each line is treated as already including VAT, so the tax is the price minus the price divided by (1 + the rate). Lines with no rate are skipped.';

    public const LINKED = 'The share of receipts in this range that matched a bank payment.';

    public const CARD_SHARE = 'Linked receipt totals divided by household bank expenses on the same dates. Provider and account filters apply to the receipts. The bank total stays every expense in the range. Each currency is its own card.';

    public const STAPLES = 'Products bought in more than half the months of this range. A range shorter than two months stays empty, because a single month cannot separate a staple from a one-off.';

    public const STORE = 'Trips, total, and average basket for each store. The same name in two formats stays two rows.';

    public const TOP_PRODUCTS = 'The 15 products with the most spending. A different spelling counts as a different product.';

    public const PRODUCT_CATEGORY = 'Item spend by product category. Lines with no category are Uncategorized.';

    public const BASKET_OVER_TIME = 'Each month shows total spend and the average receipt.';

    public const WEEKDAY_GROCERY = 'Receipt totals by weekday, from Monday through Sunday.';

    public const TOP_PLACE_SHARE = 'How much of this currency\'s spending sits in the five places you paid the most.';

    public const UNCATEGORIZED_SPEND = 'Share of expenses that still have no category.';

    public const PLACE_FREQUENCY = 'How many times you paid a place, next to the average amount. A place you visit often for a small amount is a different decision from one large bill.';

    public const UNUSUAL_CHARGE = 'A payment more than twice the average of the other payments at that same place. The place needs at least three payments in this range, so a first visit is not flagged.';

    public const FIRST_PLACE = 'Places whose earliest transaction in your history falls inside this range. Older visits outside the dates keep a place off this list.';

    public const LINES_PER_BASKET = 'Average number of lines on a receipt in this range.';

    public const UNCATEGORIZED_ITEMS = 'Share of item spend that has no product category.';

    public const CASH_GAP = 'Receipts in this range with no matched bank payment, shown next to household ATM withdrawals on the same dates. When the two move together, those groceries were likely paid in cash.';

    public const VAT_BY_RATE = 'Spend and the tax inside it, split by the rate printed on the line. Lines with no rate are their own group.';

    public const PRICES_MOVED = 'Products bought at least three times in this range whose latest unit price is above the average of those purchases.';

    public const UNUSUAL_BASKET = 'A basket more than twice the average of the other baskets at that store. The store needs at least three trips in this range, so a first visit is not flagged.';

    public const FIRST_PRODUCT = 'Products whose first purchase falls inside this range. Earlier history outside the dates still counts, so a long-running staple stays off this list.';
}
