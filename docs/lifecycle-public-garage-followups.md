# Public garage lifecycle follow-ups

Status: prepared for a later sprint. No production sending is activated by this document or by the condition classes added with it.

## Current lifecycle tracks

The v1 lifecycle flow in `App\Services\LifecycleEmailService` is the flow that can queue real email jobs through `garagebook:send-lifecycle-emails` and `SendLifecycleEmailJob`. It already handles no vehicle, no maintenance, after first maintenance and inactivity with template-backed emails, unsubscribe checks and a 7-day frequency cap.

The v2 rule engine in `App\Services\Lifecycle\Rules` is shadow-only. `garagebook:lifecycle:evaluate-rules` evaluates rules, stores `lifecycle_rule_evaluations` unless `--no-store` is used, and can preview template eligibility. It does not queue or send emails.

## Prepared conditions

`App\Services\Lifecycle\Conditions\PublicGarageLifecycleConditions` exposes reusable checks for:

- public vehicle without photo;
- public vehicle without maintenance;
- low garage quality score.

These conditions require a public slug, exclude outreach/demo vehicles, and do not send or queue anything.

## Follow-up sprint

A later lifecycle sprint should decide whether public-garage nudges belong in v1 sending or in a promoted v2 flow. That sprint must define templates, cooldowns, overlap rules with existing photo/no-maintenance mails, and explicit activation tests before any real campaign is enabled.
