<?php

expect_same('megabytes from an ini string', return_bytes('2M'), 2 * 1024 * 1024);
expect_same('gigabytes from an ini string', return_bytes('1G'), 1073741824);
expect_same('kilobytes from an ini string', return_bytes('10K'), 10240);
expect_same('plain byte string', return_bytes('100'), 100);

expect_same('display kilobytes', bytes_to_display(1536), [1.5, 'k']);
expect_same('display bytes', bytes_to_display(12), [12, 'b']);
expect_same('display megabytes', bytes_to_display(1048576), [1.0, 'm']);

expect_same('form megabytes keep a fraction', form_size_to_bytes('1.5', 'm'), 1572864);
expect_same('unknown unit is treated as bytes', form_size_to_bytes(40, 'tb'), 40);

expect_same('ini size megabytes', bytes_to_ini_size(1048576), '1M');
expect_same('ini size gigabytes', bytes_to_ini_size(1073741824), '1G');
expect_same('ini size zero', bytes_to_ini_size(0), '0');

expect_same('filesize bytes', format_filesize(12), '12 B');
expect_same('filesize kilobytes', format_filesize(1536), '1.5 KB');
expect_same('filesize megabytes', format_filesize(1048576), '1 MB');
