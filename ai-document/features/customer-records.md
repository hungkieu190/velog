# Customer records

Managers and administrators with the VeLog customer capabilities can create, edit, search, archive, and restore private customer records in WordPress admin. A customer contains a required Unicode name and optional phone and email values. Duplicate names and contact values are allowed; the internal record ID distinguishes records.

Technicians may receive an operational customer summary containing only ID, name, and state. Phone and email are manager/administrator-only and are not placed in technician HTML, URLs, notices, or audit snapshots returned to them.

Archiving preserves the full record. It is rejected while an active vehicle identifies that customer as its current owner. The manager must reassign or archive the active vehicle first. Restore is versioned and audited. There is no customer hard-delete action.

List search is bounded to 100 Unicode characters and 50 results per page. State, name/ID sort, direction, and page inputs are allowlisted. Bulk archive/restore processes each selected customer separately and reports aggregate successes and failures.
