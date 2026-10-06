# CUST-001 manual walkthrough

1. Sign in as a VeLog manager and open **VeLog > Customers**.
2. Create a customer with a long Unicode name and optional international phone/email values.
3. Search by name, phone, and email; verify state filtering, deterministic ordering, and pagination.
4. Edit the customer in a second tab, then submit the stale first tab and confirm it cannot overwrite the newer version.
5. Link an active vehicle in the VEH-001 journey and confirm customer archive is rejected until the vehicle is reassigned or archived.
6. Archive and restore customers through row and bulk actions; confirm the aggregate bulk result matches per-customer outcomes.
7. As a technician, verify customer phone/email are absent from visible markup and direct customer mutations are denied.
8. Verify keyboard order, visible focus, validation states, 320px layout, long Unicode content, a real RTL locale, and absence of VeLog assets on unrelated admin pages.

Steps 1–7 have backend fixture coverage where applicable. Step 8 belongs to CUST-001-FE browser verification.
