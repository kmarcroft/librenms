<?php
/*
 * Shared helpers for the A10 ACOS SLB Load Balancer detail pages.
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

use Symfony\Component\Process\Process;

if (! function_exists('acos_slb_rrd_lastvalues')) {
    // Core LibreNMS's Rrd::lastUpdate() returns null against this module's RRD
    // files on this environment's rrdtool (its regex expects the persistent
    // piped-process "OK" suffix, which a plain `rrdtool lastupdate` never has) -
    // shell out directly instead, the same way core's own RrdProcess does.
    function acos_slb_rrd_lastvalues(string $filename): array
    {
        if (! is_file($filename)) {
            return [];
        }

        $rrdtool = \App\Facades\LibrenmsConfig::get('rrdtool', 'rrdtool');
        $env = ['LC_ALL' => 'C'];
        $rrdcached = \App\Facades\LibrenmsConfig::get('rrdcached', '');
        if ($rrdcached) {
            $env['RRDCACHED_ADDRESS'] = $rrdcached;
        }

        $process = new Process([$rrdtool, 'lastupdate', $filename], null, $env);
        $process->run();
        if (! $process->isSuccessful()) {
            return [];
        }

        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        if (count($lines) < 2) {
            return [];
        }

        $dsNames = preg_split('/\s+/', trim($lines[0]));
        [, $valuesPart] = explode(':', $lines[1], 2);
        $values = preg_split('/\s+/', trim($valuesPart));

        return array_combine($dsNames, $values) ?: [];
    }
}
