# Feature Plan: Service Timeline

**Status**: Draft
**Author**: Mamflow
**Created**: 2024-01-01
**Updated**: 2024-01-01

---

## Summary

A chronological record of all services performed on a vehicle. Each entry in the timeline represents one service event: date, mileage, description, technician, cost, and attached photos.

---

## User Story

> As a repair shop technician, I want to log each service I perform on a vehicle, so that the customer has a permanent, trustworthy record of their car's maintenance history.

---

## Acceptance Criteria

- [ ] Technician can create a service entry linked to a Vehicle Passport.
- [ ] Service entry includes: date, mileage, service type, description, cost, technician name.
- [ ] Photos can be attached to a service entry.
- [ ] Timeline is displayed in reverse chronological order.
- [ ] Timeline is filterable by service type and date range.
- [ ] Timeline is exportable.

---

## Technical Design

### Custom Post Type

- Post type: `mf_service`
- Parent: linked via `_mf_service_vehicle_id` meta
- Taxonomy: `mf_service_type` (Oil Change, Brake Service, Electrical, etc.)

### Meta Fields

| Field           | Key                         | Type    |
|----------------|-----------------------------|---------|
| Vehicle ID     | `_mf_service_vehicle_id`    | integer |
| Date           | `_mf_service_date`          | string  |
| Mileage        | `_mf_service_mileage`       | integer |
| Cost           | `_mf_service_cost`          | float   |
| Technician     | `_mf_service_technician`    | string  |
| Notes          | `_mf_service_notes`         | string  |

---

## Security Considerations

- Only authenticated users with `log_vehicle_service` capability can create entries.
- Nonce validation on all form submissions.
- Cost field sanitized as float.
- Notes sanitized with `sanitize_textarea_field()`.

---

## Test Plan

- Unit: Meta sanitization, taxonomy registration.
- Integration: Service entry creation, vehicle linkage.
- Manual: Full service log workflow.

---

## Open Questions

- Should service types be user-configurable (admin UI) or hardcoded taxonomy?
- Cost currency — use WooCommerce currency settings or plugin-specific?
