<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Lists Virtual Server Ports (listeners), i.e. axVirtualServerPortStatTable -
 * matches the A10 GUI's own separate "Virtual Services" tab (as distinct
 * from the top-level "Virtual Servers" / VIP tab). One row each, click a row
 * to see its detail page + graphs.
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

$component = new LibreNMS\Component();
$components = $component->getComponents($device['device_id'], ['filter' => ['disabled' => ['=', 0]]]);
$components = $components[$device['device_id']] ?? [];

// Link each row's Service Group straight to its Service Groups tab detail page.
$poolIdByName = [];
// Link each row's Virtual Server name back to that VS's own detail page.
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
$vportDetLink = \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_vport', 'subtype' => 'acos_slb_vport_det']);

// Optionally scope this listing down to the ports belonging to a single
// Virtual Server, e.g. linked from that VS's "Ports" count.
$vsidFilter = (int) ($vars['vsid'] ?? 0);
$vsFilterUid = null;
if ($vsidFilter && isset($components[$vsidFilter]) && $components[$vsidFilter]['type'] == 'acos-slb-vs') {
    $vsFilterUid = $components[$vsidFilter]['UID'];
}

if ($vsFilterUid !== null) {
    echo '<div class="alert alert-info">Showing Virtual Services for <strong>' . htmlspecialchars((string) $components[$vsidFilter]['label']) . '</strong> only. <a href="' . \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_vport', 'subtype' => '', 'vsid' => '']) . '">Show all</a></div>';
}
?>
<table id='grid' data-toggle='bootgrid' class='table table-condensed table-responsive table-striped'>
    <thead>
    <tr>
        <th data-column-id="vportid" data-type="numeric" data-visible="false" data-searchable="false">vportid</th>
        <th data-column-id="vsname">Virtual Server</th>
        <th data-column-id="address">Address</th>
        <th data-column-id="port">Port</th>
        <th data-column-id="servicegroup">Service Group</th>
        <th data-column-id="status" data-visible="false">Status</th>
        <th data-column-id="message">Status</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ($components as $vportid => $array) {
        if ($array['type'] != 'acos-slb-vport') {
            continue;
        }
        if ($vsFilterUid !== null && ! str_starts_with((string) $array['UID'], (string) $vsFilterUid)) {
            continue;
        }

        if ($array['status'] != 0) {
            $message = $array['error'];
            $status = $array['status'];
        } else {
            $message = 'Ok';
            $status = '';
        }

        $sgName = $array['servicegroup'];
        if ($sgName !== null && $sgName !== '' && isset($poolIdByName[$sgName])) {
            $sgCell = '<a href="' . $poolLink . 'poolid=' . $poolIdByName[$sgName] . '" onclick="event.stopPropagation();">' . htmlspecialchars((string) $sgName) . '</a>';
        } else {
            $sgCell = htmlspecialchars((string) $sgName);
        }

        $vsName = $array['vsname'];
        if ($vsName !== null && $vsName !== '' && isset($vsIdByLabel[$vsName])) {
            $vsCell = '<a href="' . $vsLink . 'vsid=' . $vsIdByLabel[$vsName] . '" onclick="event.stopPropagation();">' . htmlspecialchars((string) $vsName) . '</a>';
        } else {
            $vsCell = htmlspecialchars((string) $vsName);
        } ?>
        <tr>
            <td><?php echo $vportid; ?></td>
            <td><?php echo $vsCell; ?></td>
            <td><?php echo $array['address']; ?></td>
            <td><?php echo $array['port']; ?></td>
            <td><?php echo $sgCell; ?></td>
            <td><?php echo $status; ?></td>
            <td><?php echo $message; ?></td>
        </tr>
        <?php
    } ?>
    </tbody>
</table>

<div class="panel panel-default" id="currconnections">
    <div class="panel-heading"><h3 class="panel-title">Total Current Connections (all Virtual Services)</h3></div>
    <div class="panel-body">
        <?php
        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['legend'] = 'no';
        $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
        $graph_array['type'] = 'device_acos_slb_allvport_curconns';
        require 'includes/html/print-graphrow.inc.php';
        ?>
    </div>
</div>
<div class="panel panel-default" id="bytesin">
    <div class="panel-heading"><h3 class="panel-title">Total Bytes In (all Virtual Services)</h3></div>
    <div class="panel-body">
        <?php
        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['legend'] = 'no';
        $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
        $graph_array['type'] = 'device_acos_slb_allvport_bytesin';
        require 'includes/html/print-graphrow.inc.php';
        ?>
    </div>
</div>
<div class="panel panel-default" id="bytesout">
    <div class="panel-heading"><h3 class="panel-title">Total Bytes Out (all Virtual Services)</h3></div>
    <div class="panel-body">
        <?php
        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['legend'] = 'no';
        $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
        $graph_array['type'] = 'device_acos_slb_allvport_bytesout';
        require 'includes/html/print-graphrow.inc.php';
        ?>
    </div>
</div>
<script type="text/javascript">
    $("#grid").bootgrid({
        caseSensitive: false,
        statusMappings: {
            2: "danger"
        },
    }).on("click.rs.jquery.bootgrid", function (e, columns, row) {
        var link = '<?php echo $vportDetLink; ?>vportid=' + row['vportid'];
        window.location.href = link;
    });
</script>
