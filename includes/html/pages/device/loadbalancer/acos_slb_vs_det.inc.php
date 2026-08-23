<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Single Virtual Server (VIP) detail page - shows this
 * VS's own metadata, its Virtual Server Ports, and graphs scoped to just
 * this one VS (not summed/overlaid with every other VS on the device).
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

$vsid = (int) ($vars['vsid'] ?? 0);
if (! isset($components[$vsid]) || $components[$vsid]['type'] != 'acos-slb-vs') {
    echo '<div class="alert alert-warning">Virtual Server not found.</div>';
    return;
}

$vs = $components[$vsid];

// Live counters are read straight from RRD (not component attrs) so routine
// per-poll changes don't get logged to the device Eventlog.
require_once 'includes/html/pages/device/loadbalancer/acos_slb_functions.inc.php';
$vsMain = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$vs['type'], $vs['label'], $vs['hash']]));
$vsL7 = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$vs['type'], 'l7', $vs['label'], $vs['hash']]));

// Link each port's Service Group straight to its Service Groups tab detail page.
$poolIdByName = [];
foreach ($components as $cid => $c) {
    if ($c['type'] == 'acos-slb-pool') {
        $poolIdByName[$c['label']] = $cid;
    }
}
$poolLink = \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_pool', 'subtype' => 'acos_slb_pool_det']);
?>
<div class="row" style="margin-bottom: 10px;">
    <div class="col-md-12">
        <span style="font-size: 20px;">Virtual Server - <?php echo $vs['label']; ?></span><br />
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
                    <td><?php echo $vsMain['curconns'] ?? ''; ?></td>
                    <td><?php echo $vsMain['totconns'] ?? ''; ?></td>
                    <td><?php echo $vsMain['peakconns'] ?? ''; ?></td>
                    <td><?php echo $vsL7['l7succreqs'] ?? ''; ?></td>
                    <td><?php echo $vsL7['l7reqs'] ?? ''; ?></td>
                    <td><?php echo $vsMain['bytesin'] ?? ''; ?></td>
                    <td><?php echo $vsMain['bytesout'] ?? ''; ?></td>
                    <td><?php echo $vsMain['pktsin'] ?? ''; ?></td>
                    <td><?php echo $vsMain['pktsout'] ?? ''; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="container-fluid">
            <div class="row">
                <div class="panel panel-default panel-condensed">
                    <div class="panel-heading"><strong>Details</strong></div>
                    <table class="table table-hover table-condensed table-striped">
                        <tr>
                            <td>Address:</td>
                            <td><?php echo $vs['address']; ?></td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td><?php echo $vs['status'] == 0 ? 'Ok' : $vs['error']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="container-fluid">
            <div class="row">
                <div class="panel panel-default panel-condensed">
                    <div class="panel-heading"><strong>Virtual Server Ports</strong></div>
                    <table class="table table-hover table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>Port</th>
                            <th>Service Group</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <?php
                        foreach ($components as $comp) {
                            if ($comp['type'] != 'acos-slb-vport') {
                                continue;
                            }
                            if (! str_starts_with((string) $comp['UID'], (string) $vs['UID'])) {
                                continue;
                            }

                            if ($comp['status'] != 0) {
                                $status = $comp['error'];
                                $rowClass = 'class="danger"';
                            } else {
                                $status = 'Ok';
                                $rowClass = '';
                            }

                            $sgName = $comp['servicegroup'];
                            if ($sgName !== null && $sgName !== '' && isset($poolIdByName[$sgName])) {
                                $sgCell = '<a href="' . $poolLink . 'poolid=' . $poolIdByName[$sgName] . '">' . htmlspecialchars((string) $sgName) . '</a>';
                            } else {
                                $sgCell = htmlspecialchars((string) $sgName);
                            } ?>
                            <tr <?php echo $rowClass; ?>>
                                <td><?php echo $comp['port']; ?></td>
                                <td><?php echo $sgCell; ?></td>
                                <td><?php echo $status; ?></td>
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
    <div class="col-md-12">
        <div class="container-fluid">
            <div class="row">
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
                        $graph_array['type'] = 'device_acos_slb_vs_curconns';
                        $graph_array['id'] = $vsid;
                        require 'includes/html/print-graphrow.inc.php';
                        ?>
                    </div>
                </div>
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
                        $graph_array['type'] = 'device_acos_slb_vs_bytesin';
                        $graph_array['id'] = $vsid;
                        require 'includes/html/print-graphrow.inc.php';
                        ?>
                    </div>
                </div>
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
                        $graph_array['type'] = 'device_acos_slb_vs_bytesout';
                        $graph_array['id'] = $vsid;
                        require 'includes/html/print-graphrow.inc.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
