## Version 5.0.2 (2026101600)
* Improvement: Support Moodle 5.2 and 5.3 (`$plugin->supported = [500, 503]`). The code was checked against Moodle 5.3 for removed or deprecated APIs.
* Bugfix: Fix language strings that were shown as [[from]], [[to]], [[strftimedate]] and [[strftimedatetime]]: the date range labels in the category assignment form (the core strings 'from' and 'to' were removed in Moodle 5.0), and the date format of the ECS certificate expiry date and of the course metadata dates (these strings are in the 'langconfig' component).

## Version 5.0.1 (2026101400)
* Bugfix: When the ECS does not provide the campusconnect/member_status resource (HTTP 404, e.g. ECS 7 by free IT), enrolment status updates no longer send an "ECS connection error" message to all site admins on every cron run. The updates stay queued and are retried on the next run, and a log entry explains the reason. Other errors are reported as before. (#17)

## Version 5.0 (2026072401)
* Bugfix: Parse ECS response headers case-insensitively. ECS version 7 (by free IT) sends HTTP response headers in lower-case (content-type, location), so connecting to an ECS 7 server failed with "expected content type 'application/json' got type ''" (e.g. when loading the participant list), and the resource id of newly created resources could not be read from the location header. HTTP field names are case-insensitive per RFC 9110, so header names are now normalized to lower-case when parsing the response. (#16)
