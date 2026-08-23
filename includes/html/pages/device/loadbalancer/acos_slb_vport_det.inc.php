<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Single Virtual Service (Virtual Server Port / listener) detail page - shows
 * its own metadata, live statistics, and graphs scoped to just this one
 * service (not summed/overlaid with every other Virtual Service).
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

$vportid = (int) ($vars['vportid'] ?? 0);
if (! isset($components[$vportid]) || $components[$vportid]['type'] != 'acos-slb-vport') {
    echo '<div class="alert alert-warning">Virtual Service not found.</div>';
    return;
}

$vport = $components[$vportid];

// Live counters are read straight from RRD (not component attrs) so routine
// per-poll changes don't get logged to the device Eventlog.
require_once 'includes/html/pages/device/loadbalancer/acos_slb_functions.inc.php';
$vportMain = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$vport['type'], $vport['label'], $vport['hash']]));
$vportL7 = acos_slb_rrd_lastvalues(\App\Facades\Rrd::name($device['hostname'], [$vport['type'], 'l7', $vport['label'], $vport['hash']]));

$poolIdByName = [];
$vsIdByLabel = [];
foreach ($components as $cid => $c) {
    if ($c['type'] == 'acos-slb-pool') {
        $poolIdByName[$c['label']] = $cid;
    } elseif ($c['type'] == 'acos-slb-vs') {
        $vsIdByLabel[$c['label']] = $cid;
    }
}
$poolLink = \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_pool', 'subtype' => 'acos_slb_pool_det']);
$vsLink = \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_vs', 'subtype' => 'acos_slb_vs_det']);
?>
<div class="row" style="margin-bottom: 10px;">
    <div class="col-md-12">
        <span style="font-size: 20px;">Virtual Service - <?php echo $vport['vsname']; ?>:<?php echo $vport['port']; ?></span><br />
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
                    <td><?php echo $vportMain['curconns'] ?? ''; ?></td>
                    <td><?php echo $vportMain['totconns'] ?? ''; ?></td>
                    <td><?php echo $vportMain['peakconns'] ?? ''; ?></td>
                    <td><?php echo $vportL7['l7succreqs'] ?? ''; ?></td>
                    <td><?php echo $vportL7['l7reqs'] ?? ''; ?></td>
                    <td><?php echo $vportMain['bytesin'] ?? ''; ?></td>
                    <td><?php echo $vportMain['bytesout'] ?? ''; ?></td>
                    <td><?php echo $vportMain['pktsin'] ?? ''; ?></td>
                    <td><?php echo $vportMain['pktsout'] ?? ''; ?></td>
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
                    <div class="panel-heading"><strong>Details</strong></div>
                    <table class="table table-hover table-condensed table-striped">
                        <tr>
                            <td>Virtual Server:</td>
                            <td>
                                <?php if (isset($vsIdByLabel[$vport['vsname']])): ?>
                                    <a href="<?php echo $vsLink; ?>vsid=<?php echo $vsIdByLabel[$vport['vsname']]; ?>"><?php echo $vport['vsname']; ?></a>
                                <?php else: ?>
                                    <?php echo $vport['vsname']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Address:</td>
                            <td><?php echo $vport['address']; ?></td>
                        </tr>
                        <tr>
                            <td>Port:</td>
                            <td><?php echo $vport['port']; ?></td>
                        </tr>
                        <tr>
                            <td>Service Group:</td>
                            <td>
                                <?php if (isset($poolIdByName[$vport['servicegroup']])): ?>
                                    <a href="<?php echo $poolLink; ?>poolid=<?php echo $poolIdByName[$vport['servicegroup']]; ?>"><?php echo $vport['servicegroup']; ?></a>
                                <?php else: ?>
                                    <?php echo $vport['servicegroup']; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td><?php echo $vport['status'] == 0 ? 'Ok' : $vport['error']; ?></td>
                        </tr>
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
                $graph_array['type'] = 'device_acos_slb_vport_curconns';
                $graph_array['id'] = $vportid;
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
                $graph_array['type'] = 'device_acos_slb_vport_bytesin';
                $graph_array['id'] = $vportid;
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
                $graph_array['type'] = 'device_acos_slb_vport_bytesout';
                $graph_array['id'] = $vportid;
                require 'includes/html/print-graphrow.inc.php';
                ?>
            </div>
        </div>
    </div>
</div>
