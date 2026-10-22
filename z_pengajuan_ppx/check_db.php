<?php
require_once __DIR__ . '/../dist/config/config.php';

$error = [];

$q1 = "SELECT pr.request_type FROM ppx_request pr LIMIT 1";
$r1 = mysqli_query($conn, $q1);
if (!$r1) $error[] = "ppx_request: " . mysqli_error($conn);

$q2 = "SELECT par.request_type FROM ppx_approval_routing par LIMIT 1";
$r2 = mysqli_query($conn, $q2);
if (!$r2) $error[] = "ppx_approval_routing: " . mysqli_error($conn);

echo json_encode(['errors' => $error]);
?>
