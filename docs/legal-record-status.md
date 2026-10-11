# Legal-record status boundary

`MotionStatusPresenter` delegates its value-only classification and date
formatting to `StatusPresentation`. Both are presentation only. `Motion.status`, the configured
status label, status links, custom status text, dates, permissions, and
workflow actions remain owned by the production-pinned Antragsgrün models.

The presenter may add a status classification (as a styling hook) and a date,
but it must not write model
state, translate a configured workflow into a guessed outcome, remove a source
status row, or replace status context. It shows no status wording of its own:
an earlier summary repeated the generic status name, which duplicated the
source value and contradicted it for screened amendments ("Geprüft"). Unknown and custom states deliberately
receive no guessed explanation. The upstream formatted status is retained as
the visible source value.

Recovery is the only user route overridden by this plugin. The route is mapped
only when the plugin is active and refuses to replace an existing conflicting
controller map. Recovery token issuance is serialized per user row. A disabled
mail transport creates no token; a reported transport failure rolls back the
upstream token and rate-limit write. A pre-existing error flash is consumed
around the upstream call and restored only after a successful commit, so stale
session state cannot trigger a rollback for a later request.

This is a compatibility boundary, not a second workflow engine. Any change to
status semantics, authorship, timestamps, votes, amendments, or publication
history requires fixture-backed product and legal-owner acceptance.
