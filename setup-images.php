<?php
// Create images directory and copy logo
if (!file_exists('images')) {
    mkdir('images', 0755, true);
    echo "Created images directory\n";
}

if (file_exists('assets/images/logo.png')) {
    copy('assets/images/logo.png', 'images/logo.png');
    echo "Copied logo to images/logo.png\n";
} else {
    echo "Source logo not found at assets/images/logo.png\n";
}

echo "Setup complete!\n";
?>
