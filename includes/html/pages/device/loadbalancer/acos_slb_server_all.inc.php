<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Lists Real Servers (nodes) discovered via axServerTable/axServerStatTable -
 * one row each, click a row to see its detail page + graphs.
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
?>
<table id='grid' data-toggle='bootgrid' class='table table-condensed table-responsive table-striped'>
    <thead>
    <tr>
        <th data-column-id="serverid" data-type="numeric" data-visible="false" data-searchable="false">serverid</th>
        <th data-column-id="name">Real Server</th>
        <th data-column-id="address">Address</th>
        <th data-column-id="healthmonitor">Health Monitor</th>
        <th data-column-id="weight">Weight</th>
        <th data-column-id="status" data-visible="false">Status</th>
        <th data-column-id="message">Status</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ($components as $serverid => $array) {
        if ($array['type'] != 'acos-slb-server') {
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
            <td><?php echo $serverid; ?></td>
            <td><?php echo $array['label']; ?></td>
            <td><?php echo $array['address']; ?></td>
            <td><?php echo $array['healthmonitor']; ?></td>
            <td><?php echo $array['weight']; ?></td>
            <td><?php echo $status; ?></td>
            <td><?php echo $message; ?></td>
        </tr>
        <?php
    } ?>
    </tbody>
</table>
<script type="text/javascript">
    $("#grid").bootgrid({
        caseSensitive: false,
        statusMappings: {
            2: "danger"
        },
    }).on("click.rs.jquery.bootgrid", function (e, columns, row) {
        var link = '<?php echo \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_server', 'subtype' => 'acos_slb_server_det']); ?>serverid=' + row['serverid'];
        window.location.href = link;
    });
</script>
