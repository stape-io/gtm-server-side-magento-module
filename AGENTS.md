# Stape GTM Server Side for Magento 2

A Magento 2 module (`Stape_Gtm`) that loads Google Tag Manager, pushes ecommerce events to the data layer and sends order webhooks to the merchant's server GTM container. Name concepts with the terms in [GLOSSARY.md](GLOSSARY.md).

## Stape conventions

Claude Code gets Stape's full conventions from the `stape-dev` plugin at session start. Agents without the plugin follow these:

- **Jira**: tickets live in project `DEV` on `stape.atlassian.net`.
- **Branches**: `feature/DEV-<id>-<short-name>`, with `fix/` for a `Bug`.
- **PRs**: the description opens with `# [DEV-<id>](https://stape.atlassian.net/browse/DEV-<id>)`. Proof goes in an `## Evidence` section, or `Not verified: <reason>` when there is none.
