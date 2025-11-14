<?php
/**
 * W3A11Y Artisan Database Schema
 * 
 * Creates WordPress database tables for storing plugin data including
 * inspiration suggestions and generation history.
 * 
 * @package W3A11Y_Artisan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * W3A11Y Artisan Database Schema Class
 */
class W3A11Y_Artisan_Database {
    
    /**
     * Plugin version for database migrations
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Create plugin tables
     * 
     * @since 1.0.0
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for storing AI inspiration suggestions
        $inspiration_table = $wpdb->prefix . 'w3a11y_artisan_inspiration';
        
        $sql_inspiration = "CREATE TABLE $inspiration_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            image_hash varchar(64) NOT NULL,
            suggestions longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY image_hash (image_hash)
        ) $charset_collate;";
        
        // Table for storing prompt history (for reuse)
        $history_table = $wpdb->prefix . 'w3a11y_artisan_history';
        
        $sql_history = "CREATE TABLE $history_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            operation_type enum('generate','edit') NOT NULL,
            prompt text NOT NULL,
            attachment_id bigint(20) unsigned DEFAULT NULL,
            image_hash varchar(64) DEFAULT NULL,
            operation_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY operation_type (operation_type),
            KEY attachment_id (attachment_id),
            KEY image_hash (image_hash),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Remove sessions table - not needed for simplified approach
        
        // Include WordPress upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        dbDelta($sql_inspiration);
        dbDelta($sql_history);
        
        // Update database version
        update_option('w3a11y_artisan_db_version', self::DB_VERSION);
        
        // Log table creation
        W3A11Y_Artisan::log('Database tables created successfully', 'info');
    }
    
    /**
     * Drop plugin tables (for uninstall)
     * 
     * @since 1.0.0
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'w3a11y_artisan_inspiration',
            $wpdb->prefix . 'w3a11y_artisan_history'
        ];
        
        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table drop for uninstall, table name cannot be parameterized
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('w3a11y_artisan_db_version');
    }
    
    /**
     * Check if database needs upgrade
     * 
     * @since 1.0.0
     */
    public static function maybe_upgrade() {
        $installed_version = get_option('w3a11y_artisan_db_version', '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Clean expired data
     * 
     * @since 1.0.0
     */
    public static function cleanup_expired_data() {
        global $wpdb;
        
        $current_time = current_time('mysql');
        
        // No expiration cleanup needed for inspiration table as inspiration never expires
        
        // Clean old history (keep last 100 prompts per user)
        $history_table = $wpdb->prefix . 'w3a11y_artisan_history';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cleanup query doesn't need caching, table names cannot be parameterized in MySQL
        $wpdb->query("
            DELETE h1 FROM $history_table h1
            INNER JOIN (
                SELECT user_id, id
                FROM $history_table h2
                WHERE (
                    SELECT COUNT(*)
                    FROM $history_table h3
                    WHERE h3.user_id = h2.user_id
                    AND h3.id >= h2.id
                ) > 100
            ) h4 ON h1.id = h4.id
        ");
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
    
    /**
     * Get inspiration for image
     * 
     * @param int $user_id WordPress user ID
     * @param string $image_hash Hash of the image
     * @return array|null Inspiration data or null if not found
     * @since 1.0.0
     */
    public static function get_inspiration($user_id, $image_hash) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'w3a11y_artisan_inspiration';
        $current_time = current_time('mysql');
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, using prepare() for user data, table names cannot be parameterized in MySQL
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d AND image_hash = %s
             ORDER BY created_at DESC LIMIT 1",
            $user_id,
            $image_hash
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        if ($result) {
            return [
                'id' => $result->id,
                'suggestions' => json_decode($result->suggestions, true),
                'created_at' => $result->created_at
            ];
        }
        
        return null;
    }
    
    /**
     * Save inspiration for image
     * 
     * @param int $user_id WordPress user ID
     * @param string $image_hash Hash of the image
     * @param array $suggestions Inspiration suggestions
     * @return int|false Insert ID or false on failure
     * @since 1.0.0
     */
    public static function save_inspiration($user_id, $image_hash, $suggestions) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'w3a11y_artisan_inspiration';
        
        // Delete existing inspiration for this image/user
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete operation
        $wpdb->delete($table, [
            'user_id' => $user_id,
            'image_hash' => $image_hash
        ]);
        
        // Insert new inspiration
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert
        $result = $wpdb->insert($table, [
            'user_id' => $user_id,
            'image_hash' => $image_hash,
            'suggestions' => json_encode($suggestions)
        ]);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Save prompt to history for reuse
     * 
     * @param int $user_id WordPress user ID
     * @param string $operation_type 'generate' or 'edit'
     * @param string $prompt User prompt
     * @param array|null $operation_data Additional operation data
     * @param int|null $attachment_id WordPress attachment ID (optional)
     * @param string|null $image_hash Hash of the image (optional)
     * @return int|false Insert ID or false on failure
     * @since 1.0.0
     */
    public static function save_prompt_history($user_id, $operation_type, $prompt, $operation_data = null, $attachment_id = null, $image_hash = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'w3a11y_artisan_history';
        
        // Build WHERE clause for checking existing prompt with image context
        $where_conditions = ["user_id = %d", "operation_type = %s", "prompt = %s"];
        $where_params = [$user_id, $operation_type, $prompt];
        
        if ($attachment_id) {
            $where_conditions[] = "attachment_id = %d";
            $where_params[] = $attachment_id;
        } else {
            $where_conditions[] = "attachment_id IS NULL";
        }
        
        if ($image_hash) {
            $where_conditions[] = "image_hash = %s";
            $where_params[] = $image_hash;
        } else {
            $where_conditions[] = "image_hash IS NULL";
        }
        
        // Build the WHERE clause string safely
        $where_clause = implode(" AND ", $where_conditions);
        
        // Check if this exact prompt already exists for this user and image (avoid duplicates)
        $query = "SELECT id FROM $table WHERE " . $where_clause;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, where_clause built from safe strings, using prepare() for user data
        $existing = $wpdb->get_row($wpdb->prepare($query, $where_params));
        
        if ($existing) {
            // Update the existing record's timestamp to make it "recent"
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
            $wpdb->update($table, [
                'created_at' => current_time('mysql')
            ], [
                'id' => $existing->id
            ]);
            return $existing->id;
        }
        
        // Insert new prompt
        $insert_data = [
            'user_id' => $user_id,
            'operation_type' => $operation_type,
            'prompt' => $prompt,
            'operation_data' => $operation_data ? json_encode($operation_data) : null
        ];
        
        if ($attachment_id) {
            $insert_data['attachment_id'] = $attachment_id;
        }
        
        if ($image_hash) {
            $insert_data['image_hash'] = $image_hash;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert
        $result = $wpdb->insert($table, $insert_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get prompt history for user
     * 
     * @param int $user_id WordPress user ID
     * @param string|null $operation_type Filter by 'generate' or 'edit' (optional)
     * @param int $limit Number of prompts to return (default 20)
     * @param int|null $attachment_id Filter by WordPress attachment ID (optional)
     * @param string|null $image_hash Filter by image hash (optional)
     * @return array Prompt history
     * @since 1.0.0
     */
    public static function get_prompt_history($user_id, $operation_type = null, $limit = 20, $attachment_id = null, $image_hash = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'w3a11y_artisan_history';
        
        $where_clause = "user_id = %d";
        $params = [$user_id];
        
        if ($operation_type) {
            $where_clause .= " AND operation_type = %s";
            $params[] = $operation_type;
        }
        
        // Filter by attachment ID
        if ($attachment_id) {
            $where_clause .= " AND attachment_id = %d";
            $params[] = $attachment_id;
        }
        
        // Filter by image hash
        if ($image_hash) {
            $where_clause .= " AND image_hash = %s";
            $params[] = $image_hash;
        }
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, where_clause built from safe strings, using prepare() for user data, table names and WHERE clauses cannot be parameterized in MySQL
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d",
            array_merge($params, [$limit])
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        
        return array_map(function($row) {
            return [
                'id' => $row->id,
                'operation_type' => $row->operation_type,
                'prompt' => $row->prompt,
                'attachment_id' => $row->attachment_id,
                'image_hash' => $row->image_hash,
                'operation_data' => $row->operation_data ? json_decode($row->operation_data, true) : null,
                'created_at' => $row->created_at
            ];
        }, $results);
    }
    
    /**
     * Update prompt history entries to add attachment_id for a specific image hash
     * 
     * This is used when a generated image is saved to WordPress Media Library
     * to link existing prompt history entries to the new attachment.
     * 
     * @param int $user_id WordPress user ID
     * @param string $image_hash Hash of the image
     * @param int $attachment_id WordPress attachment ID to set
     * @return int Number of rows updated
     * @since 1.0.0
     */
    public static function update_prompt_history_attachment_id($user_id, $image_hash, $attachment_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'w3a11y_artisan_history';
        
        // Update all prompt history entries for this user and image hash
        // where attachment_id is currently NULL (generated images)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
        $updated_rows = $wpdb->update(
            $table,
            [
                'attachment_id' => $attachment_id
            ],
            [
                'user_id' => $user_id,
                'image_hash' => $image_hash,
                'attachment_id' => null // Only update entries that don't already have an attachment_id
            ],
            ['%d'], // attachment_id format
            ['%d', '%s', null] // where clause formats
        );
        
        return $updated_rows !== false ? $updated_rows : 0;
    }
}