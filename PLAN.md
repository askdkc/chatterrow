# Chatter Release-Blocking Defects Remediation Plan

> **For Hermes:** Use `anti-memory-loss-loop`, `test-driven-development`, and `subagent-driven-development` to implement this plan task-by-task. The parent agent owns `.hermes/task-state.md`, verifies every diff and test result, and does not accept a subagent's completion claim without re-running the checks.

**Goal:** Make the Laravel/Svelte groupware application safe and usable end-to-end by removing the P0 mutation blockers, restoring the Inertia/UI data contracts, completing chat/file/task/gantt behavior, hardening OnlyOffice and file access, and returning every CI gate to green.

**Architecture:** Keep Laravel controllers, policies, Eloquent models, Inertia, Svelte 5, Reverb, and the existing file-viewer/OnlyOffice integrations. Standardize HTTP mutation handling and API serialization instead of adding a second API layer. Add narrowly scoped services/resources where trust boundaries or lifecycle logic must be centralized, and lock every review finding with a regression test before changing production code.

**Tech Stack:** PHP 8.4, Laravel 13, Pest/PHPUnit through `php artisan test`, PHPStan, Laravel Pint, Svelte 5, TypeScript, Inertia 3, Laravel Echo/Reverb, Vite 8, Vitest with jsdom for pure frontend logic, SQLite test databases, local fake storage.

---

## 1. Scope and delivery rules

### In scope

- CSRF handling for every custom `fetch()` mutation.
- Correct create-policy signatures and nested-resource authorization.
- Stable shared/page-specific Inertia props.
- Chat message/attachment serialization, persisted reply counts, a readable thread panel, and deletion semantics that preserve replies written by other users.
- File upload claims, staged-upload expiry, delete authorization, safe inline rendering, channel-scoped uploads, and retryable physical-file cleanup.
- OnlyOffice viewer reauthorization, signed URL/JWT expiry, deployment-safe document keys, and Office structure preflight.
- Correct server/channel Gantt rendering and date-only calculations in JST and other time zones.
- Idempotent/reschedulable due-date reminders.
- Todo tenant isolation and server-owner account deletion rules.
- Realtime event-name compatibility and create/update/delete consistency for messages and Todos.
- Preview viewer/editor teardown across close, cancellation, timeout, and terminal-error paths.
- Missing server/channel CRUD controls, dead UI callbacks, visible operation errors, and keyboard/screen-reader access.
- `.env.example`, README, formatting, static analysis, type checks, build, audit, and regression coverage.

### Out of scope

- OnlyOffice editing, commenting, or document mutation; the integration remains read-only.
- A visual redesign unrelated to a confirmed defect.
- New roles beyond the existing server owner/member distinction.
- Native mobile applications or external integrations.
- Production deployment, production database mutation, production secrets, or destructive production migrations.

### Stop conditions

Stop implementation and report before proceeding if any of these occurs:

- A change requires production credentials or production data access.
- Existing data cannot be migrated without an irreversible transformation not described here.
- The OnlyOffice document server is unavailable for manual verification; automated fakes may still be completed, but external acceptance must remain explicitly pending.
- One hypothesis fails three times without a new reproducible fact.
- A proposed fix expands beyond the acceptance criteria without a concrete failing case.

### Commit policy

Each task below is one independently verified change set. Suggested commit commands are documentation only; execute commits only when the user authorizes commits for the implementation session. Never include `.hermes/task-state.md` in a product commit.

---

## 2. Acceptance criteria

The remediation is complete only when all criteria below are demonstrated:

- [ ] A verified user can create a server, channel, message, Todo, member, and file without a 419 response.
- [ ] Authorized channel/message/Todo creation no longer raises a Policy `TypeError`.
- [ ] Channel creation returns the JSON contract consumed by `ChannelDialog` and navigates without attempting to parse redirected HTML.
- [ ] Nonmembers cannot access nested resources; nonowners cannot manage members; nonuploaders/nonowners cannot delete files.
- [ ] Uploaded active content such as HTML/SVG is never served inline from the authenticated application origin.
- [ ] Message attachment responses contain usable stream/download/thumbnail URLs and never expose storage paths.
- [ ] A user can open a thread, read persisted replies, add a reply, reload, and retain the correct reply count.
- [ ] Deleting a thread parent produces a tombstone/soft deletion and never cascade-deletes replies authored by other users.
- [ ] Tasks, Gantt, and Files pages render with complete non-undefined props and a populated server rail.
- [ ] Server and channel Gantt bars align with date columns and show the same date in JST as stored in the database.
- [ ] A removed server member cannot reuse an unexpired OnlyOffice source URL.
- [ ] OnlyOffice JWTs expire, document keys are deployment-scoped, and invalid Office structures fail closed.
- [ ] Changing `ends_on` or `due_on` permits one reminder for the new date; retries/concurrent runs do not duplicate reminders.
- [ ] Canceled staged uploads expire, and server/channel/message/file deletion removes both database rows and source/preview blobs.
- [ ] A failed storage deletion remains represented in a retryable cleanup ledger until source and preview blobs are confirmed absent.
- [ ] A Todo assignee must belong to the same server.
- [ ] An account owning servers cannot be deleted until those servers are deleted or ownership is explicitly resolved.
- [ ] Backend `broadcastAs()` names exactly match the dot-prefixed Echo listeners, and realtime clients converge after message/Todo create and Todo/message delete operations.
- [ ] Closing or failing a preview destroys every viewer/editor instance, including instances created after an awaited dynamic import.
- [ ] Server/channel create, rename, and delete operations are reachable from the UI with role-correct controls.
- [ ] Mutation failures are visible through persistent/`aria-live` feedback, and core dialogs/actions are keyboard and screen-reader operable.
- [ ] `composer ci:check`, frontend unit tests, production build, PHP/Node audits, and the full PHP test suite pass from a clean worktree.

---

## 3. Coverage matrix

| ID | Confirmed defect | Primary task | Required proof |
|---|---|---|---|
| C-001 | Custom mutations send an empty CSRF token and return 419 | Task 2 | Blade/meta test, HTTP helper unit test, browser smoke |
| C-002 | Channel/message/Todo create policies receive the wrong model type | Task 2 | Authorized create tests return success, not 500 |
| C-003 | `auth.servers`/`authServers`, `members`, and `channels` props disagree | Task 3 | Inertia prop assertions for every page |
| C-004 | Chat attachments have no URL resource fields | Task 4 | JSON and Inertia resource assertions |
| C-005 | Replies can be written but not loaded/read; count is not persisted in responses | Task 4 | Thread GET/POST/reload tests |
| C-006 | Client controls attachment path/name/MIME/size | Task 5 | Tampering and cross-user claim tests |
| C-007 | HTML/SVG can execute inline; any member can delete another member's file | Task 5 | Response-header/body tests and 403 delete tests |
| C-008 | Removed/canceled chat uploads remain orphaned, and channel uploads disappear from the channel list | Task 6 | Staging expiry plus channel upload/list/reload tests |
| C-009 | Parent/file deletion leaves source or preview blobs and ignores storage deletion failure | Task 6 | `Storage::fake()` lifecycle and cleanup-retry tests |
| C-010 | OnlyOffice URLs survive member revocation; JWT/key/content-version/preflight are weak | Task 7 | Revocation, expiry, deployment/content key, and malformed-file tests |
| C-011 | Gantt date and CSS-grid calculations are invalid | Task 8 | Vitest date/grid tests plus Inertia route test |
| C-012 | Reminders are not reschedulable or atomically idempotent | Task 9 | Reschedule/retry/concurrency-oriented tests |
| C-013 | Todo can be assigned to an outsider; owner deletion orphans a server | Task 10 | Tenant-validation and profile-delete tests |
| C-014 | Broadcast names do not match Echo listeners; realtime deletion and self-exclusion are incomplete | Task 11 | `broadcastAs()` contract, event payload, and frontend reducer tests |
| C-015 | Server/channel update/delete UI and several callbacks are absent | Task 12 | Component/unit checks and manual workflow |
| C-016 | Prettier, Pint, PHPStan, and Svelte warnings leave CI red | Task 13 | All final gates exit 0 |
| C-017 | Deleting a thread parent cascade-deletes replies written by other users | Task 4 | Parent tombstone and reply-retention tests |
| C-018 | Channel create redirects to HTML while `ChannelDialog` requires JSON | Task 2 | 201 JSON response and dialog navigation test |
| C-019 | Preview instances survive close-during-load, timeout, or terminal error | Task 6 | Viewer/editor teardown component tests |
| C-020 | Chat/Todo/File failures are silent | Tasks 2 and 12 | Visible error-state component tests |
| C-021 | Core dialogs and hover-only actions are not keyboard/screen-reader operable | Task 12 | Accessibility checks and keyboard workflow |

---

## Task 1: Freeze baseline evidence and test conventions

**Objective:** Establish the persistent work ledger and preserve the current failures before any product-code change.

**Files:**

- Create locally, do not commit: `.hermes/task-state.md`
- Inspect: `AGENTS.md`
- Inspect: `composer.json`
- Inspect: `package.json`
- Inspect: `phpunit.xml`
- Inspect: `tests/TestCase.php`

**Steps:**

1. Record Objective, Scope, Out of Scope, Acceptance Criteria, Stop Conditions, and coverage IDs C-001 through C-021 in `.hermes/task-state.md`.
2. Record the clean starting state:

   ```bash
   git status --short --branch
   git log --oneline -10
   git diff
   git diff --cached
   ```

3. Re-run and record the baseline gates without modifying files:

   ```bash
   php artisan test
   npm run lint:check
   npm run types:check
   npm run format:check
   composer lint:check
   composer types:check
   npm run build
   composer audit --locked
   npm audit --audit-level=high
   ```

4. Preserve the known baseline in the ledger: 43 PHP tests pass, while Prettier, Pint, PHPStan, and Svelte warnings remain unresolved.
5. Confirm there are no pre-existing user changes. If there are, stop and separate them before implementation.

**Verification:** The state file agrees with Git and includes exact command output and the current HEAD.

**Suggested commit:** None; this task changes no product file.

---

## Task 2: Restore all mutation paths and correct create authorization

**Objective:** Eliminate 419 responses and create-policy `TypeError`s before any feature work continues.

**Files:**

- Create: `tests/Feature/ApplicationShellTest.php`
- Create: `tests/Feature/GroupwareMutationAuthorizationTest.php`
- Create: `resources/js/lib/http.ts`
- Create: `resources/js/lib/http.test.ts`
- Create: `resources/js/components/discord/ChannelDialog.test.ts`
- Modify: `resources/views/app.blade.php`
- Modify: `app/Http/Controllers/ChannelController.php`
- Modify: `resources/js/components/discord/ServerDialog.svelte`
- Modify: `resources/js/components/discord/ChannelDialog.svelte`
- Modify: `resources/js/components/discord/MemberDialog.svelte`
- Modify: `resources/js/components/discord/TodoPanel.svelte`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `resources/js/pages/servers/Files.svelte`
- Modify: `app/Policies/ChannelPolicy.php`
- Modify: `app/Policies/MessagePolicy.php`
- Modify: `app/Policies/TodoPolicy.php`
- Modify: `package.json`
- Modify: `package-lock.json`

**Step 1: Write failing PHP tests**

Cover at least these cases:

- Authenticated app HTML contains one nonempty `<meta name="csrf-token">`.
- A server member can create a channel, message, and Todo.
- Channel creation requested as JSON returns `201` with `{ "channel": { "id": ... } }`, not a followed redirect/HTML document.
- A nonmember receives 403/404 for all three creates.
- A channel from another server cannot be used under the requested server route.
- Validation responses are 422 JSON when requested as JSON, not silent redirects or 500s.

Use named routes and assert database state, not only response status.

**Step 2: Verify RED**

```bash
php artisan test tests/Feature/ApplicationShellTest.php tests/Feature/GroupwareMutationAuthorizationTest.php
```

Expected before implementation: missing CSRF meta assertion and three create requests fail.

**Step 3: Add one shared HTTP helper**

`resources/js/lib/http.ts` must:

- Read the CSRF meta once per request and throw a clear error if absent.
- Set `X-CSRF-TOKEN`, `X-Requested-With: XMLHttpRequest`, and `Accept: application/json`.
- Preserve caller headers.
- Avoid setting `Content-Type` for `FormData`.
- Use `credentials: 'same-origin'`.
- Convert non-2xx JSON/validation responses into a typed error usable by dialogs, Chat, Todo, and Files operations.

Representative API:

```ts
export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly payload: unknown,
    ) {
        super(`HTTP ${status}`);
    }
}

export async function apiFetch(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (!token) throw new Error('CSRF token is missing from the application shell.');

    const headers = new Headers(init.headers);
    headers.set('X-CSRF-TOKEN', token);
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('Accept', 'application/json');

    const response = await fetch(input, { ...init, headers, credentials: 'same-origin' });
    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        throw new HttpError(response.status, payload);
    }

    return response;
}
```

Add Vitest/jsdom only once here so this helper and later pure utilities can be tested:

```json
{
  "scripts": { "test:unit": "vitest run" },
  "devDependencies": {
    "vitest": "<locked version>",
    "jsdom": "<locked version>",
    "@testing-library/svelte": "<locked version>"
  }
}
```

Use the package manager to select and lock compatible versions; do not hand-edit lockfile versions.

**Step 4: Fix the shell and all callers**

Add the standard Blade meta tag and replace every duplicated `csrfToken()`/raw mutation fetch with `apiFetch()`. Dialog, Chat, Todo, upload, and delete operations must display validation/server errors through visible `aria-live` state, retain user input on failure, and never fail only in the console.

**Step 5: Align the Channel create response with its caller**

`ChannelController::store()` must return the JSON resource expected by `ChannelDialog` with status 201. The dialog test must prove that it reads `channel.id` and navigates only after the successful JSON response. Do not rely on native `fetch()` following a redirect to an Inertia HTML page.

**Step 6: Correct policy signatures**

Use the actual parent passed by each controller:

```php
// ChannelPolicy
public function create(User $user, Server $server): bool

// MessagePolicy and TodoPolicy
public function create(User $user, Channel $channel): bool
```

The policy body must test membership through that parent. Do not construct fake unsaved child models merely to satisfy the old signatures.

**Step 7: Verify GREEN**

```bash
php artisan test tests/Feature/ApplicationShellTest.php tests/Feature/GroupwareMutationAuthorizationTest.php
npm run test:unit -- resources/js/lib/http.test.ts
npm run test:unit -- resources/js/components/discord/ChannelDialog.test.ts
npm run types:check
npm run lint:check
```

Expected: all targeted tests pass; zero type/lint errors.

**Suggested commit:** `fix: restore authenticated groupware mutations`

---

## Task 3: Normalize the Inertia page-prop contract

**Objective:** Make ServerRail, ChannelList, Tasks, Gantt, and Files receive one documented, type-safe set of props.

**Files:**

- Create: `tests/Feature/GroupwarePagePropsTest.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `app/Http/Controllers/ServerController.php`
- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `app/Http/Controllers/FileIndexController.php`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/pages/servers/Index.svelte`
- Modify: `resources/js/pages/servers/Show.svelte`
- Modify: `resources/js/pages/servers/Tasks.svelte`
- Modify: `resources/js/pages/servers/Gantt.svelte`
- Modify: `resources/js/pages/servers/Files.svelte`
- Modify: `resources/js/pages/chat/Chat.svelte`

**Step 1: Write failing Inertia assertions**

For `/servers`, server show, channel chat, tasks, gantt, and files, assert:

- Shared `auth.servers` is always present for an authenticated user.
- `server`, `channels`, and `members` exist on pages rendering `ChannelList`.
- The active channel exists only where needed.
- Nonmembers receive 403 before props are resolved.

**Step 2: Choose one source of truth**

Keep the existing shared `auth.servers`. Remove the top-level `authServers` contract from page components rather than duplicating the query. Type the shared props in `resources/js/types/index.ts` and derive the rail data from Inertia's shared `auth.servers`.

Controllers remain responsible only for page-specific `server`, `channels`, `members`, `todos`, `tasks`, and `files` props.

**Step 3: Supply complete page-specific data**

- Tasks: server, channels, members, todos.
- Gantt: server, channels, members, tasks, optional active channel/scope.
- Files: server, channels, members, files, optional active channel/scope.
- Chat: server, channel, members, initial messages, todos.

Use explicit serializers/selects to keep props stable and avoid leaking unrelated columns.

**Step 4: Verify**

```bash
php artisan test tests/Feature/GroupwarePagePropsTest.php
npm run types:check
npm run build
```

Expected: all pages satisfy the same shared contract; no `undefined.length` runtime path remains.

**Suggested commit:** `fix: normalize groupware page props`

---

## Task 4: Introduce stable message/file resources and complete threads

**Objective:** Return one canonical chat payload with usable attachment URLs, a fully readable persisted thread, and deletion semantics that preserve other users' replies.

**Files:**

- Create: `app/Http/Resources/StoredFileResource.php`
- Create: `app/Http/Resources/MessageResource.php`
- Create: `tests/Feature/MessageThreadTest.php`
- Create: `tests/Feature/MessageAttachmentResourceTest.php`
- Create: `database/migrations/2026_08_01_000003_add_soft_deletes_to_messages.php`
- Create: `app/Events/MessageDeleted.php`
- Create: `resources/js/components/discord/ThreadPanel.svelte`
- Modify: `app/Http/Controllers/ChatPageController.php`
- Modify: `app/Http/Controllers/MessageController.php`
- Modify: `app/Events/MessageCreated.php`
- Modify: `app/Events/ReminderCreated.php`
- Modify: `app/Models/Message.php`
- Modify: `resources/js/components/discord/MessageItem.svelte`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `resources/js/types/index.ts`

**Step 1: Write failing resource/thread tests**

Assert that every root and reply payload contains:

- Message ID, server/channel/user/parent IDs, body, reminder flag, timestamps, and a stable deleted/tombstone flag.
- User resource, except where a tombstone intentionally redacts it.
- `reply_count` for root messages.
- Attachment IDs, safe names, detected MIME/size, `stream_url`, `download_url`, `thumbnail_url`, preview state, and permission flags.
- No `path`, `preview_path`, or disk internals.

Assert that `GET .../messages?parent_id=<root>`:

- Returns only replies for that root and channel.
- Rejects a parent from another channel/server.
- Orders replies predictably.
- Is authorized for members only.

Add the confirmed authorization-regression case:

1. Alice creates a parent.
2. Bob replies.
3. Alice deletes her parent.
4. Bob's reply still exists and remains readable beneath a redacted parent tombstone.

**Step 2: Implement Laravel resources**

Use the resources in all five payload sources:

1. Initial Inertia messages.
2. Message create response.
3. Thread index response.
4. `MessageCreated`/`ReminderCreated` broadcast payloads.
5. `MessageDeleted` tombstone payloads.

Load `attachments`, `user`, and `replies_count` deliberately to prevent N+1 queries and payload drift.

**Step 3: Preserve replies when a parent is deleted**

Add message soft deletion and serialize a stable `is_deleted` tombstone. Thread queries may include a soft-deleted parent but must not expose its previous body or attachments. Ordinary user deletion must never reach the database `parent_id ... cascadeOnDelete()` path. `forceDelete()` is reserved for deliberate server teardown after descendants and files are handled explicitly.

The deletion policy authorizes deletion of the selected message only; parent ownership is not authorization to destroy child messages. Broadcast `MessageDeleted` after commit so peers render the same tombstone.

**Step 4: Implement the thread panel**

`ThreadPanel.svelte` must show the parent/tombstone, loading/error/empty states, replies, and a reply composer. Opening a thread fetches persisted replies; creating a reply appends the returned canonical resource. Reloading must preserve the same count and replies.

Do not represent a thread merely by changing the main composer placeholder.

**Step 5: Add deduplication**

Use message ID as the dedupe key for both HTTP responses and broadcasts. When calling an endpoint followed by `broadcast(...)->toOthers()`, pass Echo's socket ID as `X-Socket-ID` so the initiating browser is excluded.

**Step 6: Verify**

```bash
php artisan test tests/Feature/MessageThreadTest.php tests/Feature/MessageAttachmentResourceTest.php
npm run types:check
npm run test:unit
npm run build
```

Expected: attachment links are valid, replies survive reload and parent deletion, and counts do not double-increment.

**Suggested commit:** `feat: complete safe chat attachments and threads`

---

## Task 5: Harden file claims, inline responses, and delete authorization

**Objective:** Treat every upload as untrusted and enforce ownership at attach/delete boundaries.

**Files:**

- Create: `app/Policies/StoredFilePolicy.php`
- Create: `tests/Feature/StoredFileSecurityTest.php`
- Create: `tests/Feature/MessageAttachmentClaimTest.php`
- Create: `database/migrations/2026_08_01_000004_add_content_sha256_to_stored_files.php`
- Modify: `app/Http/Controllers/StoredFileController.php`
- Modify: `app/Http/Controllers/MessageController.php`
- Modify: `app/Http/Resources/StoredFileResource.php`
- Modify: `app/Models/StoredFile.php`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `resources/js/pages/servers/Files.svelte`
- Modify: `resources/js/types/index.ts`

**Step 1: Write failing security tests**

Cover:

- HTML, XHTML, SVG, XML, and unknown binary content is not returned as executable inline content.
- PDF and explicitly allowed image/video/audio MIME types retain safe inline behavior.
- Every inline response has `nosniff`, private cache policy, and a restrictive content policy where applicable.
- A nonuploader/nonowner member cannot delete a file.
- The uploader and server owner can delete it.
- A user cannot attach another user's pending file.
- A user cannot attach a file from another server.
- A user cannot attach an already attached file again.
- Changing client-provided name/MIME/size/path has no effect because those fields are no longer accepted.
- The immutable server-side SHA-256 matches the uploaded bytes and cannot be supplied or changed by the client.

**Step 2: Replace attachment metadata with IDs**

The upload response may expose a file ID and presentation fields, but not the storage path. Message creation accepts only:

```json
{ "attachment_ids": [12, 13] }
```

Inside a database transaction, lock and validate each ID against:

- `server_id` equals the route server.
- `uploaded_by` equals the current user.
- `attachable_type` and `attachable_id` are null.

Associate server-observed metadata without accepting any client replacement values. During upload, calculate and store a non-null `content_sha256` from the actual temporary-file bytes before preview jobs can run. This hash is immutable and becomes Task 7's content-version input; path, extension, timestamp, and size are not content identity.

**Step 3: Add file policy**

- `view`: current server member.
- `delete`: uploader or server owner.

Authorize the `StoredFile` itself for stream/download/thumbnail/delete after verifying nested server ownership. Include `can_delete` in the file resource and hide destructive UI when false.

**Step 4: Add an explicit safe-inline policy**

Maintain a strict allowlist for formats required by the product, such as PDF and selected raster image/video/audio MIME types. Active document formats and unknown content must use attachment download or return 415 from the stream endpoint. Never trust the filename extension to override detected MIME.

Office previews continue through the sanitized local preview or hardened OnlyOffice path, not raw inline HTML.

**Step 5: Verify**

```bash
php artisan test tests/Feature/StoredFileSecurityTest.php tests/Feature/MessageAttachmentClaimTest.php
php artisan test tests/Feature/MessageAttachmentResourceTest.php
npm run types:check
```

Expected: active-content tests prove it cannot execute inline; cross-user claims/deletes return 403/422.

**Suggested commit:** `security: enforce file ownership and safe streaming`

---

## Task 6: Implement staged/channel uploads, retryable cleanup, and preview teardown

**Objective:** Keep channel files visible, expire abandoned chat uploads, retain cleanup state across storage failures, and destroy preview instances on every lifecycle exit.

**Files:**

- Create: `app/Support/StoredFileCleanupService.php`
- Create: `app/Jobs/DeleteStoredFileBlobs.php`
- Create: `app/Console/Commands/PurgeStagedFiles.php`
- Create: `database/migrations/2026_08_01_000005_add_staging_fields_to_stored_files.php`
- Create: `database/migrations/2026_08_01_000006_create_stored_file_deletions_table.php`
- Create: `tests/Feature/ChannelFileTest.php`
- Create: `tests/Feature/StagedUploadTest.php`
- Create: `tests/Feature/StoredFileLifecycleTest.php`
- Create: `resources/js/components/files/StoredFilePreviewDialog.test.ts`
- Create: `resources/js/components/files/OnlyOfficePreviewDialog.test.ts`
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Modify: `app/Http/Controllers/StoredFileController.php`
- Modify: `app/Http/Controllers/FileIndexController.php`
- Modify: `app/Http/Controllers/MessageController.php`
- Modify: `app/Http/Controllers/ChannelController.php`
- Modify: `app/Http/Controllers/ServerController.php`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `resources/js/pages/servers/Files.svelte`
- Modify: `resources/js/components/files/StoredFilePreviewDialog.svelte`
- Modify: `resources/js/components/files/OnlyOfficePreviewDialog.svelte`

**Step 1: Write failing staging/lifecycle tests**

With `Storage::fake('local')`, assert source and preview handling for:

- Direct file deletion.
- Soft-deleted message attachment cleanup without deleting replies.
- Channel deletion, including message and direct channel uploads.
- Server deletion.
- One server/channel never touching another tenant's files.
- `Storage::delete()` failure leaving a pending cleanup record that a later successful retry completes.
- A removed/canceled staged attachment being deleted by its uploader.
- An abandoned staged attachment expiring, while a permanent server/channel file never expires.

Component tests must also prove:

- Closing during a dynamic import cannot mount a viewer after cleanup.
- Stale async generations cannot replace the current viewer.
- OnlyOffice `onError` and ready-timeout paths destroy an already-created editor exactly once.
- Repeated close/error calls are idempotent.

**Step 2: Define staged and channel upload semantics**

Add a nullable `staged_until` (or equivalent explicit state) to distinguish temporary chat uploads from permanent server/channel files:

- Chat upload: staged with a bounded expiry.
- Successful message attachment: clear staging atomically when claiming the file ID.
- Pending-file removal: call the authorized file DELETE endpoint immediately.
- Navigation/unload: best-effort cleanup only; correctness comes from scheduled expiry.
- Direct server upload: permanent server file.
- Direct channel upload: immediately associated polymorphically with the `Channel`.

Add a nested channel upload route. Channel file listing must include direct channel files and files attached to messages in that channel. Schedule `PurgeStagedFiles` with overlap protection.

**Step 3: Persist cleanup intent before deleting rows**

`StoredFileCleanupService` must create a cleanup-ledger row containing disk, source path, preview path, tenant/file identifiers, state, and attempts before a parent cascade can erase the `stored_files` row. Parent deletion enumerates and records all affected files inside the same database transaction.

After commit, dispatch `DeleteStoredFileBlobs`. The job must be idempotent, retryable, and mark cleanup complete only after both source and preview are confirmed absent. A failed `Storage::delete()` must never be reported as fully deleted or lose the paths needed for retry. A scheduled retry path handles pending ledger rows if queue dispatch fails.

Do not rely on Eloquent child events for database cascades, and do not scatter independent `Storage::delete()` calls across controllers.

**Step 4: Make preview teardown generation-safe**

Both preview dialogs use a monotonically increasing generation/abort token checked after every `await`, including dynamic imports and external script loading. All terminal paths—close, unmount, unsupported file, load failure, OnlyOffice `onError`, and timeout—must clear timers/listeners and destroy any viewer/editor before changing UI state.

**Step 5: Verify**

```bash
php artisan test tests/Feature/ChannelFileTest.php tests/Feature/StagedUploadTest.php tests/Feature/StoredFileLifecycleTest.php
npm run test:unit -- resources/js/components/files/StoredFilePreviewDialog.test.ts resources/js/components/files/OnlyOfficePreviewDialog.test.ts
npm run types:check
```

Expected: channel files survive reload, staged files expire correctly, failed blob deletion remains retryable, all successful deletions leave DB/storage clean, and no preview instance survives close/error/timeout.

**Suggested commit:** `fix: make file staging cleanup and previews deterministic`

---

## Task 7: Harden the OnlyOffice read-only trust boundary

**Objective:** Ensure a document server receives only short-lived, viewer-bound, structurally valid read-only documents.

**Files:**

- Create: `app/Support/OfficeDocumentInspector.php`
- Create: `tests/Feature/OnlyOfficeSecurityTest.php`
- Create: `tests/Unit/OfficeDocumentInspectorTest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/OnlyOfficePreviewController.php`
- Modify: `app/Http/Controllers/OnlyOfficeFileController.php`
- Modify: `app/Support/OnlyOfficeConfigService.php`
- Modify: `app/Support/OnlyOfficeTokenService.php`
- Modify: `app/Support/OnlyOfficeDocumentVersion.php`
- Modify: `app/Support/OnlyOfficeDocumentTypeResolver.php`
- Modify: `config/onlyoffice.php`
- Modify: `.env.example`

**Step 1: Write failing security tests**

Cover:

- Config request requires current file-view authorization.
- Signed source URL contains a signed viewer ID and version.
- Removing that viewer from the server makes the same still-unexpired URL fail.
- Tampering with viewer/file/version fails.
- Deleted viewers fail closed.
- JWT includes bounded `iat`, `nbf`, and `exp` claims.
- Document keys differ across deployment IDs and differ for same-path/same-size files with different `content_sha256` values.
- A renamed executable or malformed ZIP with `.docx` does not produce a config.
- Valid representative DOCX/XLSX/PPTX/ODF fixtures pass.
- Unsigned existing/nonexisting IDs do not provide a useful existence oracle.

**Step 2: Bind the source URL to the viewer**

Change `OnlyOfficeConfigService::make()` to accept the authenticated `User` explicitly; remove global `auth()` access from the service. Include `viewer`, `version`, and file identifier in the temporary signed URL.

Avoid implicit model binding on the public internal route. Let signed middleware validate the raw parameter first, then manually resolve the record and re-check:

- Viewer exists.
- Viewer is still a server member.
- File still belongs to that server and version.

Use one generic denied/not-found response shape for invalid public requests.

**Step 3: Add bounded tokens and deployment identity**

Use the configured download TTL for top-level JWT time claims. Add `ONLYOFFICE_DEPLOYMENT_ID` and include it with the immutable `content_sha256` in the deterministic document key namespace so separate deployments and different byte content cannot share cache identity. Do not use path/size alone as the version.

Keep all editor permissions read-only and preserve disabled macros/plugins.

**Step 4: Validate the actual Office structure**

`OfficeDocumentInspector` must inspect server-detected bytes, not only extension:

- OOXML: valid ZIP plus expected content markers.
- ODF: valid ZIP and expected `mimetype`/manifest.
- Legacy OLE formats: correct compound-file signature if they remain supported.
- Fail closed when inspection cannot be completed.

If ZIP support is required, add and document the runtime extension explicitly rather than silently skipping inspection.

**Step 5: Verify**

```bash
php artisan test tests/Feature/OnlyOfficeSecurityTest.php tests/Unit/OfficeDocumentInspectorTest.php
composer types:check
```

Manual external verification may be marked pending only if the document server is unavailable; all authorization and token tests must still pass locally.

**Suggested commit:** `security: bind and validate OnlyOffice previews`

---

## Task 8: Rebuild Gantt date math and layout

**Objective:** Render correct server/channel Gantt views for short and long ranges without timezone drift or CSS-grid overlap.

**Files:**

- Create: `resources/js/lib/gantt.ts`
- Create: `resources/js/lib/gantt.test.ts`
- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `resources/js/pages/servers/Gantt.svelte`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `routes/web.php` only if route naming/shape must change
- Modify: `tests/Feature/GroupwarePagePropsTest.php`

**Step 1: Write failing pure-function tests**

Test with the process timezone set to JST and UTC:

- `2026-08-01` maps to the same date-only ordinal in both zones.
- `today` from a `Date` is valid, not `NaN`.
- Leap days and month/year boundaries.
- Inclusive one-day and multi-day spans.
- First date begins after the 260px label column.
- 7-, 30-, and 180-day ranges generate one actual day column per day.

Use UTC date-only math, for example parsing `YYYY-MM-DD` into `Date.UTC(year, month - 1, day)`, rather than interpolating a `Date` into a new date string.

**Step 2: Refactor layout**

Use separate explicit grid rows for the header and each task. Every row must have:

- Column 1: sticky task label.
- Columns 2 through N+1: one cell per day.
- Bar start/end offset by the label column.

Tick density controls labels only; it must never reduce the number of grid cells.

**Step 3: Return a page for channel Gantt**

`TaskController::channelGantt()` must return an Inertia Gantt page with channel-scoped tasks and complete props, not raw JSON. Reuse the server Gantt component with an explicit scope/active-channel prop.

**Step 4: Verify**

```bash
npm run test:unit -- resources/js/lib/gantt.test.ts
php artisan test tests/Feature/GroupwarePagePropsTest.php
npm run types:check
npm run build
```

Manual check: first/middle/last bars align under their stored dates at browser timezone `Asia/Tokyo`.

**Suggested commit:** `fix: render timezone-safe server and channel gantt views`

---

## Task 9: Make reminders reschedulable and atomically idempotent

**Objective:** Emit exactly one reminder per current due date even after rescheduling, retries, broadcast failure, or overlapping scheduler runs.

**Files:**

- Modify: `tests/Feature/DueDateReminderTest.php`
- Modify: `app/Http/Controllers/ChannelController.php`
- Modify: `app/Http/Controllers/TodoController.php`
- Modify: `app/Console/Commands/SendDueDateReminders.php`
- Modify: `routes/console.php`
- Create: `database/migrations/2026_08_01_000007_add_due_reminder_indexes.php`

**Step 1: Add failing cases**

- Changing a channel `ends_on` resets its reminder state only when the date actually changes.
- Changing a Todo `due_on` does the same.
- Clearing a due date clears stale reminder state appropriately.
- Running the command twice creates one message.
- Simulated failure after database work does not make the next run create a duplicate.
- Completed Todos never produce reminders.
- Two claim attempts result in one winner.

**Step 2: Reset markers on date changes**

Use model/controller dirty comparison; do not accept `reminded_at` from the client. Preserve the marker when unrelated fields change.

**Step 3: Claim and create atomically**

For each candidate, inside a transaction:

1. Atomically update `reminded_at` only where it is still null/current.
2. If no row was claimed, skip it.
3. Create the reminder message.
4. Schedule broadcast after commit.

A broadcast failure after commit may delay realtime delivery but must not create a second persisted reminder.

**Step 4: Prevent scheduler overlap and add indexes**

Add `withoutOverlapping()` and `onOneServer()` where the cache backend supports it. Add compound indexes matching the due-date/completion/reminded filters.

**Step 5: Verify**

```bash
php artisan test tests/Feature/DueDateReminderTest.php
php artisan reminders:send-due --days-ahead=0
```

The direct command is run only against an isolated test/local database.

**Suggested commit:** `fix: make due-date reminders idempotent`

---

## Task 10: Enforce Todo tenant boundaries and server ownership invariants

**Objective:** Prevent cross-server identity references and prevent account deletion from orphaning an active server.

**Files:**

- Create: `tests/Feature/TodoAssigneeAuthorizationTest.php`
- Modify: `tests/Feature/Settings/ProfileUpdateTest.php`
- Modify: `app/Http/Controllers/TodoController.php`
- Modify: `app/Http/Controllers/Settings/ProfileController.php`
- Modify: `app/Http/Requests/Settings/ProfileDeleteRequest.php`
- Modify: `app/Models/User.php`
- Modify: `resources/js/components/DeleteUser.svelte`

**Step 1: Add failing tenant tests**

- Assignee in the same server is accepted.
- Registered outsider is rejected on create and update.
- Assignee from another server is rejected.
- Null assignee remains valid.

Use a shared validation rule constrained to `server_user.server_id` and `server_user.user_id`; do not duplicate unconstrained `exists:users,id` rules.

**Step 2: Add failing owner-deletion tests**

- A user owning any server cannot delete the account.
- Error text explains that owned servers must first be deleted/resolved.
- A nonowner with no owned servers can delete the account as before.
- Failed deletion leaves the user and all servers unchanged.

YAGNI decision: block account deletion rather than silently transferring ownership. Ownership-transfer UI is not introduced unless separately requested.

**Step 3: Verify**

```bash
php artisan test tests/Feature/TodoAssigneeAuthorizationTest.php tests/Feature/Settings/ProfileUpdateTest.php
```

Expected: no `created_by=NULL` server can be produced through normal application flows.

**Suggested commit:** `fix: enforce server-scoped assignees and ownership`

---

## Task 11: Align broadcast names and complete realtime convergence

**Objective:** Make backend event names match Echo subscriptions and ensure every connected client reaches the same message/Todo state without duplicates or reload-only deletions.

**Files:**

- Create: `app/Events/TodoDeleted.php`
- Create: `tests/Feature/BroadcastContractTest.php`
- Create: `resources/js/lib/realtime.ts`
- Create: `resources/js/lib/realtime.test.ts`
- Modify: `app/Events/MessageCreated.php`
- Modify: `app/Events/MessageDeleted.php`
- Modify: `app/Events/ReminderCreated.php`
- Modify: `app/Events/TodoUpdated.php`
- Modify: `app/Http/Controllers/TodoController.php`
- Modify: `app/Http/Controllers/MessageController.php`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `resources/js/components/discord/TodoPanel.svelte`
- Modify: `resources/js/lib/echo.ts`
- Modify: `.env.example`

**Step 1: Write event-name, payload, and reducer tests**

Backend contract tests must assert:

- `MessageCreated::broadcastAs()` returns `MessageCreated`.
- `MessageDeleted::broadcastAs()` returns `MessageDeleted`.
- `ReminderCreated::broadcastAs()` returns `ReminderCreated`.
- `TodoUpdated::broadcastAs()` returns `TodoUpdated`.
- `TodoDeleted::broadcastAs()` returns `TodoDeleted`.
- Private channel names and canonical minimal resource payloads match the frontend contract.

These exact names intentionally match the existing dot-prefixed Echo listeners. Do not retain dot-prefixed literal listeners while emitting fully qualified PHP class names.

Pure frontend reducers must prove:

- Repeating the same message/Todo event does not duplicate it.
- Reply events increment once.
- A message-delete event renders/removes the matching message according to Task 4's tombstone contract without removing replies.
- Todo-delete removes the matching item and ignores unknown IDs.
- Events for another channel are ignored.

**Step 2: Implement explicit broadcast names**

Add matching `broadcastAs()` methods to every event listed above and use the same Laravel Resources as HTTP responses. Dispatch persisted events after commit so clients cannot observe rolled-back state.

**Step 3: Standardize socket-aware HTTP**

Have `apiFetch()` or a realtime wrapper add `X-Socket-ID` when Echo is available. Continue applying the HTTP response locally and rely on `toOthers()` for peers.

**Step 4: Broadcast deletes and clean subscriptions**

Todo deletion broadcasts its ID/channel after successful commit. Message deletion broadcasts the Task 4 tombstone. Frontend listeners remove/update the correct item and leave the base Echo channel during teardown; repeated mount/unmount must not accumulate callbacks.

**Step 5: Document clean Reverb configuration**

Add nonsecret placeholders for broadcast/Reverb/Vite variables to `.env.example`. Document that `php artisan dev` starts registered development services, while production requires explicit web, queue, scheduler, and Reverb process supervision.

**Step 6: Verify**

```bash
php artisan test tests/Feature/BroadcastContractTest.php
php artisan test --filter='Todo|Message|Broadcast'
npm run test:unit -- resources/js/lib/realtime.test.ts
npm run types:check
```

Manual check with two sessions: create/toggle/delete without reload; both clients converge, the initiating client receives no duplicate, and browser network frames contain the explicit short event names.

**Suggested commit:** `fix: align broadcast contracts and realtime state`

---

## Task 12: Finish server/channel CRUD and remove dead controls

**Objective:** Make all advertised CRUD and management actions reachable, role-correct, visibly fallible, and keyboard/screen-reader operable in the Svelte UI.

**Files:**

- Create: `resources/js/components/discord/GroupwareDialogs.test.ts`
- Create: `resources/js/pages/chat/OperationErrors.test.ts`
- Modify: `resources/js/components/discord/ServerDialog.svelte`
- Modify: `resources/js/components/discord/ChannelDialog.svelte`
- Modify: `resources/js/components/discord/ChannelList.svelte`
- Modify: `resources/js/components/discord/MemberDialog.svelte`
- Modify: `resources/js/pages/servers/Show.svelte`
- Modify: `resources/js/pages/servers/Tasks.svelte`
- Modify: `resources/js/pages/servers/Gantt.svelte`
- Modify: `resources/js/pages/servers/Files.svelte`
- Modify: `resources/js/pages/chat/Chat.svelte`
- Modify: `app/Http/Controllers/ServerController.php`
- Modify: `app/Http/Controllers/ChannelController.php`
- Modify: `resources/js/types/index.ts`
- Extend: `tests/Feature/GroupwareMutationAuthorizationTest.php`

**Step 1: Add failing backend CRUD coverage**

Test create/rename/delete for servers and channels, nested mismatch, nonmember access, member-vs-owner permissions, and post-delete redirect behavior.

**Step 2: Add explicit capability fields**

Expose `can_manage`, `can_delete`, and equivalent channel capabilities from trusted server-side authorization. The UI may hide controls, but backend policies remain authoritative.

**Step 3: Reuse dialogs in create/edit modes**

- Server: create, rename, delete with explicit destructive confirmation.
- Channel: create, edit dates/name, delete with explicit confirmation.
- Members: add/remove controls visible only to the owner.

Use `apiFetch()` and show validation/conflict errors. Do not close dialogs, clear input, or navigate on failed requests. Reuse the installed accessible dialog primitive rather than a click-only custom overlay.

**Step 4: Wire dead callbacks**

Replace empty `onAddChannel()`/`onManageMembers()` callbacks on Tasks, Gantt, and Files with the same functional dialogs or remove controls that are not valid for the user's role.

**Step 5: Make errors and controls accessible**

- Expose Chat send/upload, Todo create/update/delete, Files upload/delete, and CRUD errors as persistent visible text in an `aria-live` region.
- Keep the failed user's text/selection and restore focus to the relevant field or action.
- Give icon-only and destructive controls stable accessible names and real `<button>` semantics.
- Make actions available through keyboard focus, not only CSS hover.
- Dialogs must have a programmatic title/description, trap focus, close with Escape, and restore focus to their trigger.
- Disable duplicate submission while pending without making error text unreachable.

Component tests cover success, 403/419/422/500 responses, focus return, Escape close, accessible names, and input retention.

**Step 6: Verify**

```bash
php artisan test tests/Feature/GroupwareMutationAuthorizationTest.php
npm run test:unit -- resources/js/components/discord/GroupwareDialogs.test.ts resources/js/pages/chat/OperationErrors.test.ts
npm run types:check
npm run lint:check
npm run build
```

Manual workflow: create → rename → use → delete for one server and channel; confirm role visibility with a second member; repeat keyboard-only and force one denied/server-error response to confirm visible feedback.

**Suggested commit:** `feat: complete server and channel management UI`

---

## Task 13: Restore CI, documentation, and clean-install readiness

**Objective:** Make every repository gate green and document every runtime service needed by a fresh installation.

**Files:**

- Modify only as required by actual diagnostics: the 30 Prettier files, 5 Pint files, and 4 PHP files containing 6 PHPStan findings.
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `.github/workflows/tests.yml`
- Modify: `README.md`
- Modify: `.env.example`

**Step 1: Fix static findings rather than suppressing them**

Resolve the known PHPStan issues in:

- `app/Http/Controllers/StoredFileController.php`
- `app/Http/Controllers/TaskController.php`
- `app/Http/Controllers/TodoController.php`
- `app/Models/Todo.php`

Fix all Svelte warnings, including nonreactive state captures and accessibility warnings. Do not lower strictness, add broad ignores, or convert warnings into accepted baseline debt.

**Step 2: Format after functional diffs stabilize**

```bash
npm run format
composer lint
```

Review the resulting diff to ensure formatting did not change generated/vendor files or unrelated project semantics.

**Step 3: Include frontend unit tests in CI**

Update local scripts and `.github/workflows/tests.yml` so CI runs `composer ci:check` (or an explicitly equivalent ordered set) with the locked PHP/Node dependencies. A green GitHub workflow must include frontend unit tests, lint, format, PHP tests, PHPStan, Svelte checks, and production build; it must not be a weaker subset of the documented local release gate.

**Step 4: Write setup/run documentation**

`README.md` and `.env.example` must document, without secrets:

- PHP/Node prerequisites and database setup.
- `composer setup` and test commands.
- Queue worker, scheduler, Reverb, and production process requirements.
- File preview dependencies such as LibreOffice/required PHP extensions.
- OnlyOffice URLs, JWT secret length, deployment ID, TTL, and network direction requirements.
- Read-only security expectations and supported formats.
- Staged-upload expiry, cleanup-ledger retry/monitoring, storage cleanup, and backup considerations.

**Step 5: Verify every gate from a clean state**

```bash
composer ci:check
npm run test:unit
npm run build
composer audit --locked
npm audit --audit-level=high
git diff --check
git status --short
```

Expected:

- Every command exits 0.
- PHPStan reports 0 errors.
- Svelte check reports 0 errors and 0 warnings.
- Prettier/Pint report no changed files.
- Dependency audits report no known vulnerabilities at the configured threshold.
- The only worktree changes are intentional implementation/test/documentation files; `.hermes/task-state.md` remains uncommitted/excluded.

**Suggested commit:** `chore: restore quality gates and deployment docs`

---

## Task 14: Final end-to-end acceptance and rollback record

**Objective:** Prove the complete user workflow and leave a reproducible release/rollback record.

**Files:**

- Update locally, do not commit unless explicitly requested: `.hermes/task-state.md`
- Modify product files only if a newly reproduced acceptance failure maps directly to C-001 through C-021.

**Steps:**

1. Start the application with isolated local/test services and no production data.
2. In browser session A, register/login, create a server and channel, rename both, and add session B as a member.
3. Confirm session B cannot manage members or delete session A's file.
4. Send text, image, PDF, and valid Office attachments; verify previews/downloads and realtime arrival with explicit short broadcast event names.
5. Upload HTML/SVG and prove it downloads or is rejected rather than executing inline.
6. Open a thread, post replies from both sessions, delete the parent as its author, reload, and verify the other user's replies survive beneath a tombstone.
7. Create, assign, toggle, and delete Todos; verify realtime convergence and outsider-assignee rejection.
8. Open server and channel Tasks/Gantt/Files pages; verify populated rails and JST date alignment across short/long ranges.
9. Generate an OnlyOffice URL, remove session B, and prove the same URL can no longer retrieve the file.
10. Reschedule a due date and execute the reminder command twice; verify exactly one reminder for the new date.
11. Cancel one staged upload and expire another; verify both are removed while permanent files remain.
12. Delete a message/file/channel/server and verify source and preview blobs are absent; simulate one failed storage deletion and prove the pending cleanup ledger succeeds on retry.
13. Close previews during load and trigger an OnlyOffice error/timeout; verify no editor/viewer survives. Repeat core dialogs keyboard-only and verify denied/server errors are visible.
14. Re-run all Task 13 gates and inspect the final diff.
15. Record in `.hermes/task-state.md`:
    - Every coverage ID and its test/evidence.
    - Final HEAD and worktree state.
    - Exact successful commands.
    - Any externally blocked OnlyOffice manual check.
    - Rollback order per suggested commit/change set and any new migration rollback command.
16. Stop when all acceptance criteria are checked. Do not continue speculative edge-case exploration after completion.

**Final verification commands:**

```bash
php artisan test
npm run test:unit
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer lint:check
composer types:check
composer ci:check
composer audit --locked
npm audit --audit-level=high
git diff --check
git status --short --branch
```

**Expected result:** All automated gates pass, all C-001 through C-021 have direct evidence, no unexplained file changes remain, and any external-only verification is explicitly identified rather than inferred.

**Suggested commit:** None unless final acceptance produces a narrowly scoped, test-backed correction.

---

## 4. Recommended execution order and review checkpoints

| Phase | Tasks | Release condition before proceeding |
|---|---|---|
| P0 unblock | 1–2 | Core authorized mutations work and unauthorized mutations fail correctly |
| Data contract | 3–4 | Utility pages render; chat resources and threads are stable |
| File security | 5–7 | Active content, ownership, lifecycle, and OnlyOffice tests pass |
| Scheduling/UI | 8–12 | Gantt, reminders, tenant invariants, realtime, and CRUD workflows pass |
| Release gate | 13–14 | CI and complete manual acceptance are green |

After each task:

1. Read `.hermes/task-state.md`.
2. Inspect `git status --short` and `git diff`.
3. Re-run the targeted test command as the parent agent.
4. Run the nearest related regression suite.
5. Update the coverage matrix with objective evidence.
6. Obtain spec-compliance review, then code-quality/security review.
7. Proceed only when both reviews pass.

Do not parallelize tasks that touch the same trust boundary or data contract. Independent test writing or read-only review may run in parallel, but Tasks 2→3→4 and Tasks 5→6→7 remain ordered because each depends on the previous payload/security contract.
