<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Lists every Service Group (Pool) - one row each, click a row to see its
 * Service Group Members plus graphs scoped to that one pool.
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

$memberCounts = [];
foreach ($components as $array) {
    if ($array['type'] != 'acos-slb-member') {
        continue;
    }
    foreach ($components as $pool) {
        if ($pool['type'] == 'acos-slb-pool' && str_starts_with((string) $array['UID'], (string) $pool['UID'])) {
            $memberCounts[$pool['UID']] = ($memberCounts[$pool['UID']] ?? 0) + 1;
        }
    }
}
?>
<table id='grid' data-toggle='bootgrid' class='table table-condensed table-responsive table-striped'>
    <thead>
    <tr>
        <th data-column-id="poolid" data-type="numeric" data-visible="false" data-searchable="false">poolid</th>
        <th data-column-id="name">Service Group</th>
        <th data-column-id="members" data-type="numeric">Members</th>
        <th data-column-id="status" data-visible="false">Status</th>
        <th data-column-id="message">Status</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ($components as $poolid => $array) {
        if ($array['type'] != 'acos-slb-pool') {
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
            <td><?php echo $poolid; ?></td>
            <td><?php echo $array['label']; ?></td>
            <td><?php echo $memberCounts[$array['UID']] ?? 0; ?></td>
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
        var link = '<?php echo \LibreNMS\Util\Url::generate($vars, ['type' => 'acos_slb_pool', 'subtype' => 'acos_slb_pool_det']); ?>poolid=' + row['poolid'];
        window.location.href = link;
    });
</script>
