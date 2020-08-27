# Beacon Data Collection
The tools in this repository provide data harvesting using the PKP Beacon, a lightweight mechanism to identify PKP software to us for security updates and research.

## Installation
To install the tool:
- Clone the repository locally
- Install composer dependencies: `composer install`
- Create an empty database called "beacon" (username=beacon, password=beacon) [FIXME]
- Create the database schema by running `php processBeaconLog.php -c`

## Usage

There are two stages involved in processing the beacon data:

1. Parsing the access logs
2. Processing the beacon list

### Parsing the access logs

To look through the access logs for beacons that might identify new installations (or provide updates from already-seen ones), use the tool `processBeaconLog.php`. For usage information, run:

```
php processBeaconLog.php -h
```

This is a single-threaded tool that can handle a large number of log entries relatively quickly.

### Processing the beacon list

To process the beacon list and query the installations there for more information, use the tool `processBeaconLog.php`. For usage information, run:

```
php updateBeacon.php -h
```

This is a multi-threaded tool that allows potentially several minutes for each beacon entry. Timeouts, concurrency, etc. are all configurable. Single records can be selected for update by OAI URL. See the usage information for details.

### Extracting the data

Data can be exported using the `export.php` tool:

```
php export.php > beacon.csv
```
