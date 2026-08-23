<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
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

// One row per top-level axVirtualServer (VIP), matching the device's own
// "Virtual Servers" count. Per-port/service detail lives on the separate
// "Virtual Services" tab (acos-slb-vport), same split the A10 GUI itself uses.
$portCounts = [];
foreach ($components as $array) {
    if ($array['type'] != 'acos-slb-vport') {
        continue;
    }
    foreach ($components as $vs) {
        if ($vs['type'] == 'acos-slb-vs' && str_starts_with((string) $array['UID'], (string) $vs['UID'])) {
            $portCounts[$vs['UID']] = ($portCounts[$vs['UID']] ?? 0) + 1;
        }
    }
}
$vportLink = \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_vport', 'subtype' => '']);
?>
<table id='grid' data-toggle='bootgrid' class='table table-condensed table-responsive table-striped'>
    <thead>
    <tr>
        <th data-column-id="vsid" data-type="numeric" data-visible="false" data-searchable="false">vsid</th>
        <th data-column-id="name">Virtual Server</th>
        <th data-column-id="address">Address</th>
        <th data-column-id="ports" data-type="numeric">Ports</th>
        <th data-column-id="status" data-visible="false">Status</th>
        <th data-column-id="message">Status</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ($components as $vsid => $array) {
        if ($array['type'] != 'acos-slb-vs') {
            continue;
        }

        if ($array['status'] != 0) {
            $message = $array['error'];
            $status = $array['status'];
        } else {
            $message = 'Ok';
            $status = '';
        } ?>
        <tr>
            <td><?php echo $vsid; ?></td>
            <td><?php echo $array['label']; ?></td>
            <td><?php echo $array['address']; ?></td>
            <td><a href="<?php echo $vportLink; ?>vsid=<?php echo $vsid; ?>" onclick="event.stopPropagation();"><?php echo $portCounts[$array['UID']] ?? 0; ?></a></td>
            <td><?php echo $status; ?></td>
            <td><?php echo $message; ?></td>
        </tr>
        <?php
    } ?>
    </tbody>
</table>

<div class="panel panel-default" id="currconnections">
    <div class="panel-heading"><h3 class="panel-title">Total Current Connections (all Virtual Servers)</h3></div>
    <div class="panel-body">
        <?php
        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['legend'] = 'no';
        $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
        $graph_array['type'] = 'device_acos_slb_allvs_curconns';
        require 'includes/html/print-graphrow.inc.php';
        ?>
    </div>
</div>
<div class="panel panel-default" id="bytesin">
    <div class="panel-heading"><h3 class="panel-title">Total Bytes In (all Virtual Servers)</h3></div>
    <div class="panel-body">
        <?php
        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['legend'] = 'no';
        $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
        $graph_array['type'] = 'device_acos_slb_allvs_bytesin';
        require 'includes/html/print-graphrow.inc.php';
        ?>
    </div>
</div>
<div class="panel panel-default" id="bytesout">
    <div class="panel-heading"><h3 class="panel-title">Total Bytes Out (all Virtual Servers)</h3></div>
    <div class="panel-body">
        <?php
        $graph_array = [];
        $graph_array['device'] = $device['device_id'];
        $graph_array['height'] = '100';
        $graph_array['width'] = '215';
        $graph_array['legend'] = 'no';
        $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
        $graph_array['type'] = 'device_acos_slb_allvs_bytesout';
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
        var link = '<?php echo \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_vs', 'subtype' => 'acos_slb_vs_det']); ?>vsid=' + row['vsid'];
        window.location.href = link;
    });
</script>
