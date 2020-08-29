<?php

class Beacon {
	const DEFAULT_CONCURRENCY = 50;
	const DEFAULT_TASK_TIMEOUT = 120;
	const DEFAULT_REQUEST_TIMEOUT = 60;
	const DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES = 60 * 60 * 24 * 7; // Also hard-coded in usage texts
}
