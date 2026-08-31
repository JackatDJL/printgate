# Printgate Roadmap

This roadmap is the source of truth for implementation scope.

The project should be built vertically and reviewably. A milestone is complete only when its acceptance criteria work on a real Linux host with CUPS, unless the milestone explicitly concerns scaffolding or test doubles.

Legend:

- [ ] not started
- [~] in progress
- [x] complete

---

## Milestone 0 — Repository foundation

**Goal:** establish a clean Laravel project and the boundaries that later work will rely on.

### M0.1 Bootstrap Laravel

- [x] Create the Laravel application.
- [x] Configure Bun/Vite.
- [x] Use SQLite for local development.
- [x] Add Blade + Livewire.
- [x] Add a minimal application shell.
- [x] Add basic feature-test infrastructure.
- [x] Confirm the app boots and tests pass.

### M0.2 Define printing domain boundary

- [x] Create `app/Printing` or an equivalent isolated module.
- [x] Define printer and print-job domain objects.
- [x] Define a `PrintBackend` abstraction.
- [x] Add a fake backend for automated tests.
- [x] Keep process execution out of controllers/UI.

### M0.3 Establish configuration and safety defaults

- [x] Add Printgate-specific configuration.
- [x] Default bind host to loopback.
- [x] Configure upload and retention limits.
- [x] Document temporary-file storage.
- [x] Add tests for configuration defaults.
- [ ] Add an architecture/security note to the repository if implementation decisions diverge from `README.md`.

**Milestone acceptance:** Laravel boots, tests pass, a fake print backend can be injected, and no CUPS-specific process logic leaks into the UI layer.

---

## Milestone 1 — Local PDF printing vertical slice

**Goal:** upload one PDF through the web UI and submit it safely to a CUPS printer.

### M1.1 Discover printers from CUPS

- [x] Implement a production CUPS adapter.
- [x] Discover configured printers.
- [x] Parse printer name and basic state.
- [x] Handle missing/unavailable CUPS gracefully.
- [x] Add parser/unit tests using fixtures.
- [x] Add a simple printer list to the dashboard.

### M1.2 Safe PDF upload

- [ ] Add a new-print-job flow.
- [ ] Accept PDF only.
- [ ] Validate size and file type.
- [ ] Generate randomized internal filenames.
- [ ] Store files outside the public web root.
- [ ] Persist minimal upload/job metadata.
- [ ] Add validation and path-safety tests.

### M1.3 Submit print jobs

- [ ] Select only a printer returned by the CUPS backend.
- [ ] Submit the stored PDF through the backend.
- [ ] Use argument-array process execution with no shell interpolation.
- [ ] Record the returned CUPS job ID.
- [ ] Handle process failure without losing useful error context.
- [ ] Add success/failure tests around the backend boundary.

### M1.4 Basic job page and cancellation

- [ ] List recent Printgate jobs.
- [ ] Show submitted/failed/cancelled state.
- [ ] Query CUPS for active job state where practical.
- [ ] Allow cancellation of a Printgate-owned job.
- [ ] Never expose arbitrary CUPS job cancellation.
- [ ] Add authorization/ownership hooks even if only one user exists initially.

**Milestone acceptance:** from the local web UI, a PDF can be uploaded, sent to a selected CUPS printer, seen in Printgate's job history, and cancelled when CUPS still allows it.

---

## Milestone 2 — Print options and preview

**Goal:** make Printgate useful for normal day-to-day printing without inventing unsupported printer controls.

### M2.1 Printer capabilities

- [ ] Query printer defaults and supported options.
- [ ] Normalize common capabilities:
  - paper/media size;
  - duplex/sides;
  - color mode;
  - orientation where available.
- [ ] Preserve unknown/raw capability data for diagnostics.
- [ ] Add parser fixtures for multiple printer styles.

### M2.2 Common print options

- [ ] Copies.
- [ ] Page range.
- [ ] Paper/media size.
- [ ] Duplex.
- [ ] Color/grayscale where supported.
- [ ] Orientation where supported.
- [ ] Validate every submitted option against an explicit allowlist/capability set.
- [ ] Never pass arbitrary user-provided `-o` values to CUPS.

### M2.3 PDF preview and review step

- [ ] Provide an in-browser PDF preview.
- [ ] Show document filename and detected page count when available.
- [ ] Add a final review screen before submission.
- [ ] Clearly show selected printer and options.
- [ ] Preserve accessibility and keyboard operation.

### M2.4 Option persistence and defaults

- [ ] Read printer defaults from CUPS.
- [ ] Allow safe Printgate-level defaults.
- [ ] Do not mutate system-wide CUPS defaults from the web application.
- [ ] Add tests for fallback behavior when capabilities are absent.

**Milestone acceptance:** Printgate dynamically shows only meaningful options for the selected printer and safely maps those choices to CUPS.

---

## Milestone 3 — Authentication and remote access hardening

**Goal:** make the application suitable for private remote access through Tailscale.

### M3.1 Local authentication

- [ ] Add a single-user local authentication flow.
- [ ] Use Laravel password hashing.
- [ ] Disable public registration.
- [ ] Add login rate limiting.
- [ ] Protect all printing/job routes.
- [ ] Add CSRF/auth tests.

### M3.2 User administration CLI

- [ ] Add `php artisan printgate:user`.
- [ ] Support initial user creation.
- [ ] Support password reset/change.
- [ ] Avoid echoing secrets.
- [ ] Make accidental multiple-admin creation explicit rather than silent.

### M3.3 Tailscale deployment mode

- [ ] Document Tailscale Serve setup.
- [ ] Verify production listener is loopback-only.
- [ ] Add a production warning if configured to bind broadly.
- [ ] Document the distinction between Tailscale Serve and public exposure.
- [ ] Do not require Tailscale for local development.

### M3.4 Security pass

- [ ] Audit file upload paths.
- [ ] Audit process execution.
- [ ] Audit print-option validation.
- [ ] Audit job cancellation boundaries.
- [ ] Add security regression tests.
- [ ] Ensure application logs do not contain document contents or passwords.

**Milestone acceptance:** a fresh deployment can be reached through Tailscale Serve, requires application authentication, and exposes no intentional general-purpose host control surface.

---

## Milestone 4 — Appliance mode

**Goal:** install Printgate on an old Linux laptop and have it reliably come back after reboot.

### M4.1 FrankenPHP production runtime

- [ ] Add/document production runtime configuration.
- [ ] Keep development workflow simple.
- [ ] Ensure production binds to loopback.
- [ ] Add health endpoint suitable for local service checks.

### M4.2 systemd service

- [ ] Add a hardened example systemd unit.
- [ ] Run unprivileged.
- [ ] Set sensible restart behavior.
- [ ] Define writable paths explicitly.
- [ ] Add service hardening options without blocking CUPS access.
- [ ] Document logs through `journalctl`.

### M4.3 Doctor command

- [ ] Add `php artisan printgate:doctor`.
- [ ] Check database/storage.
- [ ] Check CUPS.
- [ ] Check configured printers.
- [ ] Check required binaries/runtime.
- [ ] Check listener safety.
- [ ] Optionally report Tailscale status when installed.
- [ ] Use useful exit codes.

### M4.4 Cleanup and retention

- [ ] Add `php artisan printgate:cleanup`.
- [ ] Automatically delete expired source files.
- [ ] Preserve minimal non-sensitive job metadata according to configuration.
- [ ] Make cleanup idempotent.
- [ ] Add scheduled execution via Laravel scheduler or systemd timer.
- [ ] Test cleanup race conditions around active jobs.

**Milestone acceptance:** a documented installation on a Linux host survives reboot, starts automatically, reports health, and removes expired uploads.

---

## Milestone 5 — Broader document support

**Goal:** accept common source formats without weakening the core PDF-first model.

### M5.1 Image input

- [ ] Accept JPEG and PNG.
- [ ] Convert to canonical PDF.
- [ ] Preserve aspect ratio.
- [ ] Define predictable page sizing.
- [ ] Keep conversion sandboxed/narrow.

### M5.2 Office documents

- [ ] Investigate headless LibreOffice conversion.
- [ ] Accept only explicitly supported office MIME types/extensions.
- [ ] Convert to temporary PDF before preview/printing.
- [ ] Apply strict timeouts.
- [ ] Capture conversion failures safely.
- [ ] Document the larger attack surface.

### M5.3 Conversion pipeline

- [ ] Introduce a typed document-conversion abstraction.
- [ ] Store canonical PDF separately from original upload where needed.
- [ ] Ensure both source and derivative obey retention policy.
- [ ] Add fixture-based conversion tests.

**Milestone acceptance:** supported non-PDF files enter the same preview and printing pipeline only after successful conversion to PDF.

---

## Milestone 6 — Product polish

**Goal:** turn the working appliance into a pleasant small open-source product.

### M6.1 Better jobs experience

- [ ] Live/polled status updates.
- [ ] Better failure messages.
- [ ] Retry flow where safe.
- [ ] Job detail pages.
- [ ] Optional lightweight audit log.

### M6.2 Multi-user groundwork

- [ ] Decide whether multiple users are actually needed.
- [ ] If yes, add roles/ownership cleanly.
- [ ] Keep printer access policy simple.
- [ ] Preserve single-user deployment as a first-class mode.

### M6.3 Optional Tailscale identity

- [ ] Investigate trusted identity headers from Tailscale Serve.
- [ ] Only trust those headers when the backend is provably loopback/private behind the expected proxy.
- [ ] Make Tailscale identity additive or an explicit auth mode.
- [ ] Never silently weaken local authentication.

### M6.4 Packaging and release

- [ ] Choose license.
- [ ] Add install/update documentation.
- [ ] Add backup/restore guidance for SQLite/configuration.
- [ ] Add release checks.
- [ ] Produce first tagged release.

**Milestone acceptance:** Printgate is understandable, installable, supportable, and safe enough to publish for technically competent self-hosters.

---

# Deferred ideas

These are intentionally outside the initial milestones.

- Native mobile app.
- Public internet exposure without a private overlay network.
- E-mail-to-print.
- Printer driver installation from Printgate.
- Remote scanner support.
- Full IPP server implementation.
- Cloud accounts.
- Multi-host clustering.
- Rust agent/daemon unless profiling or deployment constraints justify it.
- Docker/container deployment as a requirement.

They can become future milestones if a concrete use case appears.
