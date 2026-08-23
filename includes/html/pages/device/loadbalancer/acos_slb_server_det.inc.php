<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Single Real Server detail page - shows the server's own metadata, which
 * Service Groups it's a member of (via matching servername across
 * acos-slb-member components), and graphs scoped to just this one server.
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

$serverid = (int) ($vars['serverid'] ?? 0);
if (! isset($components[$serverid]) || $components[$serverid]['type'] != 'acos-slb-server') {
    echo '<div class="alert alert-warning">Real Server not found.</div>';
    return;
}

$server = $components[$serverid];

// Live counters are read straight from RRD (not component attrs) so routine
// per-poll changes don't get logged to the device Eventlog.
require_once 'includes/html/pages/device/loadbalancer/acos_slb_functions.inc.php';
$serverMain = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$server['type'], $server['label'], $server['hash']]));
$serverL7 = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$server['type'], 'l7', $server['label'], $server['hash']]));

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
        <span style="font-size: 20px;">Real Server - <?php echo $server['label']; ?></span><br />
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
                    <td><?php echo $serverMain['curconns'] ?? ''; ?></td>
                    <td><?php echo $serverMain['totconns'] ?? ''; ?></td>
                    <td><?php echo $serverMain['peakconns'] ?? ''; ?></td>
                    <td><?php echo $serverL7['l7succreqs'] ?? ''; ?></td>
                    <td><?php echo $serverL7['l7reqs'] ?? ''; ?></td>
                    <td><?php echo $serverMain['bytesin'] ?? ''; ?></td>
                    <td><?php echo $serverMain['bytesout'] ?? ''; ?></td>
                    <td><?php echo $serverMain['pktsin'] ?? ''; ?></td>
                    <td><?php echo $serverMain['pktsout'] ?? ''; ?></td>
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
                            <td><?php echo $server['address']; ?></td>
                        </tr>
                        <tr>
                            <td>Health Monitor:</td>
                            <td><?php echo $server['healthmonitor']; ?></td>
                        </tr>
                        <tr>
                            <td>Weight:</td>
                            <td><?php echo $server['weight']; ?></td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td><?php echo $server['status'] == 0 ? 'Ok' : $server['error']; ?></td>
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
                    <div class="panel-heading"><strong>Service Group Memberships</strong></div>
                    <table class="table table-hover table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>Service Group</th>
                            <th>Port</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <?php
                        foreach ($components as $member) {
                            if ($member['type'] != 'acos-slb-member') {
                                continue;
                            }
                            if ($member['servername'] != $server['label']) {
                                continue;
                            }

                            if ($member['status'] != 0) {
                                $memberStatus = $member['error'];
                                $rowClass = 'class="danger"';
                            } else {
                                $memberStatus = 'Ok';
                                $rowClass = '';
                            }

                            if (isset($poolIdByName[$member['poolname']])) {
                                $poolCell = '<a href="' . $poolLink . 'poolid=' . $poolIdByName[$member['poolname']] . '">' . htmlspecialchars((string) $member['poolname']) . '</a>';
                            } else {
                                $poolCell = htmlspecialchars((string) $member['poolname']);
                            } ?>
                            <tr <?php echo $rowClass; ?>>
                                <td><?php echo $poolCell; ?></td>
                                <td><?php echo $member['port']; ?></td>
                                <td><?php echo $member['priority']; ?></td>
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
                        $graph_array['type'] = 'device_acos_slb_server_curconns';
                        $graph_array['id'] = $serverid;
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
                        $graph_array['type'] = 'device_acos_slb_server_bytesin';
                        $graph_array['id'] = $serverid;
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
                        $graph_array['type'] = 'device_acos_slb_server_bytesout';
                        $graph_array['id'] = $serverid;
                        require 'includes/html/print-graphrow.inc.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
