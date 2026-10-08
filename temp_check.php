<?php
$lines = file("app/schema.sql");
for ($i = 300; $i < 340; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i];
    }
}
echo "====================================\n";
for ($i = 550; $i < 600; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ": " . $lines[$i];
    }
}
