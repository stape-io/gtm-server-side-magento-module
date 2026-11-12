# Stape GTM Server Side for Magento 2

A Magento 2 module (`Stape_Gtm`) that loads Google Tag Manager, pushes ecommerce events to the data layer and sends order webhooks to the merchant's server GTM container. Name concepts with the terms in [GLOSSARY.md](GLOSSARY.md).

## Agent skills

Stape's conventions for agents, copied from the `stape-dev` plugin so agents without it get them too. In Claude Code these files replace the plugin's sections of the same name: when the plugin changes, copy its `agent-docs/` again.

Read before any task:

- [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md): Jira project, ticket types, status moves
- [docs/agents/branches.md](docs/agents/branches.md): branch names and the PR heading
- [docs/agents/triage-labels.md](docs/agents/triage-labels.md): triage labels and work queues
- [docs/agents/staper.md](docs/agents/staper.md): searching code in other `stape-io/*` repos
- [docs/agents/domain.md](docs/agents/domain.md): glossary and ADRs
- [docs/agents/verification.md](docs/agents/verification.md): when a ticket counts as done

The plugin's `jira-flow`, `review` and `improvement` sections only steer its own `/stape-dev:` skills, so they are not copied here.
