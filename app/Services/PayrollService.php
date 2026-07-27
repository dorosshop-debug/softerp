<?php

namespace SoftNova\Services;

/**
 * Nómina Colombia (MVP): empleados + liquidación mensual básica
 * (salud/pensión empleado-empleador + auxilio de transporte).
 */
class PayrollService
{
    public function __construct(private \PDO $db)
    {
        TenantOpsSchema::ensure($db);
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Parámetros SMMLV / auxilio / aportes (editables vía accounting_settings). */
    public function params(): array
    {
        $acc = new AccountingService($this->db);
        // Sembrar defaults
        $defaults = [
            'smmlv' => '1423500', // referencia 2026 aprox; el tenant puede ajustar
            'transport_aid' => '200000',
            'health_employee_rate' => '4',
            'pension_employee_rate' => '4',
            'health_employer_rate' => '8.5',
            'pension_employer_rate' => '12',
            'payroll_expense_account' => '510503',
            'payroll_payable_account' => '250505',
        ];
        foreach ($defaults as $key => $value) {
            $this->query(
                "INSERT IGNORE INTO accounting_settings (setting_key, setting_value) VALUES (?, ?)",
                [$key, $value]
            );
        }
        // Asegurar cuentas
        $this->query(
            "INSERT IGNORE INTO accounting_accounts (code, name, account_type, nature, is_system)
             VALUES ('510503', 'Nómina y prestaciones', 'expense', 'debit', 1)"
        );
        $this->query(
            "INSERT IGNORE INTO accounting_accounts (code, name, account_type, nature, is_system)
             VALUES ('250505', 'Salarios por pagar', 'liability', 'credit', 1)"
        );

        return [
            'smmlv' => (float)$this->setting('smmlv', '1423500'),
            'transport_aid' => (float)$this->setting('transport_aid', '200000'),
            'health_employee_rate' => (float)$this->setting('health_employee_rate', '4'),
            'pension_employee_rate' => (float)$this->setting('pension_employee_rate', '4'),
            'health_employer_rate' => (float)$this->setting('health_employer_rate', '8.5'),
            'pension_employer_rate' => (float)$this->setting('pension_employer_rate', '12'),
            'payroll_expense_account' => $this->setting('payroll_expense_account', '510503'),
            'payroll_payable_account' => $this->setting('payroll_payable_account', '250505'),
        ];
    }

    private function setting(string $key, string $default): string
    {
        $row = $this->query(
            'SELECT setting_value FROM accounting_settings WHERE setting_key = ? LIMIT 1',
            [$key]
        )->fetch();
        return $row ? (string)$row['setting_value'] : $default;
    }

    public function employees(string $status = '', string $q = ''): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR document_number LIKE ? OR position_title LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return $this->query(
            'SELECT * FROM employees WHERE ' . implode(' AND ', $where)
            . ' ORDER BY last_name, first_name'
            , $params
        )->fetchAll();
    }

    public function saveEmployee(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $docType = trim((string)($data['document_type'] ?? 'CC')) ?: 'CC';
        $doc = preg_replace('/\s+/', '', (string)($data['document_number'] ?? ''));
        $first = trim((string)($data['first_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        if ($doc === '' || $first === '') {
            throw new \InvalidArgumentException('Documento y nombre son obligatorios');
        }
        $salary = max(0, (float)($data['salary'] ?? 0));
        $fields = [
            $docType,
            $doc,
            $first,
            $last,
            trim((string)($data['email'] ?? '')) ?: null,
            trim((string)($data['phone'] ?? '')) ?: null,
            trim((string)($data['position_title'] ?? '')) ?: null,
            ($data['hire_date'] ?? '') !== '' ? $data['hire_date'] : null,
            $salary,
            trim((string)($data['contract_type'] ?? 'indefinido')) ?: 'indefinido',
            trim((string)($data['payment_method'] ?? 'transfer')) ?: 'transfer',
            trim((string)($data['bank_account'] ?? '')) ?: null,
            !empty($data['has_transport_aid']) ? 1 : 0,
            in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
            trim((string)($data['notes'] ?? '')) ?: null,
        ];

        if ($id > 0) {
            $this->query(
                "UPDATE employees SET document_type=?, document_number=?, first_name=?, last_name=?,
                    email=?, phone=?, position_title=?, hire_date=?, salary=?, contract_type=?,
                    payment_method=?, bank_account=?, has_transport_aid=?, status=?, notes=?
                 WHERE id=?",
                array_merge($fields, [$id])
            );
            return $id;
        }

        $this->query(
            "INSERT INTO employees
                (document_type, document_number, first_name, last_name, email, phone, position_title,
                 hire_date, salary, contract_type, payment_method, bank_account, has_transport_aid, status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            $fields
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Calcula liquidación de un empleado para N días (base 30).
     *
     * @return array<string,float|int|string>
     */
    public function calculateEmployeeLine(array $employee, int $daysWorked = 30): array
    {
        $p = $this->params();
        $days = max(1, min(31, $daysWorked));
        $factor = $days / 30;
        $salary = (float)$employee['salary'];
        $base = round($salary * $factor, 2);

        $transport = 0.0;
        if (!empty($employee['has_transport_aid']) && $salary <= ($p['smmlv'] * 2)) {
            $transport = round($p['transport_aid'] * $factor, 2);
        }

        $healthEmp = round($base * $p['health_employee_rate'] / 100, 2);
        $pensionEmp = round($base * $p['pension_employee_rate'] / 100, 2);
        $healthEr = round($base * $p['health_employer_rate'] / 100, 2);
        $pensionEr = round($base * $p['pension_employer_rate'] / 100, 2);

        $gross = round($base + $transport, 2);
        $deductions = round($healthEmp + $pensionEmp, 2);
        $net = round($gross - $deductions, 2);

        $name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));

        return [
            'employee_id' => (int)$employee['id'],
            'employee_name' => $name,
            'salary_base' => $base,
            'days_worked' => $days,
            'transport_aid' => $transport,
            'other_earnings' => 0.0,
            'health_employee' => $healthEmp,
            'pension_employee' => $pensionEmp,
            'other_deductions' => 0.0,
            'health_employer' => $healthEr,
            'pension_employer' => $pensionEr,
            'gross_pay' => $gross,
            'net_pay' => $net,
        ];
    }

    /**
     * Crea liquidación del periodo (único por año-mes). Estado draft.
     */
    public function createRun(int $year, int $month, string $payDate, int $daysWorked = 30, string $paymentMethod = 'transfer', ?int $userId = null, string $notes = ''): int
    {
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));
        $exists = $this->query(
            "SELECT id, status FROM payroll_runs WHERE period_year=? AND period_month=? LIMIT 1",
            [$year, $month]
        )->fetch();
        if ($exists && ($exists['status'] ?? '') !== 'cancelled') {
            throw new \RuntimeException('Ya existe una liquidación para ese periodo');
        }

        $employees = $this->employees('active');
        if (!$employees) {
            throw new \RuntimeException('No hay empleados activos para liquidar');
        }

        $number = sprintf('NOM-%04d%02d', $year, $month);
        $label = sprintf('%02d/%04d', $month, $year);
        $this->db->beginTransaction();
        try {
            if ($exists && ($exists['status'] ?? '') === 'cancelled') {
                $this->query('DELETE FROM payroll_runs WHERE id=?', [(int)$exists['id']]);
            }
            $this->query(
                "INSERT INTO payroll_runs
                    (run_number, period_year, period_month, period_label, pay_date, days_worked,
                     payment_method, status, notes, user_id)
                 VALUES (?,?,?,?,?,?,?,'draft',?,?)",
                [$number, $year, $month, $label, $payDate, $daysWorked, $paymentMethod, $notes ?: null, $userId]
            );
            $runId = (int)$this->db->lastInsertId();

            $gross = $ded = $net = $employer = 0.0;
            $ins = $this->db->prepare(
                "INSERT INTO payroll_items
                    (payroll_run_id, employee_id, employee_name, salary_base, days_worked, transport_aid,
                     other_earnings, health_employee, pension_employee, other_deductions,
                     health_employer, pension_employer, gross_pay, net_pay)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            foreach ($employees as $emp) {
                $line = $this->calculateEmployeeLine($emp, $daysWorked);
                $ins->execute([
                    $runId, $line['employee_id'], $line['employee_name'], $line['salary_base'], $line['days_worked'],
                    $line['transport_aid'], $line['other_earnings'], $line['health_employee'], $line['pension_employee'],
                    $line['other_deductions'], $line['health_employer'], $line['pension_employer'],
                    $line['gross_pay'], $line['net_pay'],
                ]);
                $gross += $line['gross_pay'];
                $ded += $line['health_employee'] + $line['pension_employee'] + $line['other_deductions'];
                $net += $line['net_pay'];
                $employer += $line['health_employer'] + $line['pension_employer'];
            }

            $this->query(
                "UPDATE payroll_runs SET gross_total=?, deductions_total=?, net_total=?, employer_total=? WHERE id=?",
                [round($gross, 2), round($ded, 2), round($net, 2), round($employer, 2), $runId]
            );
            $this->db->commit();
            return $runId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function runs(int $limit = 24): array
    {
        return $this->query(
            "SELECT * FROM payroll_runs ORDER BY period_year DESC, period_month DESC LIMIT " . (int)$limit
        )->fetchAll();
    }

    public function runDetail(int $id): ?array
    {
        $run = $this->query('SELECT * FROM payroll_runs WHERE id=?', [$id])->fetch();
        if (!$run) {
            return null;
        }
        $items = $this->query(
            'SELECT * FROM payroll_items WHERE payroll_run_id=? ORDER BY employee_name',
            [$id]
        )->fetchAll();
        return ['run' => $run, 'items' => $items];
    }

    /**
     * Contabiliza y marca como pagada: crea gasto categoría payroll + asiento nómina.
     */
    public function postAndPay(int $runId, bool $affectCash = false): void
    {
        $detail = $this->runDetail($runId);
        if (!$detail) {
            throw new \RuntimeException('Liquidación no encontrada');
        }
        $run = $detail['run'];
        if (($run['status'] ?? '') === 'cancelled') {
            throw new \RuntimeException('Liquidación cancelada');
        }
        if (in_array($run['status'], ['posted', 'paid'], true)) {
            throw new \RuntimeException('La liquidación ya fue contabilizada');
        }

        $net = (float)$run['net_total'];
        if ($net <= 0) {
            throw new \RuntimeException('Neto a pagar inválido');
        }

        $this->db->beginTransaction();
        try {
            // Gasto operativo (nómina) para reportes de gastos tipificados
            $this->query(
                "INSERT INTO expenses
                    (description, amount, category, expense_date, payment_method, notes, user_id)
                 VALUES (?,?,?,?,?,?,?)",
                [
                    'Nómina ' . $run['period_label'],
                    $net,
                    'payroll',
                    $run['pay_date'],
                    $run['payment_method'] ?: 'transfer',
                    'Liquidación ' . $run['run_number'] . ' · bruto ' . $run['gross_total'],
                    $run['user_id'] ?? ($_SESSION['tenant_user_id'] ?? null),
                ]
            );
            $expenseId = (int)$this->db->lastInsertId();

            $accounting = new AccountingService($this->db);
            try {
                // payroll → grupo fijo → cuenta 510505 / 510503 según settings del gasto
                $accounting->postExpense($expenseId, $affectCash && ($run['payment_method'] ?? '') === 'cash');
            } catch (\Throwable $e) {
                error_log('Payroll expense accounting: ' . $e->getMessage());
            }

            $this->query(
                "UPDATE payroll_runs SET status='paid', expense_id=? WHERE id=?",
                [$expenseId, $runId]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelRun(int $runId): void
    {
        $run = $this->query('SELECT * FROM payroll_runs WHERE id=?', [$runId])->fetch();
        if (!$run) {
            throw new \RuntimeException('Liquidación no encontrada');
        }
        if (($run['status'] ?? '') === 'paid') {
            throw new \RuntimeException('No se puede cancelar una nómina ya pagada. Anule el gasto/asiento manualmente si aplica.');
        }
        $this->query("UPDATE payroll_runs SET status='cancelled' WHERE id=?", [$runId]);
    }
}
