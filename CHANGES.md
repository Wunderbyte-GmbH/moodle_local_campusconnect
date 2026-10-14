## Version 5.0.1 (2026101400)
* Bugfix: When the ECS does not provide the campusconnect/member_status resource (HTTP 404, e.g. ECS 7 by free IT), enrolment status updates no longer send an "ECS connection error" message to all site admins on every cron run. The updates stay queued and are retried on the next run, and a log entry explains the reason. Other errors are reported as before. (#17)

## Version 5.0 (2026072401)
* Bugfix: Parse ECS response headers case-insensitively. ECS version 7 (by free IT) sends HTTP response headers in lower-case (content-type, location), so connecting to an ECS 7 server failed with "expected content type 'application/json' got type ''" (e.g. when loading the participant list), and the resource id of newly created resources could not be read from the location header. HTTP field names are case-insensitive per RFC 9110, so header names are now normalized to lower-case when parsing the response. (#16)
