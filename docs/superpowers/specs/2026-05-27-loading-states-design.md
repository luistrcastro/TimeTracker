# Loading States for HTTP-Calling Components

**Date:** 2026-05-27
**Status:** Approved

---

## Overview

Add visual loading states to every frontend component that makes HTTP requests but currently provides no feedback while waiting for a response. The goal is consistent UX: buttons show spinners while their request is in flight, destructive actions are disabled during any loading, and double-submissions are prevented.

---

## Scope

### Components with gaps (need changes)

| Component | Missing |
|---|---|
| `ContractorEntryEditDialog` | `saving` ref on Save Changes button |
| `RepliconEntryEditDialog` | `saving` ref on Save Changes button |
| `ContractorEntryRowNew` | `saving` ref on Save button |
| `RepliconEntryRowNew` | `saving` ref on Save button |
| `ContractorDayEntryTable` | Per-row loading on delete |
| `RepliconDayEntryTable` | Per-row loading on delete |
| `RepliconDayEntryTable` | Per-row loading on toggle logged checkbox |

### Components already correct (no changes)

`InvoiceDetailDialog`, `InvoiceCreateCard`, `CredentialsCard`, `RowMapEditor`, `compiled.vue` (submit), `profile.vue`, `CompanySettingsCard`, `ClientDetailsCard`.

---

## Pattern

All loading state is **component-local**. No store changes. No new composables. This matches the existing pattern in `InvoiceDetailDialog` and `CredentialsCard`.

```typescript
const saving = ref(false)

async function save() {
  saving.value = true
  try {
    await store.someAction(...)
    // existing post-save logic
  } finally {
    saving.value = false
  }
}
```

---

## Implementation Details

### Entry edit dialogs (`ContractorEntryEditDialog`, `RepliconEntryEditDialog`)

- Add `const saving = ref(false)` in `<script setup>`
- Wrap existing `save()` function body in `saving.value = true` / try / finally
- Save Changes button: add `:loading="saving"`
- Cancel button: add `:disabled="saving"` (prevents closing mid-flight)

### Entry new rows (`ContractorEntryRowNew`, `RepliconEntryRowNew`)

- Add `const saving = ref(false)` in `<script setup>`
- Wrap existing `save()` function body in `saving.value = true` / try / finally
- Save button: add `:loading="saving"` and extend `:disabled` to `!canSave || saving`

### Entry tables — delete (`ContractorDayEntryTable`, `RepliconDayEntryTable`)

- Add `const deletingId = ref<string | null>(null)` in `<script setup>`
- Extract inline delete call into an `async function deleteEntry(id: string)` that sets/clears `deletingId`
- Delete button per row:
  - `:loading="deletingId === row.id"`
  - `:disabled="deletingId !== null"` (prevents deleting two rows simultaneously)

```typescript
const deletingId = ref<string | null>(null)

async function deleteEntry(id: string) {
  deletingId.value = id
  try {
    await store.remove(id)
  } finally {
    deletingId.value = null
  }
}
```

### Replicon table — toggle logged (`RepliconDayEntryTable`)

- Add `const togglingId = ref<string | null>(null)` in `<script setup>`
- Extract inline toggle call into an `async function toggleLogged(row)` that sets/clears `togglingId`
- Logged checkbox: `:disabled="togglingId !== null"` (checkbox has no spinner; disabling is sufficient)

```typescript
const togglingId = ref<string | null>(null)

async function toggleLogged(row: RepliconTimeEntry) {
  togglingId.value = row.id!
  try {
    await replicon.update(row.id!, { logged: !row.logged })
  } finally {
    togglingId.value = null
  }
}
```

---

## Constraints

- No store-level loading state — UI state stays in components
- No new composables or abstractions
- All loading refs follow `ref<boolean | string | null>` — no complex state machines
- Disabled state on adjacent actions prevents double-submissions during loading
