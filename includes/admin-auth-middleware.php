<?php
// ============================================================
// ADMIN AUTHORIZATION MIDDLEWARE
// Role-based access control for multi-tenant admin system
// ============================================================

require_once __DIR__ . '/db-helpers.php';

/**
 * Check if admin has access to an organization
 * @param int $admin_id Admin ID
 * @param int $org_id Organization ID
 * @return array|false {id, admin_id, organization_id, role} or false
 */
function checkAdminOrgAccess($admin_id, $org_id) {
    $admin = dbGetRow("SELECT is_super_admin FROM admin_accounts WHERE id = ?", [(int)$admin_id]);

    if (!$admin) {
        return false;
    }

    // Super admin has access to all orgs
    if ($admin['is_super_admin']) {
        return ['role' => 'manager'];
    }

    // Check admin_organizations bridge table
    return dbGetRow(
        "SELECT id, admin_id, organization_id, role FROM admin_organizations
         WHERE admin_id = ? AND organization_id = ? AND is_active IS NOT FALSE",
        [(int)$admin_id, (int)$org_id]
    );
}

/**
 * Check if admin has access to an event
 * @param int $admin_id Admin ID
 * @param int $event_id Event ID
 * @return array|false {id, admin_id, event_id, role} or false
 */
function checkAdminEventAccess($admin_id, $event_id) {
    $admin = dbGetRow("SELECT is_super_admin FROM admin_accounts WHERE id = ?", [(int)$admin_id]);

    if (!$admin) {
        return false;
    }

    // Super admin has access to all events
    if ($admin['is_super_admin']) {
        return ['role' => 'manager'];
    }

    // Check admin_events bridge table
    return dbGetRow(
        "SELECT id, admin_id, event_id, role FROM admin_events
         WHERE admin_id = ? AND event_id = ?",
        [(int)$admin_id, (int)$event_id]
    );
}

/**
 * Get all organizations accessible by an admin
 * @param int $admin_id Admin ID
 * @return array List of {id, name, slug, role}
 */
function getAdminAccessibleOrganizations($admin_id) {
    $admin = dbGetRow("SELECT is_super_admin FROM admin_accounts WHERE id = ?", [(int)$admin_id]);

    if (!$admin) {
        return [];
    }

    // Super admin can access all organizations
    if ($admin['is_super_admin']) {
        return dbGetAll(
            "SELECT id, name, slug, 'manager' as role FROM organizations ORDER BY name"
        );
    }

    // Regular admins: get organizations from bridge table
    return dbGetAll(
        "SELECT o.id, o.name, o.slug, ao.role
         FROM organizations o
         INNER JOIN admin_organizations ao ON ao.organization_id = o.id
         WHERE ao.admin_id = ?
         ORDER BY o.name",
        [(int)$admin_id]
    );
}

/**
 * Get all events accessible by an admin, optionally filtered by organization
 * @param int $admin_id Admin ID
 * @param int|null $org_id Optional: filter by organization
 * @return array List of {id, name, event_date, organization_id, role}
 */
function getAdminAccessibleEvents($admin_id, $org_id = null) {
    $admin = dbGetRow("SELECT is_super_admin FROM admin_accounts WHERE id = ?", [(int)$admin_id]);

    if (!$admin) {
        return [];
    }

    $sql = "SELECT e.id, e.name, e.event_date, e.organization_id, COALESCE(ae.role, 'manager') as role
            FROM events e
            LEFT JOIN admin_events ae ON ae.event_id = e.id AND ae.admin_id = ?";
    $params = [(int)$admin_id];

    // Super admin can access all events
    if (!$admin['is_super_admin']) {
        $sql .= " WHERE ae.admin_id = ?";
        $params[] = (int)$admin_id;
    }

    // Filter by organization if specified
    if ($org_id) {
        $sql .= " AND e.organization_id = ?";
        $params[] = (int)$org_id;
    }

    $sql .= " ORDER BY e.name";

    return dbGetAll($sql, $params);
}

/**
 * Require admin has org access with manager role (or is super admin)
 * Returns admin object if authorized, dies with 403 if not
 * @param int $org_id Organization ID
 * @return array Admin record {id, username, email, full_name, is_super_admin, ...}
 */
function requireAdminOrgAccess($org_id) {
    $admin = getCurrentAdmin();

    if (!$admin) {
        http_response_code(401);
        die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
    }

    // Super admin has access to everything
    if ($admin['is_super_admin']) {
        return $admin;
    }

    // Check organization access
    $access = checkAdminOrgAccess($admin['id'], $org_id);

    if (!$access || $access['role'] !== 'manager') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
    }

    return $admin;
}

/**
 * Require admin has event access with manager role (or is super admin)
 * Returns admin object if authorized, dies with 403 if not
 * @param int $event_id Event ID
 * @return array Admin record {id, username, email, full_name, is_super_admin, ...}
 */
function requireAdminEventAccess($event_id) {
    $admin = getCurrentAdmin();

    if (!$admin) {
        http_response_code(401);
        die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
    }

    // Super admin has access to everything
    if ($admin['is_super_admin']) {
        return $admin;
    }

    // Check event access
    $access = checkAdminEventAccess($admin['id'], $event_id);

    if (!$access || $access['role'] !== 'manager') {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Forbidden']));
    }

    return $admin;
}

/**
 * Require admin is super admin only
 * Returns admin object if authorized, dies with 403 if not
 * @return array Admin record {id, username, email, full_name, is_super_admin, ...}
 */
function requireSuperAdmin() {
    $admin = getCurrentAdmin();

    if (!$admin) {
        http_response_code(401);
        die(json_encode(['status' => 'error', 'message' => 'Not authenticated']));
    }

    if (!$admin['is_super_admin']) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Super admin access required']));
    }

    return $admin;
}

/**
 * Get current authenticated admin
 * @return array|false Admin record or false if not authenticated
 */
function getCurrentAdmin() {
    $token = getAdminSessionToken();

    if (!$token) {
        return false;
    }

    return dbGetRow(
        "SELECT id, username, email, full_name, is_super_admin, is_active, organization_id, last_login, created_at
         FROM admin_accounts
         WHERE admin_session_token = ? AND is_active = 1",
        [$token]
    );
}

/**
 * Get admin session token from cookie or header
 * @return string|false Token or false if not found
 */
function getAdminSessionToken() {
    $cookie_name = defined('ADMIN_SESSION_COOKIE_NAME') ? ADMIN_SESSION_COOKIE_NAME : 'admin_session_token';

    // Check cookie first
    if (!empty($_COOKIE[$cookie_name])) {
        return $_COOKIE[$cookie_name];
    }

    // Check Authorization header (Bearer token)
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
        return $matches[1];
    }

    return false;
}
