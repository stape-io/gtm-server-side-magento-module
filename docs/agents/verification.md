# Verification

A ticket is done when its behaviour is **proven on the surface the user sees**, not when the tests pass. Proof is a step of implementing:

- **`/stape-dev:implement`**: once the code is complete and `code-review` is through, before the commit that hands it over and before the PR, run `/stape-dev:accept <key>`; it sizes the proof to the diff. For a `Bug`, run it once more **before** writing the fix, to see the symptom red.
- **`/stape-dev:implement-spec`**: each implementer proves its own ticket; the integration branch gets one full `/stape-dev:accept <epic>` before the QA slice.
- **Mid-ticket**: slices are proven by `tdd`; drive the app only when a slice's behaviour cannot be seen any other way. One instance per session, reused while the branch is the same.

The result goes in the PR body's `## Evidence`, the `pr` template's section, or the section of the repo's own template that holds proof. When the user wants to commit or open the PR without proof, record `Not verified: <reason>` there and move on, without asking again. An app without a `verify` skill: offer `/stape-dev:verify-skill` once, then ship with `Not verified: no verify skill`.
