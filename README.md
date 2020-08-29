# Beacon Data Collection
The tools in this repository provide data harvesting using the PKP Beacon, a lightweight mechanism to identify PKP software to us for security updates and research.

## Installation
To install the tool:
- Clone the repository locally
- Install composer dependencies: `composer install`
- Create an empty database called "beacon" (username=beacon, password=beacon). (This can be overridden using environment variables.)
- Create the database schema by running `php manage.php -c

## Usage

There are three stages involved in processing the beacon data:

1. Parsing the access logs for installations
2. Extracting the list of contexts (e.g. journals) from the installations
3. Updating the beacon data for each context.

### Parsing the access logs

To look through the access logs for beacons that might identify new installations (or provide updates from already-seen ones), use the tool `processBeaconLog.php`. For usage information, run:

```
php processBeaconLog.php -h
```

This is a single-threaded tool that can handle a large number of log entries relatively quickly.

### Extracting the context list

To extract the context list, use the tool `extractContexts.php`. For usage information, run:

```
php extractContexts.php -h
```

This is a multi-threaded tool that allows potentially several minutes for each beacon entry. Timeouts, concurrency, etc. are all configurable. Records can be selected for update by OAI URL. See the usage information for details.

### Processing the beacon list

To update the beacon data for each context, use the tool `updateBeacon.php`. For usage information, run:

```
php updateBeacon.php -h
```

This is a multi-threaded tool that allows potentially several minutes for each beacon entry. Timeouts, concurrency, etc. are all configurable. Records can be selected for update by OAI URL. See the usage information for details.

### Extracting the data

Data can be exported using the `export.php` tool:

```
php export.php > beacon.csv
```
