<?php
require __DIR__ . '/dist/config/config.php';

function checkAndAddColumn($conn, $table) {
    echo "Checking table: $table\n";
    $res = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'informasi_barang'");
    if (mysqli_num_rows($res) === 0) {
        echo "Adding column 'informasi_barang' to $table...\n";
        $sql = "ALTER TABLE $table ADD COLUMN informasi_barang ENUM('Baru', 'Second') DEFAULT 'Baru' AFTER kondisi";
        if (mysqli_query($conn, $sql)) {
            echo "Successfully added column to $table.\n";
        } else {
            echo "Error adding column to $table: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Column 'informasi_barang' already exists in $table.\n";
    }
}

checkAndAddColumn($conn, 'asset_dfm');
checkAndAddColumn($conn, 'asset_ga');
