# Jira flow: maps, specs and slices

**Long bodies.** For an Epic spec or a map's Decisions so far, write the description as ADF and fold each long section (User Stories, Implementation Decisions, Testing Decisions, Decisions so far) into an `expand` node titled with the section name; Problem, Solution and Out of Scope stay open. Folding is presentation: every section keeps its full wording and numbering. A section still past the 32,000-character limit moves verbatim into a comment titled with its name, leaving a one-line pointer.

## Wayfinding operations

- **Map**: a `Spike` labelled `wayfinder:map`. Legacy `wayfinder-` labels (hyphen) mean the same.
- **Decision tickets**: `Sub-task`s of the map labelled `wayfinder:<type>`.
- **Frontier**: `parent = <map> AND statusCategory != Done AND assignee IS EMPTY`, minus tickets with an open blocker; first in map order wins. Claim by assigning.
- **Resolve**: comment the answer, transition to done, append a pointer to the map's Decisions so far.
- **Spec**: `/stape-dev:to-spec` publishes an `Epic` that points at the decision sub-tasks. Then set the map's `parent` to the Epic and transition the map to done; a `Spike` needs an assignee and Story Points first.

## Implementation tickets

- A tracer-bullet slice is one task per layer it touches: a `Backend Task` and/or a `Frontend Task` under the Epic, the pair linked `Relates`.
- From a map: link each task `Relates` to the decision sub-task it implements.
- **QA slice**: every Epic gets one more `Task`, `Run Autotests suite on feature branch`, labelled `QA_task`, blocked by every other ticket of the Epic. Add it after the others, unasked.
