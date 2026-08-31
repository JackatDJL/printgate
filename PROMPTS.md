# Printgate — Coding Agent Prompt Set

These prompts are designed to be copied one at a time into a coding agent.

## Global instructions for every prompt

Unless a prompt explicitly overrides these rules:

1. Read `README.md`, `ROADMAP.md`, and this `PROMPTS.md` before changing code.
2. Inspect the existing repository instead of assuming the previous prompt was implemented exactly as described.
3. Work only on the requested prompt scope.
4. Do not pre-implement later roadmap items just because they are nearby.
5. Preserve existing working behavior.
6. Prefer normal Laravel conventions over custom framework-like abstractions.
7. Keep CUPS/process execution behind the printing backend boundary.
8. Never construct shell command strings from user-controlled data.
9. Never require `sudo` from the running web application.
10. Add or update automated tests for the behavior introduced.
11. Run the relevant test suite, formatter, and static checks that already exist in the repository.
12. Update the corresponding checkbox(es) in `ROADMAP.md` only when the acceptance criteria are actually satisfied.
13. Do not mark an entire milestone complete merely because a scaffold exists.
14. If a requested assumption is impossible on the current host, implement the testable boundary and document exactly what remains to verify on a real CUPS host.
15. Do not add Rust, Docker, Redis, a separate Node server, or another database unless the prompt explicitly requests it or a hard technical requirement is demonstrated first.

At the end of every prompt, return:

```text
## Implemented
- ...

## Architecture / decisions
- ...

## Tests
- command: result

## Manual verification
- ...

## Roadmap
- checkboxes changed

## Remaining concerns
- ...
```

Keep that report factual. Mention failed checks.

---

# Milestone 0 — Repository foundation

## M0 / Prompt 1 — Bootstrap the application

```text
Implement Milestone 0, Prompt 1 for Printgate: bootstrap the Laravel application.

Read README.md, ROADMAP.md, and PROMPTS.md first.

Requirements:
- Create a conventional Laravel application in this repository.
- Use the current stable Laravel version supported by the environment.
- Configure SQLite for local development and tests.
- Use Bun for frontend package management / Vite commands.
- Add Livewire and a minimal Blade-based application shell.
- Create a simple Printgate landing/dashboard route that proves the app renders.
- Keep visual design intentionally minimal; do not build printing functionality yet.
- Ensure .env.example contains safe local defaults and no secrets.
- Add a small feature test proving the main page responds.
- Ensure framework caches/runtime files are ignored appropriately.
- Update only the matching M0.1 roadmap items that are truly complete.

Do not:
- integrate CUPS yet;
- add authentication yet;
- add Tailscale logic;
- add Docker;
- add a SPA framework;
- add Rust.

Run the relevant PHP and frontend checks available in the repository and report the exact results.
```

## M0 / Prompt 2 — Printing domain boundary

```text
Implement Milestone 0, Prompt 2 for Printgate: establish the printing domain boundary.

Read the planning files and inspect the existing Laravel code first.

Create a small, understandable printing module under app/Printing (or a clearly equivalent conventional location).

It must provide:
- a PrintBackend contract;
- immutable/value-oriented representations for a discovered printer;
- a print submission request;
- a returned print job / backend job reference;
- domain exceptions that can distinguish backend unavailable, validation failure, and submission failure;
- a FakePrintBackend suitable for feature tests.

Register the production/fake binding using normal Laravel service-container conventions, with tests able to swap in the fake.

Do not implement real `lp`, `lpstat`, `lpoptions`, or `cancel` process execution in this prompt.

Add unit tests for the domain objects and a feature/integration test proving a fake backend can be resolved and used through the application container.

Avoid overengineering:
- no repository pattern;
- no event bus;
- no DTO framework dependency;
- no Rust;
- no generic plugin architecture.

Update the relevant M0.2 roadmap items.
```

## M0 / Prompt 3 — Configuration and safe defaults

```text
Implement Milestone 0, Prompt 3 for Printgate: application configuration and safety defaults.

Add a dedicated Printgate configuration file and matching .env.example entries for:
- bind host, default 127.0.0.1;
- port, default 8787;
- maximum upload size;
- temporary document retention period;
- authentication mode placeholder, default local;
- printing backend selection only if the existing architecture needs it.

Define the private temporary document storage location using Laravel storage conventions outside the public web root.

Add validation/helpers where useful so invalid numeric limits fail clearly.

Add tests proving:
- the default bind address is loopback;
- upload/retention defaults are loaded;
- the temporary document disk/path is not public.

Do not build uploads or authentication yet.

If runtime server binding cannot be controlled purely by Laravel configuration, document the distinction clearly rather than pretending it is enforced.

Update the relevant M0.3 roadmap items.
```

---

# Milestone 1 — Local PDF printing vertical slice

## M1 / Prompt 1 — CUPS printer discovery

```text
Implement Milestone 1, Prompt 1 for Printgate: discover printers from CUPS.

Read the project planning documents and inspect the printing abstractions already present.

Implement the production CUPS-backed PrintBackend discovery path.

Requirements:
- Use Symfony Process or Laravel's process facilities in an argument-safe form.
- Do not invoke a shell.
- Discover printers configured in the host CUPS instance.
- Return at least stable printer name, human-usable display name where available, and a normalized basic state such as ready / stopped / unknown.
- Treat command failure, missing CUPS utilities, and malformed output as explicit backend errors rather than empty printer lists.
- Keep parsing separate from process execution so it is fixture-testable.
- Add representative command-output fixtures and unit tests.
- Add a printer list/status card to the existing dashboard using the PrintBackend contract.
- The UI should degrade gracefully when CUPS is unavailable.
- Do not query detailed printer capabilities yet.

Prefer the simplest reliable CUPS command(s) available on normal Linux installations. If exact output varies by locale, either force a stable locale for the child process or make parsing deliberately robust and document the choice.

Do not:
- submit print jobs yet;
- use sudo;
- configure CUPS;
- read arbitrary system files;
- add authentication.

Update the M1.1 roadmap checkboxes only when they are satisfied.
```

## M1 / Prompt 2 — Safe PDF upload

```text
Implement Milestone 1, Prompt 2 for Printgate: safe PDF upload and pending print-job creation.

Requirements:
- Add a “New print job” flow using normal Laravel/Livewire conventions.
- Accept PDF only in this milestone.
- Enforce configured maximum upload size.
- Validate extension/MIME/content as reasonably possible using framework facilities; do not trust the browser-provided MIME value alone.
- Generate a random internal filename.
- Store documents on the private temporary document storage configured earlier.
- Never place uploaded documents under public/.
- Preserve original filename only as display metadata.
- Persist a Printgate job record in SQLite with an application-generated ID and states sufficient for at least pending/submitted/failed/cancelled.
- Include timestamps, selected printer when chosen, original filename, internal storage reference, and later CUPS job reference.
- Do not expose the internal storage path directly to the browser.
- Add tests for valid upload, oversized upload, non-PDF rejection, randomized storage names, and path safety.

The flow may stop at a review/pending screen. Do not submit to CUPS in this prompt.

Keep the schema deliberately small and easy to migrate later.

Update M1.2 in ROADMAP.md.
```

## M1 / Prompt 3 — Submit PDF to CUPS

```text
Implement Milestone 1, Prompt 3 for Printgate: submit a pending PDF job to CUPS.

Requirements:
- The printer must be selected from printers returned by the PrintBackend.
- Reject stale/unknown printer names server-side even if the UI was manipulated.
- Extend the production backend to submit a PDF using CUPS.
- Execute the CUPS command using an argument array / no shell interpolation.
- Pass the application-owned stored file path only after resolving it through the private storage layer.
- Do not accept an arbitrary local path from a request.
- Capture and normalize the CUPS job identifier from successful submission.
- Persist submission result and backend job ID.
- On failure, set a useful failed state and retain a safe diagnostic message without leaking secrets or arbitrary document content.
- Prevent accidental double submission of the same pending job.
- Add unit tests around command construction/parsing and feature tests around success, invalid printer, backend failure, and duplicate submit.

Only implement the minimum print options required to print the document. Advanced options belong to Milestone 2.

Update M1.3 in ROADMAP.md.
```

## M1 / Prompt 4 — Job list and cancellation

```text
Implement Milestone 1, Prompt 4 for Printgate: basic job history, backend state lookup, and cancellation.

Requirements:
- Add a recent jobs view to the dashboard or a dedicated jobs page.
- Show Printgate job ID, original filename, printer, timestamps, local state, and CUPS job ID when present.
- Add backend support to inspect enough CUPS queue state to distinguish active/completed-or-gone where practical.
- Add cancellation using the CUPS job identifier.
- A cancellation request must resolve a Printgate-owned database job first; never provide a raw “cancel arbitrary CUPS job ID” endpoint.
- Make cancellation idempotent where practical.
- Handle a job already completed/absent from CUPS gracefully.
- Add tests for owned-job cancellation, missing job, malformed state output, and backend failure.

Do not add authentication yet, but structure controllers/actions so authorization can be inserted cleanly in Milestone 3.

At the end, manually describe the exact steps needed to verify the full Milestone 1 vertical slice on a Linux host with a real CUPS printer.

Update M1.4 and mark Milestone 1 acceptance complete only if it is genuinely supported.
```

---

# Milestone 2 — Print options and preview

## M2 / Prompt 1 — Parse printer capabilities

```text
Implement Milestone 2, Prompt 1 for Printgate: printer capability discovery.

Extend the CUPS backend so the application can ask for the selected printer's defaults and supported options.

Normalize at least these concepts when advertised by the printer:
- media/paper sizes;
- sides/duplex;
- color mode;
- orientation.

Requirements:
- Keep raw process output behind the backend.
- Use fixture-based parsers with multiple representative printer outputs.
- Preserve unknown values for diagnostics without exposing them as arbitrary executable options.
- Represent normalized capabilities with explicit value objects/arrays rather than free-form command fragments.
- Missing capabilities must be valid and should not crash the UI.

Do not yet wire every capability into job submission.

Update M2.1.
```

## M2 / Prompt 2 — Safe common print options

```text
Implement Milestone 2, Prompt 2 for Printgate: safe common print options.

Add form controls and backend mapping for:
- copies;
- page range;
- media/paper size;
- duplex/sides;
- color/grayscale if supported;
- orientation if supported.

Security requirements:
- Every submitted option is validated server-side.
- Printer-dependent values must be checked against the selected printer's discovered capabilities.
- Never accept an arbitrary CUPS `-o` string from the browser.
- Map normalized application values to a fixed set of known CUPS argument names.
- Keep process invocation argument-based with no shell.

UX requirements:
- Hide or disable unsupported options.
- Initialize controls from printer defaults where available.
- Clearly explain when the backend does not advertise an option.

Add tests that attempt to inject arbitrary option names/values and prove they are rejected.

Update M2.2.
```

## M2 / Prompt 3 — PDF preview and review

```text
Implement Milestone 2, Prompt 3 for Printgate: document preview and final review.

Requirements:
- Provide an in-browser preview for the private uploaded PDF without making the storage directory public.
- Serve preview content through an authenticated-ready application route/controller abstraction so Milestone 3 can protect it cleanly.
- Use safe content-disposition/content-type headers.
- Add a final review screen showing:
  - original filename;
  - preview;
  - selected printer;
  - copies;
  - page range;
  - paper;
  - duplex;
  - color mode;
  - orientation where used.
- If a reliable page-count mechanism already exists in the runtime, expose page count through a small isolated service; otherwise do not add a heavyweight dependency solely for this prompt.
- Preserve keyboard usability and semantic form labels.

Add tests for preview authorization hooks/path lookup and for review data.

Update M2.3.
```

## M2 / Prompt 4 — Defaults without mutating CUPS

```text
Implement Milestone 2, Prompt 4 for Printgate: safe printer/default behavior.

Requirements:
- Use CUPS-advertised defaults to preselect Printgate form values.
- Add optional Printgate-level defaults only where useful and clearly scoped.
- Never change system-wide or per-user CUPS defaults from the web application.
- Define deterministic fallback behavior when a printer advertises incomplete capability information.
- Add regression tests for printers with:
  - full capabilities;
  - no duplex;
  - monochrome-only;
  - missing/unknown defaults.

Do a small refactor pass if capability code became difficult to understand, but do not begin Milestone 3.

Update M2.4 and the Milestone 2 acceptance status.
```

---

# Milestone 3 — Authentication and remote access hardening

## M3 / Prompt 1 — Local authentication

```text
Implement Milestone 3, Prompt 1 for Printgate: local application authentication.

Requirements:
- Add Laravel-native session authentication for a local administrator user.
- Public self-registration must not exist.
- Protect dashboard, uploads, previews, print submission, jobs, and cancellation.
- Use Laravel's normal password hashing.
- Add login throttling/rate limiting.
- Keep CSRF protection enabled.
- Add logout.
- Return safe errors that do not reveal whether arbitrary usernames exist more than necessary.
- Existing Printgate jobs should be associated with the authenticated user, even if the application currently supports only one practical administrator.

Use the simplest conventional Laravel approach. Do not add OAuth, social login, Tailscale headers, passkeys, or a third-party auth SaaS.

Add feature tests proving unauthenticated access is blocked and authenticated access works.

Update M3.1.
```

## M3 / Prompt 2 — User management CLI

```text
Implement Milestone 3, Prompt 2 for Printgate: administrator management through Artisan.

Create `php artisan printgate:user` or a small family of clearly named Printgate user commands.

It must support:
- creating the initial administrator;
- changing/resetting the administrator password;
- listing only non-sensitive account identity information if listing is useful.

Requirements:
- prompt securely for passwords when interactive;
- do not print passwords;
- validate password confirmation;
- produce useful exit codes;
- make creation of an additional administrator explicit rather than accidental;
- work on a headless machine.

Add command tests.

Do not create a web-based user administration panel in this prompt.

Update M3.2.
```

## M3 / Prompt 3 — Tailscale Serve deployment mode

```text
Implement Milestone 3, Prompt 3 for Printgate: Tailscale-oriented private deployment documentation and runtime safety checks.

Do not automate account login or manipulate a user's Tailnet policy.

Requirements:
- Add clear production documentation for running Printgate on loopback and exposing it through Tailscale Serve.
- Include example commands, but keep hostnames/account-specific values generic.
- Explicitly distinguish private Tailnet access from public exposure.
- Do not recommend router port forwarding.
- Add a production startup/doctor warning when Printgate is configured to bind to a non-loopback address unless an explicit acknowledgement setting exists.
- Keep local development possible without Tailscale.
- If Tailscale is installed, optionally surface status information through an isolated diagnostic adapter; absence of Tailscale must not break local mode.

Do not implement trusted Tailscale identity headers yet.

Add tests for listener-safety configuration logic.

Update M3.3.
```

## M3 / Prompt 4 — Security audit milestone

```text
Implement Milestone 3, Prompt 4 for Printgate: focused security audit and regression pass.

Review the code that now exists. Do not assume earlier prompts were implemented securely.

Audit:
- all uploaded-file paths;
- preview/download routes;
- CUPS process construction;
- printer name validation;
- print-option validation;
- duplicate submission;
- job cancellation ownership;
- authentication/CSRF boundaries;
- log messages;
- exception rendering.

Fix concrete issues you find.

Add regression tests for meaningful attack cases, including at minimum:
- path traversal attempt;
- manipulated printer name;
- arbitrary CUPS option attempt;
- cancellation of another user's/non-Printgate job where representable;
- unauthenticated document preview.

Do not invent theoretical abstractions without a concrete bug or test need.

Return an explicit list of findings, including “no issue found” areas.

Update M3.4 and Milestone 3 acceptance only after the audit.
```

---

# Milestone 4 — Appliance mode

## M4 / Prompt 1 — Production runtime with FrankenPHP

```text
Implement Milestone 4, Prompt 1 for Printgate: a production FrankenPHP runtime.

Requirements:
- Add the minimal repository configuration/scripts needed to run the Laravel app under FrankenPHP on Linux without Docker.
- Preserve the existing normal Laravel development workflow.
- Ensure the documented production listener is loopback-only.
- Add a lightweight health endpoint intended for local service checks.
- Health must not leak sensitive configuration, printer names, filenames, or user data.
- Document required host packages/runtime assumptions.

Do not write the systemd unit yet unless a tiny example is required to make runtime invocation unambiguous.

Add relevant health/runtime tests possible within the repository.

Update M4.1.
```

## M4 / Prompt 2 — systemd service

```text
Implement Milestone 4, Prompt 2 for Printgate: systemd deployment.

Add a checked-in example service unit and installation documentation.

Requirements:
- run as a dedicated unprivileged `printgate` user where practical;
- use an explicit working directory;
- load environment from a root/admin-managed environment file;
- start after network-online and CUPS;
- restart on failure with sensible limits;
- bind Printgate only to loopback;
- use systemd hardening options where compatible with Laravel storage, SQLite, CUPS access, and FrankenPHP;
- make writable paths explicit;
- never run the app as root merely to reach the printer;
- document `systemctl` and `journalctl` commands for operation/debugging.

Do not create an opaque installer that silently modifies the host yet.

If CUPS group/socket permissions vary between distributions, document the variability and choose the least-privileged approach rather than adding blanket privileges.

Update M4.2.
```

## M4 / Prompt 3 — Doctor command

```text
Implement Milestone 4, Prompt 3 for Printgate: `php artisan printgate:doctor`.

The command should provide a concise human-readable diagnostic report and useful exit status.

Check at least:
- Laravel/application configuration;
- database connectivity;
- writable private document storage;
- configured upload/retention values;
- CUPS command availability;
- ability to query CUPS;
- number of discovered printers;
- production bind-address safety;
- required runtime binaries;
- Tailscale status only when relevant/available.

Requirements:
- separate warnings from fatal failures;
- do not expose secrets;
- keep checks independently testable;
- do not mutate the host;
- avoid requiring root.

Add command tests for healthy, warning, and fatal scenarios using fakes.

Update M4.3.
```

## M4 / Prompt 4 — Retention cleanup

```text
Implement Milestone 4, Prompt 4 for Printgate: document retention and cleanup.

Requirements:
- Add `php artisan printgate:cleanup`.
- Delete source/derived files after the configured retention period.
- Do not delete a file while its Printgate job is still in a state that requires it for pending submission.
- Make cleanup idempotent.
- Handle already-missing files gracefully.
- Keep minimal job metadata unless configuration says otherwise.
- Add normal Laravel scheduling or a documented systemd-timer approach for automatic execution. Prefer one mechanism and explain why.
- Add tests around expiration boundaries, active jobs, already-missing files, and repeated cleanup runs.

Do not add generalized filesystem cleanup outside Printgate-owned storage.

Update M4.4 and Milestone 4 acceptance.
```

---

# Milestone 5 — Broader document support

## M5 / Prompt 1 — JPEG/PNG conversion

```text
Implement Milestone 5, Prompt 1 for Printgate: image upload support through canonical PDF conversion.

Requirements:
- Extend accepted inputs to JPEG and PNG.
- Never print the raw image path directly through a new special case.
- Convert images into a canonical temporary PDF and pass that PDF through the existing preview/print pipeline.
- Preserve aspect ratio.
- Choose and document predictable page sizing/margins.
- Apply size/resource limits.
- Keep conversion code behind a small DocumentConverter abstraction.
- Ensure source and generated PDF are both Printgate-owned and covered by retention cleanup.
- Add conversion tests using small fixtures.

Do not add Office formats yet.

Update M5.1.
```

## M5 / Prompt 2 — Office document investigation and implementation

```text
Implement Milestone 5, Prompt 2 for Printgate: controlled Office-document conversion.

First inspect the current conversion architecture and document the exact proposed LibreOffice headless invocation and threat/operational tradeoffs.

Then implement support only for a small explicit allowlist such as DOCX and ODT if the host dependency can be detected cleanly.

Requirements:
- run conversion without shell interpolation;
- use a dedicated temporary working directory per conversion;
- enforce a timeout;
- enforce input-size limits;
- do not accept macros/executable formats merely because LibreOffice can open them;
- canonicalize output to PDF;
- surface safe conversion errors;
- cleanup all temporary artifacts;
- integrate with the existing preview/print pipeline;
- add tests around command construction and failure handling, with real conversion tests optional when LibreOffice is available.

If safe implementation requires a sandbox not available on the target deployment, stop short of pretending the risk is solved: implement the boundary, document the limitation, and leave the roadmap item unchecked where appropriate.

Update only M5.2 items truly achieved.
```

## M5 / Prompt 3 — Consolidate document pipeline

```text
Implement Milestone 5, Prompt 3 for Printgate: consolidate the document ingestion/conversion pipeline.

Review PDF, image, and Office handling for duplicated logic.

Produce one understandable pipeline where:
- source upload validation is type-specific;
- conversion is optional;
- the canonical printable artifact is a PDF;
- preview always references the canonical artifact;
- printing always references the canonical artifact;
- all files remain Printgate-owned;
- cleanup knows every related artifact.

Use explicit types/state rather than filename conventions where practical.

Add regression tests covering one file of every supported type through ingestion to a fake print submission.

Do not begin product-polish features.

Update M5.3 and Milestone 5 acceptance.
```

---

# Milestone 6 — Product polish

## M6 / Prompt 1 — Jobs UX

```text
Implement Milestone 6, Prompt 1 for Printgate: improve the jobs experience without changing the core architecture.

Add:
- clear job-detail pages;
- periodic status refresh using the simplest Livewire mechanism;
- useful backend failure messages;
- safe retry only for jobs where retry semantics are well-defined;
- a lightweight audit/event timeline if it can be implemented without logging document content.

Do not build WebSockets or Redis just for status updates.

Add tests for retry eligibility and status presentation.

Update M6.1.
```

## M6 / Prompt 2 — Multi-user decision and implementation gate

```text
Implement Milestone 6, Prompt 2 for Printgate.

Do not immediately add multi-user complexity.

First inspect actual current ownership/auth code and write a short ADR or docs note answering:
- what breaks if there are multiple users;
- which resources need ownership;
- whether printer-level permissions are necessary;
- whether single-user mode can remain simpler.

If the existing model already supports multiple users cleanly, add the minimal UI/admin functionality needed to make that real.

If not, implement only the smallest migrations/authorization changes justified by the analysis.

Preserve single-user deployment as a first-class path.

Update M6.2 only according to what is actually implemented.
```

## M6 / Prompt 3 — Optional Tailscale identity

```text
Implement Milestone 6, Prompt 3 for Printgate: investigate and, if safe, add optional trusted Tailscale identity mode.

This is security-sensitive.

Requirements before trusting proxy identity:
- prove/document the expected Tailscale Serve request path;
- ensure the backend listener is not directly reachable from untrusted interfaces;
- define exactly which headers/identity signals are trusted;
- reject/ignore spoofable identity headers outside that trusted deployment mode;
- keep local-password authentication available;
- make the auth mode explicit in configuration.

Add tests proving that merely sending the same HTTP headers directly to the application does not grant identity in modes where the trusted proxy requirement is not met.

If the deployment assumptions cannot be verified robustly in application code, document the limitation and do not silently weaken auth.

Update M6.3 only if the security model is defensible.
```

## M6 / Prompt 4 — Release readiness

```text
Implement Milestone 6, Prompt 4 for Printgate: first-release readiness.

Review the whole repository as if preparing an initial public FOSS release.

Complete or improve:
- installation documentation;
- configuration reference;
- update procedure;
- backup/restore guidance for SQLite and config;
- troubleshooting;
- security assumptions;
- supported input formats;
- supported/tested host environments;
- release checks in CI where appropriate.

Identify a suitable open-source license decision as a human-required item if none has been chosen; do not invent author/legal details.

Run the full test suite and all configured format/static checks.

Return:
- release blockers;
- known limitations;
- exact manual verification checklist for a fresh Linux+CUPS+Tailscale host.

Update M6.4 and only mark the milestone/release ready if the criteria are genuinely satisfied.
```

---

# Optional corrective prompt template

Use this between milestone prompts when a review finds problems.

```text
Perform a corrective pass on the current Printgate branch.

Do not start the next roadmap prompt.

Context:
[PASTE REVIEW FINDINGS HERE]

Instructions:
- Read README.md, ROADMAP.md, PROMPTS.md and inspect the actual diff/current implementation.
- Verify each finding rather than blindly applying the suggested fix.
- Fix confirmed issues with the smallest coherent change.
- Add regression tests for bugs that could recur.
- Do not broaden scope into future milestones.
- If a review finding is incorrect, explain why with concrete code/test evidence.
- Keep roadmap checkboxes honest.

Return the standard Printgate implementation report.
```

# Optional milestone review prompt

Use this after the final prompt in a milestone and before moving on.

```text
Audit the just-completed Printgate milestone against README.md and ROADMAP.md.

Do not implement the next milestone.

Tasks:
1. Verify every checked roadmap item for this milestone against the actual repository.
2. Run the relevant test suite and checks.
3. Look for security regressions, architectural leakage, dead code, untested parsing, and misleading documentation.
4. Pay special attention to any user-controlled value that can influence filesystem paths or process arguments.
5. Uncheck roadmap items that are not actually satisfied.
6. Fix small, clearly in-scope defects found during the audit.
7. For larger issues, describe them precisely instead of silently expanding scope.

Return:
- verified acceptance criteria;
- fixes made;
- tests/checks;
- manual verification still required;
- whether the next milestone is safe to begin.
```
