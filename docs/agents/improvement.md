# Improving the environment

A mistake fixed once in code comes back; fix where it came from. Pick the target by where the lesson belongs:

- **This repo's code, checks or rules**: `/stape-dev:retro` (one session) or `/stape-dev:correct` (the repo's history).
- **This repo's `verify` skill**: a recipe that no longer matches the app, or a harness gap `/stape-dev:accept` hit → `/stape-dev:verify-skill` audit.
- **The toolkit itself**: a `/stape-dev:` skill or a Stape convention that misled, missed a step or has a wrong default, in any repo. Propose a GitHub issue on `stape-io/stape-ai-toolkit` naming the skill or section, what happened (the session's evidence, private data removed), and the change you suggest; create it after the user confirms (`gh issue create -R stape-io/stape-ai-toolkit`). Matt's vendored skills are copied unmodified: a lesson about one becomes a Stape convention or skill, never an edit to the copy.
