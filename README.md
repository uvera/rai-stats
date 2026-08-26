# rai-stats

A self-hosted family finance app for Raiffeisen Bank Serbia accounts.

Each family member logs in, imports their own transactions from Raiffeisen's
retail internet banking (RaiOnline), and everyone can browse spending stats —
per account, per person, or across the whole household.

## Stack

- Laravel + Filament v5
- PostgreSQL
- [DDEV](https://ddev.com) for local development

## Status

Early build, in progress via the [Hedgehog](https://github.com/skyf0xx/hedgehog)
build discipline.

## Note on the previous version of this repo

Earlier commits in this repo's history contain a Go CLI
([originally forked from savely-krasovsky/raiffeisen-retail-api](https://github.com/savely-krasovsky/raiffeisen-retail-api))
that was used to manually export transactions to JSON for ad-hoc analysis.
That logic has been ported natively into this app's import wizard; the
standalone CLI is no longer maintained here.
