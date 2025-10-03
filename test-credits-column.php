<?php
/**
 * Test script to verify user credits column implementation
 * This file is for testing purposes only
 */

// Load WordPress
require_once(__DIR__ . '/../../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('You must be an administrator to run this test.');
}

echo "<h1>User Credits Column Test</h1>";

// Test 1: Check if hooks are registered
echo "<h2>Test 1: Verify hooks are registered</h2>";
$hooks_registered = [
    'manage_users_columns' => has_filter('manage_users_columns'),
    'manage_users_custom_column' => has_filter('manage_users_custom_column'),
    'admin_footer-users.php' => has_action('admin_footer-users.php'),
    'wp_ajax_wpspt_update_user_credits' => has_action('wp_ajax_wpspt_update_user_credits'),
];

foreach ($hooks_registered as $hook => $registered) {
    if ($registered) {
        echo "✓ Hook '$hook' is registered<br>";
    } else {
        echo "✗ Hook '$hook' is NOT registered<br>";
    }
}

// Test 2: Check if WPSPT_Admin class methods exist
echo "<h2>Test 2: Verify WPSPT_Admin methods exist</h2>";
$methods = [
    'add_user_credits_column',
    'display_user_credits_column',
    'add_user_credits_inline_edit',
    'ajax_update_user_credits'
];

foreach ($methods as $method) {
    if (method_exists('WPSPT_Admin', $method)) {
        echo "✓ Method 'WPSPT_Admin::$method' exists<br>";
    } else {
        echo "✗ Method 'WPSPT_Admin::$method' does NOT exist<br>";
    }
}

// Test 3: Test database credit operations
echo "<h2>Test 3: Test database credit operations</h2>";

// Get a test user
$test_user = get_users(['number' => 1, 'role__in' => ['administrator', 'wpcustomer']]);
if (!empty($test_user)) {
    $user_id = $test_user[0]->ID;
    echo "Using test user: " . esc_html($test_user[0]->display_name) . " (ID: $user_id)<br>";

    // Get current credits
    $current_credits = WPSPT_Database::get_user_credits($user_id);
    echo "Current credits: $current_credits<br>";

    // Update credits
    $test_credits = 99;
    WPSPT_Database::update_user_credits($user_id, $test_credits);
    echo "Updated credits to: $test_credits<br>";

    // Verify update
    $new_credits = WPSPT_Database::get_user_credits($user_id);
    if ($new_credits == $test_credits) {
        echo "✓ Credits update successful (verified: $new_credits)<br>";
    } else {
        echo "✗ Credits update failed (expected: $test_credits, got: $new_credits)<br>";
    }

    // Restore original credits
    WPSPT_Database::update_user_credits($user_id, $current_credits);
    echo "Restored original credits: $current_credits<br>";
} else {
    echo "No test user found<br>";
}

// Test 4: Test column display
echo "<h2>Test 4: Test column display function</h2>";
if (!empty($test_user)) {
    $user_id = $test_user[0]->ID;
    $credits = WPSPT_Database::get_user_credits($user_id);

    $column_output = WPSPT_Admin::display_user_credits_column('', 'wpspt_credits', $user_id);

    if (strpos($column_output, 'wpspt-user-credits') !== false &&
        strpos($column_output, 'wpspt-edit-user-credits') !== false) {
        echo "✓ Column display function works correctly<br>";
        echo "Output: " . esc_html($column_output) . "<br>";
    } else {
        echo "✗ Column display function failed<br>";
    }
}

// Test 5: Test add_user_credits_column
echo "<h2>Test 5: Test add_user_credits_column function</h2>";
$test_columns = ['username' => 'Username', 'email' => 'Email'];
$updated_columns = WPSPT_Admin::add_user_credits_column($test_columns);

if (isset($updated_columns['wpspt_credits'])) {
    echo "✓ Credits column added successfully<br>";
    echo "Column name: " . esc_html($updated_columns['wpspt_credits']) . "<br>";
} else {
    echo "✗ Credits column was not added<br>";
}

echo "<h2>All Tests Completed</h2>";
echo "<p><strong>To see the feature in action:</strong> Go to Users > All Users in your WordPress admin panel.</p>";
echo "<p>You should see a 'Support Credits' column with Edit buttons for each user.</p>";
