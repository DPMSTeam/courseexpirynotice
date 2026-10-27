# Course Enrolment Duration block (`block_courseexpirynotice`)

A Moodle block that shows a learner's active course enrolments together
with their **enrolment expiry dates** and the number of days remaining,
on the Dashboard (My courses) page.

- **Component:** `block_courseexpirynotice`
- **Display name:** Course Enrolment Duration
- **Plugin type:** Block
- **Moodle versions:** 4.0 and later (requires `2022041900` or newer)
- **Maturity:** Stable
- **License:** GNU GPL v3 or later

---

## What the block shows

For each course where the signed-in user has an **active** enrolment, the
block displays:

| Item | Details |
|------|---------|
| Course name | The course full name, filtered in the course context |
| Expiry date | The enrolment end date, formatted in the site time zone (`userdate()`) |
| Status | One of: **Days remaining: N**, **Expires today**, **Expired**, or **No expiry** |

### Enrolment rules applied

The block intentionally **excludes** data that the learner cannot act on:

- **Suspended enrolments** (`user_enrolments.status = ENROL_USER_ACTIVE`) are hidden.
- **Disabled enrolment methods** (`enrol.status = ENROL_INSTANCE_ENABLED`) are hidden.
- **Hidden courses** are hidden, unless the user holds
  `moodle/course:viewhiddencourses` in that course's context.
- When a user has **more than one active enrolment** in the same course,
  the course is listed **once**:
  - if **any** qualifying enrolment has no end date, the course is shown as **No expiry**;
  - otherwise the **latest** end date is used.

### Pagination

Five courses are shown per page, with Moodle's native paging bar and the
`courseexpirynotice_page` URL parameter. Pagination is rendered only when
more than five courses are available.

If the user has no visible active enrolments, the block displays
**"No enrolled courses found."**

---

## Where to add the block

The block is restricted to the **Dashboard**:

```php
public function applicable_formats() {
    return [
        'my'         => true,
        'course-view' => false,
        'site'        => false,
    ];
}