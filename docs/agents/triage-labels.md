# Triage labels

Triage roles are Jira labels of the same name (`labels` field): `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. A triage label stays for the ticket's whole life; the status moves it through the workflow. A work queue is a label plus a status filter, e.g. `project = DEV AND labels = "ready-for-agent" AND statusCategory = "To Do"`. A `wontfix` decision lives on the ticket (label, reason in a comment, status Won't Do); the repo's `.out-of-scope/` notes are kept only where a repo already has them.
