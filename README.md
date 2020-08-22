# Beacon Data Collection
The tools in this repository provide data harvesting using the PKP Beacon, a lightweight mechanism to identify PKP software to us for security updates and research.

## Installation
To install the tool:
- Clone the repository locally
- Install composer dependencies: `composer install`
- Use the blank CSV file to seed the list: `cp beacon-blank.csv beacon.csv`

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

## Notes

### File Locking

Because the tools use a CSV flat-file for information storage, file locking is necessary to make sure that simultaneous edits don't damage the file.

The `processBeaconLog.php` file locks the `beacon.csv` file throughout its run.

The `updateBeacon.php` tool locks the `beacon.csv` file only briefly when saving each row.

Both tools will wait until a lock is released before proceeding, so while running them at different times is best, running them simultaneously won't cause damage.

### CSV File Details

The CSV file has column names in the first row. These need to match symbolic values used in the code. If starting from scratch, use `beacon-blank.csv`. New columns can be added manually to the CSV, but make sure neither tool is currently running.
