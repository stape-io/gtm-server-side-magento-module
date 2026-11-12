# Branches and PRs

Ticket work starts on its own branch, created from the default branch when you are on it: `<type>/DEV-<id>-<short-name>`, with `fix/` for a `Bug`, `research/` for a wayfinder research spike, and `feature/` for everything else (tasks, an Epic's integration branch); e.g. `feature/DEV-1234-consent-export`. Work spanning several repos uses the same branch name in each.

A PR description opens with `# [DEV-1234](https://stape.atlassian.net/browse/DEV-1234)`.

In the `pr` template's Summary, a visual that shows a change (a file tree, a call tree, a flow, config) is a fenced `diff` block: unchanged lines start with a space, added lines with `+`, removed lines with `-`, so GitHub colours them. A plain `text` block is only for a shape with no before, such as a new module's call tree.
