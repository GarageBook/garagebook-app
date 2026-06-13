# Follow-up: public CTA visibility for private vehicles

## Problem
The CTA `Open publieke voertuigpagina` is currently shown for private vehicles. In that case the link can lead to a `404` because the public garage route only resolves public vehicles.

## Scope to align later
- maintenance list/detail context
- vehicle detail

## Preferred solution
- Show `Open publieke voertuigpagina` only when the vehicle is public/shareable.
- Keep `Exporteer deelbare PDF` visible if that export is intentionally allowed as a private/shareable output.
- Add regression tests for public/private CTA visibility in both contexts.

## Explicitly out of scope for commit `dc17856`
This follow-up should not change the current shared-action rollout. The current behavior was intentionally kept identical to the maintenance page to avoid mixing behavior changes into that commit.
