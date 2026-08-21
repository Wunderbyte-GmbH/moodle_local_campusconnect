## Version 4.2 (2024080506)
* Improvement: Raise the minimum required Moodle version to 4.5 and test only supported Moodle versions (4.5 on PHP 8.1-8.3) in the CI matrix. (#14)

## Version 4.2 (2024080505)
* Improvement: Fix coding style across the plugin so the Moodle Plugin CI codechecker passes (phpcbf with the moodle standard plus manual fixes; no functional changes). (#3)

## Version 4.2 (2024080504)
* Bugfix: Parse ECS response headers case-insensitively. ECS version 7 (by free IT) sends HTTP response headers in lower-case (content-type, location), so connecting to an ECS 7 server failed with "expected content type 'application/json' got type ''" (e.g. when loading the participant list), and the resource id of newly created resources could not be read from the location header. HTTP field names are case-insensitive per RFC 9110, so header names are now normalized to lower-case when parsing the response. (#16)
