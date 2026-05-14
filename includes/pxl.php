<?php
/**
 * PXL Currency Management
 */

function credit_pxl(PDO $pdo, int $user_id, int $amount, string $type, string $ref = '', string $desc = ''): int {
    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance + ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?");
    $stmt->execute([$amount, $amount, $user_id]);

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, $amount, $type, $ref, $new_balance, $desc]);

    return $new_balance;
}

function debit_pxl(PDO $pdo, int $user_id, int $amount, string $type, string $ref = '', string $desc = ''): int {
    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance - ?, total_pxl_spent = total_pxl_spent + ? WHERE id = ? AND pxl_balance >= ?");
    $stmt->execute([$amount, $amount, $user_id, $amount]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Insufficient balance');
    }

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, -$amount, $type, $ref, $new_balance, $desc]);

    return $new_balance;
}

function calculate_pxl_from_score(int $score): int {
    return (int)floor($score / 200);
}

function get_streak_bonus(int $streak_days): int {
    return match(true) {
        $streak_days >= 30 => 50,
        $streak_days >= 14 => 25,
        $streak_days >= 7 => 15,
        $streak_days >= 5 => 8,
        $streak_days >= 3 => 5,
        $streak_days >= 2 => 3,
        $streak_days >= 1 => 2,
        default => 0
    };
}

function get_combo_bonus(string $combo_level): int {
    return match($combo_level) {
        'MAX' => 10,
        '3x' => 5,
        '2x' => 2,
        '1.5x' => 1,
        default => 0
    };
}

function has_daily_bonus(Redis $redis, int $user_id, string $date): bool {
    return (bool)$redis->get("daily_bonus:{$user_id}:{$date}");
}

function has_daily_game(Redis $redis, int $user_id, string $date): bool {
    return (bool)$redis->get("daily_game:{$user_id}:{$date}");
}

function set_daily_bonus(Redis $redis, int $user_id, string $date): void {
    $redis->setex("daily_bonus:{$user_id}:{$date}", 86400, '1');
}

function set_daily_game(Redis $redis, int $user_id, string $date): void {
    $redis->setex("daily_game:{$user_id}:{$date}", 86400, '1');
}