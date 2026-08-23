<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Single Service Group (Pool) detail page - shows this
 * pool's members (name/port/priority/status) plus graphs scoped to just this
 * one pool via includes/html/graphs/device/acos_slb_allpm_*.inc.php.
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

$component = new LibreNMS\Component();
$components = $component->getComponents($device['device_id']);
$components = $components[$device['device_id']] ?? [];

$poolid = (int) ($vars['poolid'] ?? 0);
if (! isset($components[$poolid]) || $components[$poolid]['type'] != 'acos-slb-pool') {
    echo '<div class="alert alert-warning">Service Group not found.</div>';
    return;
}

$pool = $components[$poolid];

// Live counters are read straight from RRD (not component attrs) so routine
// per-poll changes don't get logged to the device Eventlog.
require_once 'includes/html/pages/device/loadbalancer/acos_slb_functions.inc.php';
$poolMain = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$pool['type'], $pool['label'], $pool['hash']]));
$poolL7 = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$pool['type'], 'l7', $pool['label'], $pool['hash']]));

// Members reference a Real Server by name; join in that server's own health monitor + detail link.
$healthMonitorByServerName = [];
$serverIdByName = [];
foreach ($components as $cid => $c) {
    if ($c['type'] == 'acos-slb-server') {
        $healthMonitorByServerName[$c['label']] = $c['healthmonitor'];
        $serverIdByName[$c['label']] = $cid;
    }
}
$serverLink = \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_server', 'subtype' => 'acos_slb_server_det']);

$serverCounts = ['up' => 0, 'down' => 0, 'disabled' => 0];
foreach ($components as $c) {
    if ($c['type'] != 'acos-slb-member' || ! str_starts_with((string) $c['UID'], (string) $pool['UID'])) {
        continue;
    }
    if ($c['status'] == 2) {
        $serverCounts['down']++;
    } elseif ($c['status'] == 1) {
        $serverCounts['disabled']++;
    } else {
        $serverCounts['up']++;
    }
}
?>
<div class="row" style="margin-bottom: 10px;">
    <div class="col-md-12">
        <span style="font-size: 20px;">Service Group - <?php echo $pool['label']; ?></span><br />
    </div>
</div>
<div class="row" style="margin-bottom: 10px;">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed">
            <div class="panel-heading"><strong>Statistics</strong></div>
            <table class="table table-hover table-condensed table-striped">
                <thead>
                <tr>
                    <th colspan="3">Connections</th>
                    <th colspan="2">Requests</th>
                    <th colspan="2">Bytes</th>
                    <th colspan="2">Packets</th>
                </tr>
                <tr>
                    <th>Current</th>
                    <th>Total</th>
                    <th>Peak</th>
                    <th>Success</th>
                    <th>Total</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>In</th>
                    <th>Out</th>
                </tr>
                </thead>
                <tr>
                    <td><?php echo $poolMain['curconns'] ?? ''; ?></td>
                    <td><?php echo $poolMain['totconns'] ?? ''; ?></td>
                    <td><?php echo $poolMain['peakconns'] ?? ''; ?></td>
                    <td><?php echo $poolL7['l7succreqs'] ?? ''; ?></td>
                    <td><?php echo $poolL7['l7reqs'] ?? ''; ?></td>
                    <td><?php echo $poolMain['bytesin'] ?? ''; ?></td>
                    <td><?php echo $poolMain['bytesout'] ?? ''; ?></td>
                    <td><?php echo $poolMain['pktsin'] ?? ''; ?></td>
                    <td><?php echo $poolMain['pktsout'] ?? ''; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="container-fluid">
            <div class="row">
                <div class="panel panel-default panel-condensed">
                    <div class="panel-heading">
                        <strong>Members</strong>
                        &mdash; <?php echo $pool['status'] == 0 ? 'Ok' : $pool['error']; ?>
                        &mdash; Servers: <?php echo $serverCounts['up']; ?> Up / <?php echo $serverCounts['down']; ?> Down / <?php echo $serverCounts['disabled']; ?> Disabled
                    </div>
                    <table class="table table-hover table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>Member</th>
                            <th>Port</th>
                            <th>Priority</th>
                            <th>Health Monitor</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <?php
                        foreach ($components as $member) {
                            if ($member['type'] != 'acos-slb-member') {
                                continue;
                            }
                            if (! str_starts_with((string) $member['UID'], (string) $pool['UID'])) {
                                continue;
                            }

                            if ($member['status'] != 0) {
                                $memberStatus = $member['error'];
                                $rowClass = 'class="danger"';
                            } else {
                                $memberStatus = 'Ok';
                                $rowClass = '';
                            }

                            if (isset($serverIdByName[$member['servername']])) {
                                $memberCell = '<a href="' . $serverLink . 'serverid=' . $serverIdByName[$member['servername']] . '">' . htmlspecialchars((string) $member['servername']) . '</a>';
                            } else {
                                $memberCell = htmlspecialchars((string) $member['servername']);
                            } ?>
                            <tr <?php echo $rowClass; ?>>
                                <td><?php echo $memberCell; ?></td>
                                <td><?php echo $member['port']; ?></td>
                                <td><?php echo $member['priority']; ?></td>
                                <td><?php echo $healthMonitorByServerName[$member['servername']] ?? ''; ?></td>
                                <td><?php echo $memberStatus; ?></td>
                            </tr>
                            <?php
                        } ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default" id="currconnections">
            <div class="panel-heading"><h3 class="panel-title">Current Connections</h3></div>
            <div class="panel-body">
                <?php
                $graph_array = [];
                $graph_array['device'] = $device['device_id'];
                $graph_array['height'] = '100';
                $graph_array['width'] = '215';
                $graph_array['legend'] = 'no';
                $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
                $graph_array['type'] = 'device_acos_slb_allpm_curconns';
                $graph_array['id'] = $poolid;
                require 'includes/html/print-graphrow.inc.php';
                ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default" id="bytesin">
            <div class="panel-heading"><h3 class="panel-title">Bytes In</h3></div>
            <div class="panel-body">
                <?php
                $graph_array = [];
                $graph_array['device'] = $device['device_id'];
                $graph_array['height'] = '100';
                $graph_array['width'] = '215';
                $graph_array['legend'] = 'no';
                $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
                $graph_array['type'] = 'device_acos_slb_allpm_bytesin';
                $graph_array['id'] = $poolid;
                require 'includes/html/print-graphrow.inc.php';
                ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default" id="bytesout">
            <div class="panel-heading"><h3 class="panel-title">Bytes Out</h3></div>
            <div class="panel-body">
                <?php
                $graph_array = [];
                $graph_array['device'] = $device['device_id'];
                $graph_array['height'] = '100';
                $graph_array['width'] = '215';
                $graph_array['legend'] = 'no';
                $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
                $graph_array['type'] = 'device_acos_slb_allpm_bytesout';
                $graph_array['id'] = $poolid;
                require 'includes/html/print-graphrow.inc.php';
                ?>
            </div>
        </div>
    </div>
</div>
