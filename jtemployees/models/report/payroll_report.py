from odoo import models, fields
from datetime import timedelta


class PayrollReport(models.Model):
    _name = 'jtemployees.report.payroll'
    _description = 'Payroll Report'
    _auto = False

    # ------------------------------------------------------------------
    # PUBLIC API
    # ------------------------------------------------------------------

    def get_report_totals(self, payroll_id=False, employees=False):
        domain = [('deleted', '=', False)]

        df = False
        dt = False
        
        payroll_name = ''
        
        slips = []
        if payroll_id:
            payroll_id = int(payroll_id)
            domain = [('payroll', '=', payroll_id), ('deleted', '=', False)]
            if employees:
                domain.append(('employee', 'in', employees))
            payroll = self.env['jtemployees.payrolls'].search([('id', '=', payroll_id), ('deleted', '=', False)])
            payroll_name = payroll.name
            df = payroll.date_selected_from
            dt = payroll.date_selected_to
            slips = self.env['jtemployees.payrolls.slips'].search(domain)

        data = []
        for slip in slips:
            emp = slip.employee
            details = slip.details
            
            basic_salary = 0
            overtime_hours = 0
            for item in details:
                if item.slug == "base_salary":
                    basic_salary = item.amount
                if item.slug == "overtime":
                    for dsub in item.subs:
                        overtime_hours = overtime_hours + dsub.hours
                        
            allocations     = slip.total_allocations
            bounces         = slip.total_rewards
            deductions      = slip.total_deductions
            shortage        = slip.total_shortages
            absences        = slip.total_absences
            total_hours     = self._get_total_working_hours(emp, df, dt)
            overtime_amount = slip.total_overtime
            total_salary    = slip.total_amount
            leave_days      = self._get_total_leave_days(emp, df, dt)

            data.append({
                'employee_id':   emp.id,
                'employee_name': emp.name,

                'basic_salary_num':    basic_salary,
                'allocations_num':     allocations,
                'bounces_num':         bounces,
                'deductions_num':      deductions,
                'shortage_num':        shortage,
                'absence_num':         absences,
                'total_hours_num':     total_hours,
                'overtime_hours_num':  overtime_hours,
                'overtime_amount_num': overtime_amount,
                'total_salary_num':    total_salary,

                'basic_salary':    format(basic_salary,    ',.0f'),
                'allocations':     format(allocations,     ',.0f'),
                'bounces':         format(bounces,         ',.0f'),
                'deductions':      format(deductions,      ',.0f'),
                'shortage':        format(shortage,        ',.0f'),
                'absences':        format(absences,        ',.0f'),
                'total_hours':     format(total_hours,     ',.0f'),
                'overtime_hours':  format(overtime_hours,  ',.0f'),
                'overtime_amount': format(overtime_amount, ',.0f'),
                'total_salary':    format(total_salary,    ',.0f'),
                'leave_days':      leave_days,

                'lines':        [],
                'lines_loaded': False,
            })

        totals = {
            'basic_salary':    format(sum(r['basic_salary_num']    for r in data), ',.0f'),
            'allocations':     format(sum(r['allocations_num']     for r in data), ',.0f'),
            'bounces':         format(sum(r['bounces_num']         for r in data), ',.0f'),
            'deductions':      format(sum(r['deductions_num']      for r in data), ',.0f'),
            'shortage':        format(sum(r['shortage_num']        for r in data), ',.0f'),
            'absence':         format(sum(r['absence_num']         for r in data), ',.0f'),
            'overtime_amount': format(sum(r['overtime_amount_num'] for r in data), ',.0f'),
            'total_salary':    format(sum(r['total_salary_num']    for r in data), ',.0f'),
        }

        return {
            'report_name': 'Payroll Report',
            'parameters': {
                'payroll_id':    payroll_id,
                'payroll_name':  payroll_name,
                'employees':     employees,
            },
            'data':   data,
            'totals': totals,
        }

    def get_employee_lines(self, employee_id, payroll_id=False):
        emp = self.env['hr.employee'].browse(employee_id)
        return self._get_detail_lines(emp, payroll_id)

    def get_report_export(self, payroll_id=False, employees=False):
        """Full dataset for PDF/XLSX export (totals + all employee detail lines)."""
        report = self.get_report_totals(payroll_id=payroll_id, employees=employees)
        for row in report['data']:
            row['lines'] = self.get_employee_lines(row['employee_id'], payroll_id)
        return report

    # ------------------------------------------------------------------
    # STUB METHODS — replace each body with real business logic
    # ------------------------------------------------------------------


    def _get_total_working_hours(self, employee, date_from, date_to):
        """Return total actual worked hours for employee in the period."""
        
        total_hours = 0
        attendance = self.env['hr.attendance'].sudo().search([('employee_id', '=', employee.id), ('check_in', '>=', date_from), ('check_out', '<', date_to)])
        for item in attendance:
            total_hours = total_hours + item.jt_worked_hours
            
        return total_hours

    def _get_total_leave_days(self, employee, date_from, date_to):
        """Return total approved leave days for employee in the period."""
        total_hours = 0
        requests = self.env['jtemployees.requests'].sudo().search([('employee_id', '=', employee.id), ('request_type', 'in', ['admin_leave', 'unpaid_leave', 'paid_leave', 'remote_working_leave', 'field_work_leave', 'official_mission_leave', 'external_training_leave', 'client_visit_leave', 'government_errand_leave']), ('date_from', '>=', date_from), ('date_to', '<', date_to), ('deleted', '=', False), ('hr_approved', '=', 'approved')])
        for item in requests:
            from_date_obj = item.date_from
            to_date_obj   = item.date_to
            duration = to_date_obj - from_date_obj
            total_hours = total_hours + duration.days
        return total_hours

    def _get_detail_lines(self, employee, payroll_id):
        slips = []
        if payroll_id:
            slips = self.env['jtemployees.payrolls.slips'].search([('payroll', '=', int(payroll_id)), ('deleted', '=', False), ('employee', '=', employee.id)])

        lines = []
        for slip in slips:
            details = slip.details
            
            for item in details:
                subs = item.subs
                for sub in subs:
                    lines.append({
                    'line_key':   f'{employee.id}-{sub.id}',
                    'detail':     item.name,
                    'sub':        sub.name,
                    'date':       sub.date.date(),
                    'hours':      sub.hours_readable,
                    'amount':     format(sub.amount, ",.0f")
                    })
        return lines
