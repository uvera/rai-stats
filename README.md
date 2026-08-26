# rai-stats

A self-hosted family finance app for Raiffeisen Bank Serbia accounts.

Each family member logs in, imports their own transactions from Raiffeisen's
retail internet banking (RaiOnline), and everyone can browse spending stats —
per account, per person, or across the whole household.

## Features

- **Import wizard** — logs into RaiOnline with a member's own credentials
  (via push notification), pulls transactions for a chosen date range per
  account, and stores them without ever persisting the bank password.
- **My Stats / Family Stats** — Filament dashboards with an income/expense
  chart, spend-per-account and spend-per-place breakdowns, a largest
  transactions table, recurring charges detection, and a spender leaderboard.
- **Transactions** — a searchable, filterable table of every imported
  transaction, scoped to what the logged-in user is allowed to see.
- **Users** — admin-managed accounts with `admin` / `user` roles.

## Stack

- Laravel + Filament v5
- PostgreSQL
- [DDEV](https://ddev.com) for local development

## Getting started

```sh
ddev start
ddev composer install
ddev artisan migrate
ddev launch
```

Copy `.env.example` to `.env` first if DDEV hasn't already generated one for
you, and run `ddev artisan key:generate` if `APP_KEY` is empty.

## Running tests

```sh
ddev artisan test
```

Tests run against a real Postgres database (see `phpunit.xml`), not SQLite —
there's no in-memory substitute configured.

## Deployment

See [docs/deployment.md](docs/deployment.md) for running this outside DDEV —
LXC/systemd and Docker/docker-compose setups, required background processes
(queue worker, scheduler), and environment variables.

## Status

Early build, in progress via the [Hedgehog](https://github.com/skyf0xx/hedgehog)
build discipline.

## Note on the previous version of this repo

Earlier commits in this repo's history contain a Go CLI
([originally forked from savely-krasovsky/raiffeisen-retail-api](https://github.com/savely-krasovsky/raiffeisen-retail-api))
that was used to manually export transactions to JSON for ad-hoc analysis.
That logic has been ported natively into this app's import wizard; the
standalone CLI is no longer maintained here.
