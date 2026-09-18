# Feature Plan: Maintenance Reminder

**Status**: Draft
**Author**: Mamflow
**Created**: 2024-01-01
**Updated**: 2024-01-01

---

## Summary

Proactive scheduling system that alerts shop staff and vehicle owners when a vehicle is due for maintenance (oil change, tire rotation, inspection, etc.) based on mileage or date intervals.

---

## User Story

> As a repair shop owner, I want the system to automatically remind me when a customer's vehicle is due for service, so that I can proactively reach out and improve customer retention.

---

## Acceptance Criteria

- [ ] Admin can define reminder rules: trigger by mileage interval OR date interval.
- [ ] Reminders are linked to a Vehicle Passport.
- [ ] System sends email notifications when a reminder is due.
- [ ] Admin dashboard shows all upcoming reminders.
- [ ] Reminders can be snoozed or marked as completed.

---

## Technical Design

### Custom Post Type

- Post type: `mf_reminder`
- Meta fields for trigger conditions and notification status.

### Cron

- WP-Cron event: `mf_velog_check_reminders` (daily)
- Registered in `Activator::schedule_events()`
- Cleared in `Deactivator::clear_scheduled_events()`

---

## Security Considerations

- Email sending uses `wp_mail()` only — no third-party mailers.
- Reminder creation requires `manage_vehicles` capability.

---

## Open Questions

- SMS notifications — out of scope for v1?
- Customer-facing reminder portal — future feature?
