# Review

`/stape-dev:code-review` checks two axes, Standards and Spec; neither hunts for bugs. Whether it runs on its own or inside `implement` / `implement-spec`, it runs with these additions.

**Comments first.** Before the axes run, `/stape-dev:no-comments` cleans the diff's comments; its `MUST KILL` flags join the Standards findings as **Act on**.

**A third axis, Risk**, as a third parallel sub-agent with a fresh context, reported under `## Risk`. Its brief: on code the diff adds or modifies only, find

- **bugs**, each traced end to end to the call chain that triggers it;
- **security**: auth and permission checks, injection, secrets in code or logs, personal or consent data sent or stored where it should not be;
- **breakage elsewhere**: consumers of every **boundary** the diff crosses (the list is in `/stape-dev:accept`'s Blast radius), in this repo and across `stape-io/*` through staper;
- **devex**: a changed env var, port, secret source or a new manual step that breaks running the app locally;
- **gate leaks**: behaviour meant to sit behind a feature flag, plan or internal-only check that reaches everyone.

Research is finished before a finding is written: when the other side of a call is readable (here or in staper), read it instead of writing "unless the backend handles it". A breakage the change clearly intends is skipped unless its full impact looks missed. High only when a reachable path breaks users, money, data or security; otherwise Medium or Low. Under 400 words.

**Three more smells for the Standards baseline**, judgement calls that a documented repo standard overrides: a file the diff pushes past 1000 lines; an ad-hoc conditional bolted into an unrelated flow; logic outside the layer that owns its concept, or a near-copy of a helper the repo already has.

**Judgment before the report.** Within each axis (never across them), sort findings into **Act on** (would block a real PR), **Consider** (real, worth weighing now), **Noted** (valid, not actionable) and **Dismissed** (wrong, a nit, a preference, or missing context, each with its reason). Reviewers pad a clean diff with nits; when an axis has only nits, say the code is fine on that axis. Only after this, if the branch has a PR, read its review comments (`gh pr view --comments`, `gh api repos/<owner>/<repo>/pulls/<n>/comments`) and add any valid finding the axes missed, attributed to its author.

**Deep review**, on request only: the Risk axis runs as several sub-agents on different models (e.g. opus, sonnet, haiku), all with the same brief. A finding two or more of them raise independently ranks first; a lone one is read and weighed. It costs several times a normal review, so offer it once, without running it, when the diff touches payments or billing, auth, a migration, or consent handling.
