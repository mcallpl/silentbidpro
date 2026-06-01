<?php
// ============================================================
// DATABASE HELPER FUNCTIONS
// MySQLi wrapper functions for common database operations
// Follows DinkConnections pattern: type-string auto-detection
// ============================================================

/**
 * Build type string for bind_param based on parameter types
 * @param array $params Array of parameters
 * @return string Type string (i/d/s)
 */
function buildTypeString($params) {
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    return $types;
}

/**
 * Execute a query and return the statement
 * @param string $sql SQL query with ? placeholders
 * @param array $params Parameters to bind
 * @return mysqli_stmt|false
 */
function dbQuery($sql, $params = []) {
    $conn = getDB();
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error . " SQL: " . $sql);
        return false;
    }

    if (!empty($params)) {
        $types = buildTypeString($params);
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error . " SQL: " . $sql);
        $stmt->close();
        return false;
    }

    return $stmt;
}

/**
 * Fetch a single row as associative array
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array|null
 */
function dbGetRow($sql, $params = []) {
    $stmt = dbQuery($sql, $params);

    if (!$stmt) {
        return null;
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row;
}

/**
 * Fetch all rows as array of associative arrays
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array
 */
function dbGetAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);

    if (!$stmt) {
        return [];
    }

    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

/**
 * Execute INSERT query and return insert_id
 * @param string $sql INSERT query
 * @param array $params Parameters to bind
 * @return int|false Insert ID or false on failure
 */
function dbInsert($sql, $params = []) {
    $conn = getDB();
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Insert preparation failed: " . $conn->error . " SQL: " . $sql);
        return false;
    }

    if (!empty($params)) {
        $types = buildTypeString($params);
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("Insert execution failed: " . $stmt->error . " SQL: " . $sql);
        $stmt->close();
        return false;
    }

    $insertId = $conn->insert_id;
    $stmt->close();

    return $insertId;
}

/**
 * Execute UPDATE query and return affected rows count
 * @param string $sql UPDATE query
 * @param array $params Parameters to bind
 * @return int|false Affected rows or false on failure
 */
function dbUpdate($sql, $params = []) {
    $conn = getDB();
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("❌ [DB UPDATE] Preparation failed: " . $conn->error . " SQL: " . $sql);
        return false;
    }

    if (!empty($params)) {
        $types = buildTypeString($params);
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("❌ [DB UPDATE] Bind failed: " . $stmt->error . " Types: " . $types . " SQL: " . $sql);
            $stmt->close();
            return false;
        }
    }

    if (!$stmt->execute()) {
        error_log("❌ [DB UPDATE] Execution failed: " . $stmt->error . " SQL: " . $sql);
        $stmt->close();
        return false;
    }

    $affectedRows = $stmt->affected_rows;
    error_log("✓ [DB UPDATE] Success - Affected rows: " . $affectedRows . " SQL: " . $sql);
    $stmt->close();

    // Return true on successful execution (statement executed without error)
    return true;
}

/**
 * Execute DELETE query and return affected rows count
 * @param string $sql DELETE query
 * @param array $params Parameters to bind
 * @return int|false Affected rows or false on failure
 */
function dbDelete($sql, $params = []) {
    return dbUpdate($sql, $params);
}

/**
 * Fetch a single value (e.g., COUNT, SUM, etc.)
 * @param string $sql SQL query with single column result
 * @param array $params Parameters to bind
 * @return string|int|null
 */
function dbGetValue($sql, $params = []) {
    $row = dbGetRow($sql, $params);
    if (!$row) {
        return null;
    }
    // Return first column value
    return array_values($row)[0] ?? null;
}

/**
 * Check if a record exists
 * @param string $table Table name
 * @param string $where WHERE clause with ? placeholders
 * @param array $params Parameters to bind
 * @return bool
 */
function dbExists($table, $where, $params = []) {
    $sql = "SELECT 1 FROM " . $table . " WHERE " . $where . " LIMIT 1";
    return dbGetRow($sql, $params) !== null;
}

/**
 * Count records with conditions
 * @param string $table Table name
 * @param string $where Optional WHERE clause
 * @param array $params Optional parameters
 * @return int
 */
function dbCount($table, $where = '', $params = []) {
    $sql = "SELECT COUNT(*) as count FROM " . $table;
    if (!empty($where)) {
        $sql .= " WHERE " . $where;
    }
    $count = dbGetValue($sql, $params);
    return (int)($count ?? 0);
}

