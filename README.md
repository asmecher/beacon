# Beacon Data Collection
The tools in this repository provide data harvesting using the PKP Beacon, a lightweight mechanism to identify PKP software to us for security updates and research.

## Installation
To install the tool:
- Clone the repository locally
- Install composer dependencies: `composer install`
- Create an empty database called "beacon" (username=beacon, password=beacon). (This can be overridden using environment variables, see .)
- Create the database schema by running `php beacon.php database create`

## Usage

There are three stages involved in processing the beacon data:

1. Parsing the PKP webserver's access logs to find installations using beacon pings
2. Extracting the list of contexts (e.g. journals) from the installations
3. Updating the beacon data for each context.

Each stage is executed by running a different script.

### Parsing the access logs

To look through the access logs for beacons that might identify new installations (or provide updates from already-seen ones), use the `process-log` command. For usage information, run:

```
php beacon.php process-log -h
```

This is a single-threaded tool that can handle a large number of log entries relatively quickly.

Additional processing done at this stage:
- OAI URLs are filtered for common development/testing characteristics like `localhost`. These are skipped. (Details: https://github.com/asmecher/beacon/blob/main/src/Entities/Endpoints.php#L12)
- Some basic deduplication is done. If the OAI URL (excluding its `http`/`https` protocol and `www.` prefix) and stats ID are already in the database, a new entry is not created. (Details: https://github.com/asmecher/beacon/blob/main/classes/Endpoints.inc.php#L18)

### Extracting the context list

In this stage, a list of contexts (e.g. journals) is extracted from each OAI endpoint.

To extract the context list, use the `scan` command. For usage information, run:

```
php beacon.php scan -h
```

This is a multi-threaded tool that allows potentially several minutes for each beacon entry. Timeouts, concurrency, etc. are all configurable. Records can be selected for update by OAI URL. See the usage information for details.

Additional processing done at this stage:
- The `driver` set specifier is excluded from further processing. Though it's possible that there's a journal called `driver`, this set is also added by the DRIVER plugin and can be misunderstood by the beacon as a journal set.

### Processing the beacon list

In this stage, the data stored in the beacon for each context (e.g. journal or preprint server) is enriched.

To update the beacon data for each context, use the `synchronize` command. For usage information, run:

```
php beacon.php synchronize -h
```

This is a multi-threaded tool that allows potentially several minutes for each beacon entry. Timeouts, concurrency, etc. are all configurable. Records can be selected for update by OAI URL. See the usage information for details.

Additional processing done at this stage:
- If one is not yet stored, the ISSN is fetched for each context by looking for ISSN-like data in a DC record's `source` element. (The first available ISSN-like data is used.)
- If one is not yet stored and an ISSN is available, the country is looked up from the ISSN using the ISSN API.
- If one is not yet stored, A "count span" is fetched and stored for the specified year (default: the previous year from June onward in the current year) based on the number of modified records.

### Extracting the data

Data can be exported as CSV using the `export` command:

```
php beacon.php export > beacon.csv
```

Additional processing done at this stage:
- If selected in the export options, limits e.g. the number of active records in the last year can be applied here.
- Further deduplication is applied. Entries are grouped into a single row if they share the same:
  - application
  - version
  - administrator email
  - earliest record datestamp
  - OAI repository name
  - OAI set specifier
