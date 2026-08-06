<?php

/**
 * Creates all tables (idempotent) and, on first run, seeds default
 * subscription plans, a super admin account, and a demo company with
 * sample data so the platform is immediately explorable.
 *
 * Usage: php database/migrate.php [--seed-demo]
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();
$driver = Database::driver();

$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
$ts = $driver === 'sqlite' ? "TEXT DEFAULT (datetime('now'))" : 'DATETIME DEFAULT CURRENT_TIMESTAMP';
$engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

$statements = [];

$statements[] = "CREATE TABLE IF NOT EXISTS plans (
    id {$id},
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    tagline VARCHAR(255),
    price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    max_users INT NOT NULL DEFAULT 5,
    max_projects INT NOT NULL DEFAULT 10,
    features TEXT,
    is_active INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS companies (
    id {$id},
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    city VARCHAR(100),
    cr_number VARCHAR(50),
    vat_number VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'trial',
    plan_id INT,
    trial_ends_at TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS users (
    id {$id},
    company_id INT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'owner',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS subscriptions (
    id {$id},
    company_id INT NOT NULL,
    plan_id INT NOT NULL,
    billing_cycle VARCHAR(10) NOT NULL DEFAULT 'monthly',
    status VARCHAR(20) NOT NULL DEFAULT 'trialing',
    current_period_end TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS payments (
    id {$id},
    company_id INT NOT NULL,
    subscription_id INT,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
    method VARCHAR(30) NOT NULL DEFAULT 'manual',
    reference VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS clients (
    id {$id},
    company_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address VARCHAR(255),
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS projects (
    id {$id},
    company_id INT NOT NULL,
    client_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'planning',
    budget DECIMAL(12,2) NOT NULL DEFAULT 0,
    start_date TEXT,
    end_date TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS estimates (
    id {$id},
    company_id INT NOT NULL,
    project_id INT,
    client_id INT,
    title VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS estimate_items (
    id {$id},
    estimate_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS invoices (
    id {$id},
    company_id INT NOT NULL,
    project_id INT,
    client_id INT,
    invoice_number VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date TEXT,
    created_at {$ts}
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS invoice_items (
    id {$id},
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0
){$engine}";

$statements[] = "CREATE TABLE IF NOT EXISTS schedule_tasks (
    id {$id},
    company_id INT NOT NULL,
    project_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    start_date TEXT,
    end_date TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    assigned_to INT,
    created_at {$ts}
){$engine}";

foreach ($statements as $sql) {
    $pdo->exec($sql);
}

echo "Tables created/verified using driver: {$driver}\n";

// ---- Seed default plans (idempotent by slug) ----
$defaultPlans = [
    [
        'slug' => 'starter',
        'name' => 'Starter',
        'tagline' => 'For small contractors getting organized',
        'price_monthly' => 199,
        'price_yearly' => 1990,
        'max_users' => 3,
        'max_projects' => 10,
        'features' => json_encode([
            'Unlimited estimates & quotes',
            'Up to 10 active projects',
            '3 team members',
            'Client & invoice management',
            'Email support',
        ]),
        'sort_order' => 1,
    ],
    [
        'slug' => 'professional',
        'name' => 'Professional',
        'tagline' => 'For growing contracting businesses',
        'price_monthly' => 449,
        'price_yearly' => 4490,
        'max_users' => 15,
        'max_projects' => 50,
        'features' => json_encode([
            'Everything in Starter',
            'Up to 50 active projects',
            '15 team members',
            'Job costing & budget tracking',
            'Project scheduling',
            'Priority support',
        ]),
        'sort_order' => 2,
    ],
    [
        'slug' => 'enterprise',
        'name' => 'Enterprise',
        'tagline' => 'For large contractors & developers',
        'price_monthly' => 899,
        'price_yearly' => 8990,
        'max_users' => 999,
        'max_projects' => 999,
        'features' => json_encode([
            'Everything in Professional',
            'Unlimited projects & users',
            'Multi-branch support',
            'Dedicated account manager',
            'ZATCA-ready VAT invoicing',
            'Custom onboarding',
        ]),
        'sort_order' => 3,
    ],
];

$checkPlan = $pdo->prepare('SELECT id FROM plans WHERE slug = ?');
$insertPlan = $pdo->prepare('INSERT INTO plans (slug, name, tagline, price_monthly, price_yearly, currency, max_users, max_projects, features, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');

foreach ($defaultPlans as $p) {
    $checkPlan->execute([$p['slug']]);
    if ($checkPlan->fetch()) {
        continue;
    }
    $insertPlan->execute([
        $p['slug'], $p['name'], $p['tagline'], $p['price_monthly'], $p['price_yearly'],
        'SAR', $p['max_users'], $p['max_projects'], $p['features'], $p['sort_order'],
    ]);
}
echo "Default plans seeded.\n";

// ---- Seed super admin account ----
$checkUser = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$checkUser->execute(['admin@buildxact-saudi.local']);
if (!$checkUser->fetch()) {
    $pdo->prepare('INSERT INTO users (company_id, name, email, password_hash, role, status) VALUES (NULL, ?, ?, ?, ?, ?)')
        ->execute(['Platform Admin', 'admin@buildxact-saudi.local', password_hash('Admin@12345', PASSWORD_DEFAULT), 'super_admin', 'active']);
    echo "Super admin seeded: admin@buildxact-saudi.local / Admin@12345\n";
} else {
    echo "Super admin already exists.\n";
}

// ---- Optional demo company with sample data ----
if (in_array('--seed-demo', $argv, true)) {
    $checkCompany = $pdo->prepare('SELECT id FROM companies WHERE email = ?');
    $checkCompany->execute(['demo@buildxact-saudi.local']);
    $existing = $checkCompany->fetch();

    if (!$existing) {
        $proPlan = $pdo->query("SELECT id FROM plans WHERE slug = 'professional'")->fetch();

        $pdo->prepare('INSERT INTO companies (name, email, phone, city, status, plan_id, trial_ends_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['Al Rashid Construction Co.', 'demo@buildxact-saudi.local', '+966 50 123 4567', 'Riyadh', 'active', $proPlan['id'], date('Y-m-d', strtotime('+14 days'))]);
        $companyId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO users (company_id, name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, 'Ahmed Al Rashid', 'owner@buildxact-saudi.local', password_hash('Demo@12345', PASSWORD_DEFAULT), 'owner', 'active']);

        $pdo->prepare('INSERT INTO subscriptions (company_id, plan_id, billing_cycle, status, current_period_end) VALUES (?, ?, ?, ?, ?)')
            ->execute([$companyId, $proPlan['id'], 'monthly', 'active', date('Y-m-d', strtotime('+30 days'))]);

        $pdo->prepare('INSERT INTO payments (company_id, amount, currency, method, reference, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, 449, 'SAR', 'mada', 'PMT-1001', 'paid']);

        $pdo->prepare('INSERT INTO clients (company_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)')
            ->execute([$companyId, 'Jeddah Heights Development', 'contact@jeddahheights.sa', '+966 55 987 6543', 'Jeddah, Saudi Arabia']);
        $clientId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO projects (company_id, client_id, name, description, status, budget, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, $clientId, 'Villa Renovation - Al Nakheel', 'Full renovation of a 600 sqm villa including MEP works.', 'in_progress', 350000, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+80 days'))]);
        $projectId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO estimates (company_id, project_id, client_id, title, status, total) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, $projectId, $clientId, 'Villa Renovation Estimate', 'accepted', 350000]);
        $estimateId = (int) $pdo->lastInsertId();

        $items = [
            ['Demolition & site prep', 1, 25000],
            ['Structural & MEP works', 1, 150000],
            ['Finishing materials', 1, 100000],
            ['Labor & supervision', 1, 75000],
        ];
        foreach ($items as [$desc, $qty, $cost]) {
            $pdo->prepare('INSERT INTO estimate_items (estimate_id, description, qty, unit_cost, total) VALUES (?, ?, ?, ?, ?)')
                ->execute([$estimateId, $desc, $qty, $cost, $qty * $cost]);
        }

        $pdo->prepare('INSERT INTO invoices (company_id, project_id, client_id, invoice_number, status, total, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$companyId, $projectId, $clientId, 'INV-1001', 'paid', 100000, date('Y-m-d', strtotime('-5 days'))]);
        $invoiceId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, total) VALUES (?, ?, ?, ?, ?)')
            ->execute([$invoiceId, 'Mobilization payment (30%)', 1, 100000, 100000]);

        $tasks = [
            ['Site demolition', -8, -2, 'done'],
            ['Electrical rough-in', -1, 14, 'in_progress'],
            ['Plumbing rough-in', 2, 16, 'pending'],
            ['Interior finishing', 20, 60, 'pending'],
        ];
        foreach ($tasks as [$title, $startOffset, $endOffset, $status]) {
            $pdo->prepare('INSERT INTO schedule_tasks (company_id, project_id, title, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$companyId, $projectId, $title, date('Y-m-d', strtotime("{$startOffset} days")), date('Y-m-d', strtotime("{$endOffset} days")), $status]);
        }

        echo "Demo company seeded: owner@buildxact-saudi.local / Demo@12345\n";
    } else {
        echo "Demo company already exists.\n";
    }
}

echo "Migration complete.\n";
