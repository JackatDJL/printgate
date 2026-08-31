# Printgate

> A small, self-hosted web gateway for securely printing to printers visible to a Linux host.

Printgate is intended for a simple use case: a Linux machine at home can already print through CUPS, but you want to submit print jobs to those printers while away from home.

The project deliberately does **not** expose CUPS itself to the public internet. Instead, Printgate provides a narrow web application in front of CUPS, designed to run locally on the print host and be reached privately through Tailscale.

## Status

Early development / planning.

The implementation roadmap lives in [`ROADMAP.md`](./ROADMAP.md).  
Copy-pasteable implementation prompts for coding agents live in [`PROMPTS.md`](./PROMPTS.md).

## Product goals

Printgate should make this workflow boring:

1. Open the Printgate web UI from a trusted remote device.
2. Authenticate.
3. Upload a document.
4. Preview it.
5. Select any printer the host can see through CUPS.
6. Choose supported print options.
7. Submit the job.
8. See its status or cancel it.
9. Have uploaded files deleted automatically after a short retention period.

Printgate should feel like a tiny appliance, not a general-purpose remote shell.

## Non-goals

Printgate is not intended to:

- expose the CUPS administration interface remotely;
- configure printer drivers;
- edit the host's CUPS configuration;
- provide arbitrary file-system access;
- execute arbitrary shell commands;
- act as an internet-facing anonymous print service;
- replace CUPS or IPP;
- require Docker.

## Proposed stack

### Application

- PHP
- Laravel
- Blade + Livewire for the web UI
- SQLite for local application state
- Bun for frontend dependency management and Vite assets

Use the current stable Laravel release supported by the development environment when the repository is bootstrapped. Avoid pinning a framework version in planning documents unless required by implementation.

### Runtime

- FrankenPHP as the production PHP application server
- systemd for lifecycle management
- CUPS as the local print subsystem
- Tailscale Serve as the preferred remote-access layer

### Optional later components

Rust is allowed for a narrowly justified helper binary if a real need appears later. It should not be introduced merely for printer discovery, process execution, file validation, or basic job management that Laravel/PHP can already handle safely.

## High-level architecture

```text
Remote browser
      │
      │ HTTPS inside Tailnet
      ▼
Tailscale Serve
      │
      │ proxy to localhost only
      ▼
Printgate / Laravel
      │
      ├── authentication
      ├── upload validation
      ├── preview
      ├── printer capability UI
      ├── print-job records
      └── cleanup
      │
      ▼
CUPS command/API boundary
      │
      ├── lpstat
      ├── lpoptions
      ├── lp
      └── cancel
      │
      ▼
Printers already configured on host
```

## Security model

The intended deployment model uses multiple layers.

### 1. Private network boundary

The production web server should listen only on loopback, for example:

```text
127.0.0.1:8787
```

Remote access should normally be provided using Tailscale Serve.

Printgate should not require router port forwarding.

### 2. Application authentication

Printgate should still have application-level authentication even when reachable only over a Tailnet.

Initial implementation:

- one local administrator account;
- password stored using Laravel's normal password hashing;
- session-based login;
- CSRF protection;
- login rate limiting.

Future implementations may support Tailscale identity or passkeys, but those are not prerequisites for the first usable version.

### 3. Narrow print boundary

Never interpolate user-controlled input into shell command strings.

All CUPS command execution must:

- use Symfony Process or an equivalent argument-array API;
- validate printer names against printers discovered from CUPS;
- map UI choices to an explicit allowlist of supported command arguments;
- reject unknown values;
- run without shell expansion;
- run without `sudo`.

### 4. Unprivileged service

The application should run as a dedicated unprivileged service account where practical.

It must not require root privileges during normal operation.

Any system installation command that requires elevated permissions belongs in an explicit install step, not in the web application.

### 5. File handling

Uploaded files are untrusted.

The application must:

- use randomized internal filenames;
- keep original filenames as metadata only;
- store uploads outside the public web root;
- enforce file-size limits;
- validate MIME/content where practical;
- enforce allowed document types;
- prevent path traversal;
- delete files automatically;
- never expose arbitrary local paths to the print command.

For the initial vertical slice, PDF is the canonical document type.

Image conversion and office-document conversion come later.

## CUPS integration

Create a dedicated application boundary such as:

```text
app/Printing/
├── CupsClient.php
├── Printer.php
├── PrinterCapability.php
├── PrintRequest.php
├── PrintJob.php
└── Exceptions/
```

Controllers and Livewire components should not call `lp`, `lpstat`, `lpoptions`, or `cancel` directly.

The CUPS adapter should expose domain-level operations such as:

```php
interface PrintBackend
{
    public function printers(): array;

    public function capabilities(string $printer): array;

    public function submit(PrintRequest $request): PrintJob;

    public function jobs(): array;

    public function cancel(string $jobId): void;
}
```

The exact interface may evolve during implementation, but preserve the architectural boundary.

## Canonical document pipeline

Version 1 should be PDF-first.

```text
PDF upload
   │
   ├── validate
   ├── store temporarily
   ├── preview
   └── submit to CUPS
```

Later:

```text
JPEG/PNG ─┐
          ├── convert to canonical PDF ──► same pipeline
DOCX/ODT ─┘
```

Office conversion should not be part of the first printing milestone.

## Initial UX

### Dashboard

Show:

- available printers;
- readiness/state;
- active/recent Printgate jobs;
- a prominent “New print job” action.

### New print job

1. Choose/upload PDF.
2. Preview document.
3. Choose printer.
4. Configure supported options.
5. Review.
6. Print.

Do not pretend an option is supported if the selected printer does not advertise it.

### Jobs

Show at least:

- Printgate job ID;
- CUPS job ID when available;
- printer;
- original filename;
- submission time;
- state;
- copies;
- selected options;
- cancel action where possible.

Do not retain document content longer than configured retention.

## Configuration

Prefer environment variables for host-specific values.

Expected examples:

```dotenv
APP_ENV=production
APP_URL=http://127.0.0.1:8787

PRINTGATE_BIND=127.0.0.1
PRINTGATE_PORT=8787

PRINTGATE_MAX_UPLOAD_MB=50
PRINTGATE_RETENTION_MINUTES=60

PRINTGATE_AUTH_MODE=local
```

Do not store secrets in version control.

## CLI

Use Laravel Artisan as the initial CLI surface.

Desired commands:

```bash
php artisan printgate:doctor
php artisan printgate:printers
php artisan printgate:user
php artisan printgate:cleanup
```

A standalone `printgate` wrapper may be added later if it improves installation or daily operation.

### `printgate:doctor`

The doctor command should eventually check:

- application configuration;
- writable storage;
- SQLite/database connectivity;
- CUPS availability;
- printer discovery;
- required host commands;
- application server assumptions;
- Tailscale availability if configured;
- obvious unsafe production configuration.

It should return a non-zero exit code when critical checks fail.

## systemd deployment

Production deployment should use a checked-in example unit or installer-generated unit.

Conceptually:

```ini
[Unit]
Description=Printgate
After=network-online.target cups.service tailscaled.service
Wants=network-online.target

[Service]
Type=simple
User=printgate
WorkingDirectory=/opt/printgate
EnvironmentFile=/etc/printgate/printgate.env
ExecStart=/usr/local/bin/frankenphp ...
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

The exact command and hardening options must be verified during implementation.

The service should bind only to loopback.

## Repository principles

1. Keep the project understandable to someone learning Laravel.
2. Prefer framework conventions over custom infrastructure.
3. Do not prematurely add microservices.
4. Keep CUPS behind one testable boundary.
5. Make unsafe states difficult.
6. Add automated tests with each feature.
7. Keep roadmap checkboxes current as work lands.
8. Do not implement future milestones while working on an earlier prompt unless required to avoid rework.

## Development workflow

The intended agent workflow is:

1. Read `README.md`.
2. Read `ROADMAP.md`.
3. Find the next unchecked prompt in `PROMPTS.md`.
4. Implement only that scope.
5. Run relevant tests/formatters.
6. Update the matching roadmap checkbox.
7. Return a concise implementation report.
8. Human reviews the diff before the next prompt.

Example:

```text
Milestone 1 / Prompt 1
        ↓
review
        ↓
Milestone 1 / Prompt 2
        ↓
review
        ↓
...
```

## License

Choose an open-source license before public release. No license is selected by this planning document.
