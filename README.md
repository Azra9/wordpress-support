# WP Support Pro - Credit-Based Support Ticket System

A comprehensive WordPress plugin that enables users to create support tickets from their account, with admin viewing and management capabilities.

## Features

- **Credit-Based Ticket System**: Users consume credits when creating tickets
- **Client Dashboard**: Frontend dashboard where users can create and view tickets
- **Admin Management**: Complete admin interface to view, manage, and respond to tickets
- **Ticket Conversations**: Two-way communication between clients and admins
- **Secure Credentials Storage**: Encrypted storage for website login credentials
- **Custom Ticket Types**: Configurable ticket types with different credit costs
- **Ticket Statuses**: Open, In Progress, Resolved, and Closed statuses
- **User Role Management**: Custom 'wpcustomer' role for clients

## Installation

1. Upload the plugin folder to `/wp-content/plugins/wordpress-support/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically:
   - Create custom database tables
   - Register the 'wpcustomer' role
   - Set up encryption keys
   - Create default ticket types

## Setup

### 1. Configure Ticket Types

1. Go to **Support Tickets > Settings** in the WordPress admin
2. Configure ticket types with their IDs, labels, and credit costs
3. Default types are:
   - Small Fix - 1 Credit
   - Theme Setup - 3 Credits

### 2. Manage User Credits

1. Go to **Support Tickets > User Credits** in the WordPress admin
2. View all users with the 'wpcustomer' role
3. Edit credits for any user by clicking "Edit Credits"

### 3. Create Client Dashboard Page

1. Create a new page (e.g., "Support Dashboard")
2. Add the shortcode: `[wpspt_dashboard]`
3. Publish the page
4. Direct your clients to this page to create and manage tickets

### 4. Assign Users to WP Customer Role

1. Go to **Users** in WordPress admin
2. Edit a user or create a new one
3. Set the role to "WP Customer"
4. Assign credits to the user via **Support Tickets > User Credits**

## Usage

### For Clients (Frontend)

1. **Access Dashboard**: Navigate to the page with `[wpspt_dashboard]` shortcode
2. **View Credits**: See available credits in the header
3. **Create Ticket**:
   - Click "New Ticket" tab
   - Fill in ticket title and description
   - Select ticket type (different types cost different credits)
   - Optionally provide website credentials (encrypted)
   - Submit the ticket
4. **View Tickets**: See all tickets in the "My Tickets" tab
5. **Track Status**: Monitor ticket status (Open, In Progress, Resolved, Closed)

### For Admins (Backend)

1. **View All Tickets**: Go to **Support Tickets** in WordPress admin
2. **Ticket List Features**:
   - See ticket ID, title, client, type, status, and date
   - Custom columns for easy management
   - Filter by status
3. **View Ticket Details**: Click on any ticket to see:
   - Client information and available credits
   - Ticket type and description
   - Full conversation history
   - Encrypted website credentials (if provided)
   - Status management
4. **Reply to Tickets**:
   - Scroll to "Ticket Conversation" metabox
   - Type your reply in the text area
   - Click "Send Reply"
   - Reply is marked as admin response
5. **Update Status**:
   - Use the "Ticket Status" metabox
   - Select new status from dropdown
   - Save the post to update
6. **View Credentials**:
   - Check "Website Credentials" metabox
   - Click "Show Password" to reveal encrypted password
   - All credentials are AES-256 encrypted

## Database Tables

The plugin creates three custom tables:

1. **wp_wpspt_conversations**: Stores ticket messages and replies
2. **wp_wpspt_credentials**: Stores encrypted website credentials
3. **wp_wpspt_credits**: Tracks user credit balances

## Security Features

- **Nonce Verification**: All AJAX requests are protected with WordPress nonces
- **Capability Checks**: Proper permission checks for all operations
- **Data Sanitization**: All user inputs are sanitized and validated
- **AES-256 Encryption**: Website credentials are encrypted using OpenSSL
- **Unique Encryption Key**: Generated on activation and stored securely

## Shortcodes

### [wpspt_dashboard]

Displays the complete client dashboard with:
- Credit balance display
- Ticket list with filtering
- New ticket creation form
- Ticket conversation interface

**Usage**: `[wpspt_dashboard]`

**Requirements**: User must be logged in and have 'wpcustomer' role or be an administrator

## Hooks and Filters

The plugin uses standard WordPress hooks and actions. Developers can extend functionality by:

- Adding custom ticket types in Settings
- Modifying ticket statuses via `WPSPT_CPT_Ticket::get_statuses()`
- Extending database functionality in `WPSPT_Database` class

## File Structure

```
wordpress-support/
├── wp-support-ticket-system.php    # Main plugin file
├── includes/
│   ├── class-loader.php            # Class autoloader
│   ├── admin/
│   │   └── class-admin.php         # Admin interface
│   ├── client/
│   │   └── class-client-dashboard.php  # Client dashboard
│   └── core/
│       ├── class-database.php      # Database operations
│       ├── class-cpt-ticket.php    # Custom post type
│       └── class-encryption.php    # Encryption utilities
├── assets/
│   ├── admin.js                    # Admin JavaScript
│   └── admin.css                   # Admin styles
└── README.md                       # This file
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher
- OpenSSL PHP extension (for encryption)

## Credits System

- Admins can add/remove credits from users
- Credits are deducted when a ticket is created
- Different ticket types can have different credit costs
- Users cannot create tickets without sufficient credits

## Support and Development

This plugin is actively maintained and developed. For issues, suggestions, or contributions, please contact the development team.

## License

This plugin is proprietary software. All rights reserved.

## Version History

### 0.1.0
- Initial release
- Credit-based ticket system
- Client dashboard with ticket creation
- Admin ticket management interface
- Encrypted credentials storage
- Ticket conversations
- User credits management
