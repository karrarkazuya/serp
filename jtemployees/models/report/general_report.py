from odoo import models, fields
from datetime import datetime, timedelta, time


class GeneralReport(models.Model):
    _name = 'jtemployees.report.general'
    _description = 'General Employee Report'
    _auto = False

    # ------------------------------------------------------------------
    # PUBLIC API
    # ------------------------------------------------------------------

    def get_report_totals(self, date_from=False, date_to=False, employees=False):
        date_from, date_to = self._normalize_dates(date_from, date_to)

        domain = [('active', '=', True)]
        if employees:
            domain.append(('id', 'in', employees))

        emp_records = self.env['hr.employee'].search(domain, order='name')
        df = date_from.date()
        dt = date_to.date()

        data = []
        for emp in emp_records:
            working_hours = self._get_total_working_hours(emp, df, dt)
            allocations     = self._get_allocations(emp)
            bounces         = self._get_bounces(emp, df, dt)
            shortage        = working_hours[0]
            total_hours     = working_hours[1]
            absence         = working_hours[2]
            overtime_hours  = self._get_overtime_hours(emp, df, dt)
            leave_days      = self._get_total_leave_days(emp, df, dt)

            data.append({
                'employee_id':   emp.id,
                'employee_name': emp.name,

                # raw numbers (for XLSX write_number / totals)
                'allocations_num':     allocations,
                'bounces_num':         bounces,
                'shortage_num':        shortage,
                'total_hours_num':     total_hours,
                'absence_num':         absence,
                'overtime_hours_num':  overtime_hours,

                # formatted strings (for display / PDF)
                'allocations':     format(allocations,     ',.0f'),
                'bounces':         format(bounces,         ',.0f'),
                'shortage':        format(shortage,        ',.0f'),
                'total_hours':     format(total_hours,     ',.0f'),
                'absence':         format(absence,     ',.0f'),
                'overtime_hours':  format(overtime_hours,  ',.0f'),
                'leave_days':      leave_days,

                'lines':        [],
                'lines_loaded': False,
            })

        totals = {
            'allocations':     format(sum(r['allocations_num']     for r in data), ',.0f'),
            'bounces':         format(sum(r['bounces_num']         for r in data), ',.0f'),
            'shortage':        format(sum(r['shortage_num']        for r in data), ',.0f'),
        }

        return {
            'report_name': 'General Report',
            'parameters': {
                'date_from': df,
                'date_to':   dt,
                'employees': employees,
            },
            'data':   data,
            'totals': totals,
        }

    def get_employee_lines(self, employee_id, date_from, date_to):
        df = fields.Datetime.to_datetime(date_from + ' 00:00:00').date()
        dt = fields.Datetime.to_datetime(date_to   + ' 23:59:59').date()
        emp = self.env['hr.employee'].browse(employee_id)
        return self._get_day_lines(emp, df, dt)

    def get_report_export(self, date_from=False, date_to=False, employees=False):
        """Full dataset for PDF/XLSX export (totals + all employee day lines)."""
        report = self.get_report_totals(
            date_from=date_from,
            date_to=date_to,
            employees=employees,
        )
        df_str = str(report['parameters']['date_from'])
        dt_str = str(report['parameters']['date_to'])
        for row in report['data']:
            row['lines'] = self.get_employee_lines(row['employee_id'], df_str, dt_str)
        return report

    # ------------------------------------------------------------------
    # STUB METHODS — replace each body with real business logic
    # ------------------------------------------------------------------


    def _get_allocations(self, employee):
        total_allocations = 0
        allocations = self.env['jtemployees.extraallocations'].sudo().search([('employee_id', '=', employee.id), ('deleted', '=', False)])
        for item in allocations:
            total_allocations = total_allocations + item.amount
        return total_allocations

    def _get_bounces(self, employee, date_from, date_to):
        total_bounces = 0
        bounces = self.env['jtemployees.bounces'].sudo().search([('employee_ids', 'in', employee.id), ('date', '>=', date_from), ('date', '<', date_to), ('deleted', '=', False)])
        for item in bounces:
            total_bounces = total_bounces + item.amount
        return total_bounces

    def _get_total_working_hours(self, employee, date_from, date_to):
        """Return total actual worked hours for employee in the period."""
        
        total_shortage = 0
        total_hours = 0
        attendance = self.env['hr.attendance'].sudo().search([('employee_id', '=', employee.id), ('check_in', '>=', date_from), ('check_out', '<', date_to)])
        days = self.env['jtemployees.passed.days'].sudo().search([('employee_id', '=', employee.id), ('date', '>=', date_from), ('date', '<', date_to), ('is_day_off', '=', False)])
        
        for item in attendance:
            total_shortage = total_shortage + item.jt_shortage_hours
            total_hours = total_hours + item.jt_worked_hours
            
        absence_days = 0
            
        for day in days:
            start_dt = datetime.combine(day.date, time.min)
            end_dt = datetime.combine(day.date, time.max)

            fingerprint_at_this_date = self.env['jtemployees.fd.log'].sudo().search([
                ('employee_id', '=', day.employee_id.id),
                ('check_in', '>=', start_dt),
                ('check_in', '<=', end_dt),
            ], limit=1)

            if not fingerprint_at_this_date:
                absence_days = absence_days + 1
        company_work_hours = employee.company_id.jt_general_hours_per_day
        
        total_shortage = total_shortage + company_work_hours * absence_days

        return total_shortage, total_hours, absence_days

    def _get_overtime_hours(self, employee, date_from, date_to):
        """Return total overtime hours for employee in the period."""
        total_hours = 0
        requests = self.env['jtemployees.requests'].sudo().search([('employee_id', '=', employee.id), ('request_type', '=', 'over_time'), ('datetime_from', '>=', date_from), ('datetime_to', '<', date_to), ('deleted', '=', False), ('hr_approved', '=', 'approved')])
        for item in requests:
            from_date_obj = item.datetime_from
            to_date_obj   = item.datetime_to
            new_date = to_date_obj - from_date_obj
            hours = new_date.total_seconds() / 3600
            total_hours = total_hours + hours
        return total_hours
    
    
    def _get_timeoff_hours(self, employee, date_from, date_to):
 
        """Return total overtime hours for employee in the period."""
        total_hours = 0
        requests = self.env['jtemployees.requests'].sudo().search([('employee_id', '=', employee.id), ('request_type', 'in', ['admin_time_off', 'paid_time_off', 'unpaid_time_off', 'field_work_time_off', 'official_mission_time_off', 'external_training_time_off', 'client_visit_time_off', 'government_errand_time_off']), ('datetime_from', '>=', date_from), ('datetime_to', '<', date_to), ('deleted', '=', False), ('hr_approved', '=', 'approved')])
        for item in requests:
            from_date_obj = item.datetime_from
            to_date_obj   = item.datetime_to
            new_date = to_date_obj - from_date_obj
            hours = new_date.total_seconds() / 3600
            total_hours = total_hours + hours
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

    def _get_day_lines(self, employee, date_from, date_to):
        lines = []
        
        attendance = self.env['hr.attendance'].sudo().search([('employee_id', '=', employee.id), ('check_in', '>=', date_from), ('check_out', '<', date_to)])
        days = self.env['jtemployees.passed.days'].sudo().search([('employee_id', '=', employee.id), ('date', '>=', date_from), ('date', '<=', date_to), ('is_day_off', '=', False)])
        
        for item in days:
            
            worked_from = ''
            worked_to = ''
            working_schedule = ''
            total_shortage = employee.company_id.jt_general_hours_per_day
            
            for att in attendance:
                if self.is_present(att.check_in, att.check_out, item.start_time, item.end_time):
                    worked_from = att.check_in
                    worked_to = att.check_out
                    working_schedule = att.jt_work_schedule.name
                    total_shortage = att.jt_shortage_hours
                    break
                
            total_leave_requests = self._get_total_leave_days(employee=employee, date_from=item.date, date_to=item.date)
            total_overtime       = self._get_overtime_hours(employee=employee, date_from=item.date, date_to=item.date)
            total_timeoff        = self._get_timeoff_hours(employee=employee, date_from=item.date, date_to=item.date)
            
            lines.append({
                'line_key':               f'{employee.id}-{item.date}',
                'day':                    str(item.date),
                'worked_from':            worked_from,
                'worked_to':              worked_to,
                'working_schedule':       working_schedule,
                'working_schedule_from':  item.start_time,
                'working_schedule_to':    item.end_time,
                'total_shortage':         total_shortage,
                'leave_requests':         total_leave_requests,
                'timeoff_requests':       total_timeoff,
                'overtime_requests':      total_overtime,
            })
        return lines

    # ------------------------------------------------------------------
    # HELPERS
    # ------------------------------------------------------------------

    def _normalize_dates(self, date_from, date_to):
        if date_from:
            date_from = fields.Datetime.to_datetime(date_from + ' 00:00:00')
        if date_to:
            date_to = fields.Datetime.to_datetime(date_to + ' 23:59:59')
        if not date_from or not date_to:
            now = datetime.now()
            date_from = datetime(now.year, now.month, 1)
            date_to   = datetime(now.year, now.month, 28, 23, 59, 59)
        return date_from, date_to


    def float_hours_to_time(self, base_date: datetime, float_hours: float) -> datetime:
        """Convert a float hour (e.g., 8.5 = 08:30) to a datetime on the given date."""
        return datetime.combine(base_date.date(), datetime.min.time()) + timedelta(hours=float_hours)

    def is_present(self, datetime_from, datetime_to, float_hours_from, float_hours_to):
        """
        Check if employee was present based on overlap between
        actual check-in/out window and scheduled shift.
        """
        if datetime_from is None or datetime_to is None:
            return False  # didn't check in or out → absent

        # Build scheduled shift datetimes using the check-in date as reference
        shift_start = self.float_hours_to_time(datetime_from, float_hours_from)
        shift_end = self.float_hours_to_time(datetime_from, float_hours_to)

        # Handle overnight shifts (e.g., 22.0 → 6.0)
        if float_hours_to <= float_hours_from:
            shift_end += timedelta(days=1)

        # Overlap check: any intersection between [datetime_from, datetime_to]
        # and [shift_start, shift_end] means the employee was present
        return datetime_from < shift_end and datetime_to > shift_start