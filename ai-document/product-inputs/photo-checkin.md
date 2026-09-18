# Feature Plan: Photo Check-in

**Status**: Draft
**Author**: Mamflow
**Created**: 2024-01-01
**Updated**: 2024-01-01

---

## Summary

Visual documentation system that allows technicians to photograph a vehicle's condition when it arrives at the shop (check-in) and when it is returned to the customer (check-out). Photos are permanently linked to the Vehicle Passport.

---

## User Story

> As a repair shop technician, I want to photograph a vehicle when it arrives and when it leaves, so that there is a clear visual record of its condition at every service event.

---

## Acceptance Criteria

- [ ] Technician can upload multiple photos per service event.
- [ ] Photos are tagged as: check-in, service, check-out.
- [ ] Photos are displayed in a gallery linked to the Vehicle Passport.
- [ ] Photo EXIF data (date, GPS if available) is extracted and stored.
- [ ] Photos are stored in WordPress media library under organized folders.

---

## Technical Design

### Storage

- WordPress media library attachments.
- Linked to `mf_service` post via post meta: `_mf_service_photos`.
- Custom upload folder: `/uploads/velog/{year}/{vehicle-id}/`

### Meta

| Field      | Key                      | Type    |
|-----------|--------------------------|---------|
| Photos     | `_mf_service_photos`     | array   |
| Photo Tags | `_mf_photo_tag`          | string  |

---

## Security Considerations

- File upload validated via `wp_check_filetype()`.
- Allowed types: jpg, jpeg, png, webp only.
- Upload capability: `upload_files` + `manage_vehicles`.
- Nonce on all upload forms.

---

## Open Questions

- Max photo upload size — use WordPress default or custom limit?
- Photo compression on upload — auto-resize to max 2048px?
- Customer-facing photo gallery — in scope for v1?
