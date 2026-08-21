## Version 5.0 (2026072402)
* Improvement: Fix coding style across the plugin so the Moodle Plugin CI codechecker passes (phpcbf with the moodle standard plus manual fixes; no functional changes). (#3)

## Version 5.0 (2026072401)
* Bugfix: Parse ECS response headers case-insensitively. ECS version 7 (by free IT) sends HTTP response headers in lower-case (content-type, location), so connecting to an ECS 7 server failed with "expected content type 'application/json' got type ''" (e.g. when loading the participant list), and the resource id of newly created resources could not be read from the location header. HTTP field names are case-insensitive per RFC 9110, so header names are now normalized to lower-case when parsing the response. (#16)
