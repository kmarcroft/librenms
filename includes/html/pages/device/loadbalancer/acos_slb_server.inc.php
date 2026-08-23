<?php
/*
 * LibreNMS module to Display data from A10 ACOS SLB Devices
 *
 * Router for the "Real Servers" tab - dispatches to the
 * listing page by default, or to the single-server detail page when a row
 * has been clicked (subtype=acos_slb_server_det&serverid=...).
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

$subtype = basename((string) $vars['subtype']);
if ($subtype != '' && is_file("includes/html/pages/device/loadbalancer/$subtype.inc.php")) {
    include "includes/html/pages/device/loadbalancer/$subtype.inc.php";
} else {
    include 'includes/html/pages/device/loadbalancer/acos_slb_server_all.inc.php';
}
