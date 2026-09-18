# Feature Plan: Vehicle Passport

**Status**: Draft
**Author**: Mamflow
**Created**: 2024-01-01
**Updated**: 2024-01-01

---

## Summary

A digital identity card for every vehicle managed in the system. The Vehicle Passport is the core entity of VeLog — it aggregates all information related to a vehicle: owner, VIN, specifications, service history, photos, and reminders.

---

## User Story

> As a repair shop owner, I want to create a digital passport for each vehicle I service, so that I can maintain a complete and accessible history for every car in my system.

---

## Acceptance Criteria

- [ ] Shop owner can create a new Vehicle Passport with: VIN, license plate, make, model, year, color, mileage.
- [ ] Each passport has a unique, shareable URL (QR-code-ready).
- [ ] Passport displays complete service history chronologically.
- [ ] Passport can be linked to a customer profile.
- [ ] Passport is accessible from the admin dashboard.
- [ ] Passport can be exported as PDF.

---

## Technical Design

### Custom Post Type

- Post type: `mf_vehicle`
- Capabilities: `manage_vehicles`
- Publicly queryable: `false` (admin-only initially)
- Has archive: `false`

### Meta Fields

| Field               | Key                        | Type    |
|--------------------|----------------------------|---------|
| VIN                | `_mf_vehicle_vin`          | string  |
| License Plate      | `_mf_vehicle_plate`        | string  |
| Make               | `_mf_vehicle_make`         | string  |
| Model              | `_mf_vehicle_model`        | string  |
| Year               | `_mf_vehicle_year`         | integer |
| Color              | `_mf_vehicle_color`        | string  |
| Current Mileage    | `_mf_vehicle_mileage`      | integer |
| Customer ID        | `_mf_vehicle_customer_id`  | integer |

---

## Database Schema

No custom tables required at this stage — uses WordPress post meta.

---

## REST API

```
GET    /wp-json/velog/v1/vehicles
GET    /wp-json/velog/v1/vehicles/{id}
POST   /wp-json/velog/v1/vehicles
PUT    /wp-json/velog/v1/vehicles/{id}
DELETE /wp-json/velog/v1/vehicles/{id}
```

---

## Security Considerations

- All endpoints require authentication (`is_user_logged_in()`).
- Create/Update/Delete require `manage_vehicles` capability.
- VIN is sanitized as alphanumeric string.
- All output is escaped via `esc_html()` / `esc_attr()`.

---

## Test Plan

- Unit: VIN sanitization, meta field registration.
- Integration: CPT registration, REST API responses.
- Manual: Create/Read/Update/Delete passport via admin UI.

---

## Open Questions

- Should passports be publicly accessible via a shareable link?
- QR code generation — third-party library or custom?
- PDF export — approach TBD.
