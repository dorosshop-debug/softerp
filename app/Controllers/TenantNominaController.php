<?php

namespace SoftNova\Controllers;

use SoftNova\Core\TenantController;
use SoftNova\Core\TenantMiddleware;
use SoftNova\Services\PayrollService;
use SoftNova\Services\TenantOpsSchema;

/**
 * Módulo Nómina: empleados + liquidaciones mensuales.
 */
class TenantNominaController extends TenantController
{
    private PayrollService $payroll;

    public function __construct()
    {
        parent::__construct();
        TenantMiddleware::authorize('nomina');
        TenantOpsSchema::ensure($this->db);
        $this->payroll = new PayrollService($this->db);
    }

    public function index(): void
    {
        $action = (string)$this->request->get('action', '');

        if ($this->request->method() === 'POST') {
            if (!$this->validateCsrfOrFail('/app/nomina')) {
                return;
            }
            match ($action) {
                'save_employee' => $this->saveEmployee(),
                'create_run' => $this->createRun(),
                'pay_run' => $this->payRun(),
                'cancel_run' => $this->cancelRun(),
                'save_params' => $this->saveParams(),
                default => $this->respond(false, 'Acción inválida', '/app/nomina'),
            };
            return;
        }

        if ($action === 'run_detail') {
            $this->runDetail();
            return;
        }

        $tab = (string)$this->request->get('tab', 'employees');
        if (!in_array($tab, ['employees', 'runs', 'params'], true)) {
            $tab = 'employees';
        }

        $q = trim((string)$this->request->get('q', ''));
        $status = (string)$this->request->get('status', '');
        $employees = $this->payroll->employees($status, $q);
        $runs = $this->payroll->runs(36);
        $params = $this->payroll->params();

        $activeCount = count(array_filter($employees, fn ($e) => ($e['status'] ?? '') === 'active'));
        $monthPayroll = 0.0;
        $y = (int)date('Y');
        $m = (int)date('n');
        foreach ($runs as $run) {
            if ((int)$run['period_year'] === $y && (int)$run['period_month'] === $m && ($run['status'] ?? '') === 'paid') {
                $monthPayroll = (float)$run['net_total'];
                break;
            }
        }

        $this->view('tenant.nomina', $this->tenantViewData([
            'tab' => $tab,
            'employees' => $employees,
            'runs' => $runs,
            'params' => $params,
            'q' => $q,
            'statusFilter' => $status,
            'activeCount' => $activeCount,
            'monthPayroll' => $monthPayroll,
        ]));
    }

    private function saveEmployee(): void
    {
        TenantMiddleware::authorize('nomina', 'create');
        try {
            $id = $this->payroll->saveEmployee([
                'id' => (int)$this->request->post('id', 0),
                'document_type' => $this->request->post('document_type', 'CC'),
                'document_number' => $this->request->post('document_number', ''),
                'first_name' => $this->request->post('first_name', ''),
                'last_name' => $this->request->post('last_name', ''),
                'email' => $this->request->post('email', ''),
                'phone' => $this->request->post('phone', ''),
                'position_title' => $this->request->post('position_title', ''),
                'hire_date' => $this->request->post('hire_date', ''),
                'salary' => $this->request->post('salary', 0),
                'contract_type' => $this->request->post('contract_type', 'indefinido'),
                'payment_method' => $this->request->post('payment_method', 'transfer'),
                'bank_account' => $this->request->post('bank_account', ''),
                'has_transport_aid' => $this->request->post('has_transport_aid', '0') === '1',
                'status' => $this->request->post('status', 'active'),
                'notes' => $this->request->post('notes', ''),
            ]);
            $this->respond(true, $id ? 'Empleado guardado' : 'Empleado creado', '/app/nomina?tab=employees');
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/nomina?tab=employees');
        }
    }

    private function createRun(): void
    {
        TenantMiddleware::authorize('nomina', 'create');
        try {
            $year = (int)$this->request->post('period_year', date('Y'));
            $month = (int)$this->request->post('period_month', date('n'));
            $payDate = (string)$this->request->post('pay_date', date('Y-m-d'));
            $days = (int)$this->request->post('days_worked', 30);
            $method = (string)$this->request->post('payment_method', 'transfer');
            $notes = trim((string)$this->request->post('notes', ''));
            $runId = $this->payroll->createRun(
                $year,
                $month,
                $payDate,
                $days,
                $method,
                (int)($_SESSION['tenant_user_id'] ?? 0) ?: null,
                $notes
            );
            $this->respond(true, 'Liquidación creada', '/app/nomina?tab=runs&action=run_detail&id=' . $runId);
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/nomina?tab=runs');
        }
    }

    private function payRun(): void
    {
        TenantMiddleware::authorize('nomina', 'edit');
        try {
            $id = (int)$this->request->post('id', 0);
            $affectCash = $this->request->post('affect_cash') === '1';
            $this->payroll->postAndPay($id, $affectCash);
            $this->respond(true, 'Nómina contabilizada y marcada como pagada', '/app/nomina?tab=runs&action=run_detail&id=' . $id);
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/nomina?tab=runs');
        }
    }

    private function cancelRun(): void
    {
        TenantMiddleware::authorize('nomina', 'delete');
        try {
            $this->payroll->cancelRun((int)$this->request->post('id', 0));
            $this->respond(true, 'Liquidación cancelada', '/app/nomina?tab=runs');
        } catch (\Throwable $e) {
            $this->respond(false, $e->getMessage(), '/app/nomina?tab=runs');
        }
    }

    private function saveParams(): void
    {
        TenantMiddleware::authorize('nomina', 'edit');
        $map = [
            'smmlv' => (string)$this->request->post('smmlv', '1423500'),
            'transport_aid' => (string)$this->request->post('transport_aid', '200000'),
            'health_employee_rate' => (string)$this->request->post('health_employee_rate', '4'),
            'pension_employee_rate' => (string)$this->request->post('pension_employee_rate', '4'),
            'health_employer_rate' => (string)$this->request->post('health_employer_rate', '8.5'),
            'pension_employer_rate' => (string)$this->request->post('pension_employer_rate', '12'),
        ];
        $stmt = $this->db->prepare(
            "INSERT INTO accounting_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($map as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $this->respond(true, 'Parámetros de nómina guardados', '/app/nomina?tab=params');
    }

    private function runDetail(): void
    {
        $id = (int)$this->request->get('id', 0);
        $detail = $this->payroll->runDetail($id);
        if (!$detail) {
            $this->respond(false, 'Liquidación no encontrada', '/app/nomina?tab=runs');
            return;
        }
        $this->view('tenant.nomina', $this->tenantViewData([
            'tab' => 'run_detail',
            'employees' => [],
            'runs' => [],
            'params' => $this->payroll->params(),
            'detail' => $detail,
            'q' => '',
            'statusFilter' => '',
            'activeCount' => 0,
            'monthPayroll' => 0,
        ]));
    }
}
