from odoo import models, fields, exceptions, api
from datetime import datetime, timedelta

import pytz
from odoo.tools.float_utils import float_is_zero
from pytz import timezone
from collections import defaultdict
from operator import itemgetter
from odoo.osv.expression import AND, OR


class hr_attendance(models.Model):
    _inherit = 'hr.attendance'
    
    jt_work_schedule     = fields.Many2one('resource.calendar', check_company=True, tracking=True, domain="['|', ('company_id', '=', False), ('company_id', '=', employee_company_id)]")
    jt_worked_hours      = fields.Float('Worked Hours')
    jt_shortage_hours    = fields.Float('Shortage Hours')
    jt_hours_per_day     = fields.Float('Expected hours')
    employee_company_id  = fields.Many2one('res.company', related='employee_id.company_id', store=True)

    
    jt_worked_hours_readable         = fields.Char(string='Worked Hours')
    jt_shortage_hours_readable       = fields.Char(string='Shortage Hours')
    jt_hours_per_day_readable        = fields.Char(string='Expected hours')

    
    def _compute_jt_worked_hours_readable(self):
        for record in self:
            mins = record.jt_worked_hours * 60
            hours = int(mins // 60)
            mins = int(round(mins % 60))
            formatted_time = f"{hours:02d}:{mins:02d}"
            record.jt_worked_hours_readable = formatted_time
            
    def _compute_jt_shortage_hours_readable(self):
        for record in self:
            mins = record.jt_shortage_hours * 60
            hours = int(mins // 60)
            mins = int(round(mins % 60))
            formatted_time = f"{hours:02d}:{mins:02d}"
            record.jt_shortage_hours_readable = formatted_time

    @api.model_create_multi
    def create(self, vals_list):
        item = super().create(vals_list)
        item.calculate_worked_time()
        return item
    
    def write(self, vals):
        result = super().write(vals)
        if 'check_in' in vals or 'check_out' in vals or 'jt_work_schedule' in vals:
            self.calculate_worked_time()
        return result
    
    def direct_write(self, vals):
        return super().write(vals)
    
    def calculate_worked_time(self):
        if self.check_in and self.check_out:
            if not self.jt_work_schedule:
                self.direct_write({
                    "jt_work_schedule": self.employee_id.resource_calendar_id.id
                })
                #self.jt_work_schedule = self.employee_id.resource_calendar_id.id
            self.jt_hours_per_day = self.jt_work_schedule.hours_per_day
            
            payroll_slip = self.env['jtemployees.payrolls.slips'].sudo()
            self.check_in
            self.check_out
            self.jt_worked_hours = payroll_slip.working_for_attendance(self.jt_work_schedule, self)
            
            if self.jt_worked_hours > 0 and self.jt_worked_hours < self.worked_hours and self.worked_hours > 0:
                self.worked_hours = self.jt_worked_hours
                
            self.jt_shortage_hours = self.jt_hours_per_day - self.jt_worked_hours
            if self.jt_shortage_hours < 0:
                self.jt_shortage_hours = 0
            
            self._compute_jt_worked_hours_readable()
            self._compute_jt_shortage_hours_readable()
            
    def _update_overtime(self, employee_attendance_dates=None):
        if employee_attendance_dates is None:
            employee_attendance_dates = self._get_attendances_dates()

        overtime_to_unlink = self.env['hr.attendance.overtime']
        overtime_vals_list = []
        affected_employees = self.env['hr.employee']
        for emp, attendance_dates in employee_attendance_dates.items():
            # get_attendances_dates returns the date translated from the local timezone without tzinfo,
            # and contains all the date which we need to check for overtime
            attendance_domain = []
            for attendance_date in attendance_dates:
                attendance_domain = OR([attendance_domain, [
                    ('check_in', '>=', attendance_date[0]), ('check_in', '<', attendance_date[0] + timedelta(hours=24)),
                ]])
            attendance_domain = AND([[('employee_id', '=', emp.id)], attendance_domain])

            # Attendances per LOCAL day
            attendances_per_day = defaultdict(lambda: self.env['hr.attendance'])
            all_attendances = self.env['hr.attendance'].search(attendance_domain)
            for attendance in all_attendances:
                check_in_day_start = attendance._get_day_start_and_day(attendance.employee_id, attendance.check_in)
                attendances_per_day[check_in_day_start[1]] += attendance

            # As _attendance_intervals_batch and _leave_intervals_batch both take localized dates we need to localize those date
            start = pytz.utc.localize(min(attendance_dates, key=itemgetter(0))[0])
            stop = pytz.utc.localize(max(attendance_dates, key=itemgetter(0))[0] + timedelta(hours=24))

            # Retrieve expected attendance intervals
            calendar = emp.resource_calendar_id or emp.company_id.resource_calendar_id
            expected_attendances = emp._employee_attendance_intervals(start, stop)

            # working_times = {date: [(start, stop)]}
            working_times = defaultdict(lambda: [])
            for expected_attendance in expected_attendances:
                # Exclude resource.calendar.attendance
                working_times[expected_attendance[0].date()].append(expected_attendance[:2])

            overtimes = self.env['hr.attendance.overtime'].sudo().search([
                ('employee_id', '=', emp.id),
                ('date', 'in', [day_data[1] for day_data in attendance_dates]),
                ('adjustment', '=', False),
            ])

            company_threshold = emp.company_id.overtime_company_threshold / 60.0
            employee_threshold = emp.company_id.overtime_employee_threshold / 60.0

            dates_calculated = []
            for day_data in attendance_dates:
                attendance_date = day_data[1]
                
                attendances = attendances_per_day.get(attendance_date, self.browse())
                unfinished_shifts = attendances.filtered(lambda a: not a.check_out)
                overtime_duration = 0
                overtime_duration_real = 0
                
                if attendance_date in dates_calculated:
                    continue
                
                # Overtime is not counted if any shift is not closed or if there are no attendances for that day,
                # this could happen when deleting attendances.
                if not unfinished_shifts and attendances:
                    # The employee is working flexible hours
                    if emp.is_flexible:
                        work_duration = 0
                        for attendance in attendances:
                            local_check_in = pytz.utc.localize(attendance.check_in)
                            local_check_out = pytz.utc.localize(attendance.check_out)
                            work_duration += (local_check_out - local_check_in).total_seconds() / 3600.0
                        # In case of fully flexible employee, no overtime is computed
                        if not emp.is_fully_flexible:
                            overtime_duration = work_duration - emp.resource_id.calendar_id.hours_per_day
                            overtime_duration_real = overtime_duration

                    # The employee usually doesn't work on that day
                    elif not working_times[attendance_date]:
                        # User does not have any resource_calendar_attendance for that day (week-end for example)
                        overtime_duration = sum(attendances.mapped('worked_hours'))
                        overtime_duration_real = overtime_duration
                    # The employee usually work on that day
                    else:
                        # Count time before, during and after 'working hours'
                        pre_work_time, work_duration, post_work_time, planned_work_duration = attendances._get_pre_post_work_time(emp, working_times, attendance_date)
                        # Overtime within the planned work hours + overtime before/after work hours is > company threshold
                        overtime_duration = work_duration - planned_work_duration
                        if pre_work_time > company_threshold:
                            overtime_duration += pre_work_time
                        if post_work_time > company_threshold:
                            overtime_duration += post_work_time
                        # Global overtime including the thresholds
                        overtime_duration_real = sum(attendances.mapped('worked_hours')) - planned_work_duration

                overtime = overtimes.filtered(lambda o: o.date == attendance_date)
                if not float_is_zero(overtime_duration, 2) or unfinished_shifts:
                    # Do not create if any attendance doesn't have a check_out, update if exists
                    if unfinished_shifts:
                        overtime_duration = 0
                    if not overtime and overtime_duration:
                        dates_calculated.append(attendance_date)
                        overtime_vals_list.append({
                            'employee_id': emp.id,
                            'date': attendance_date,
                            'duration': overtime_duration,
                            'duration_real': overtime_duration_real,
                        })
                    elif overtime:
                        overtime.sudo().write({
                            'duration': overtime_duration,
                            'duration_real': overtime_duration
                        })
                        affected_employees |= overtime.employee_id
                elif overtime:
                    overtime_to_unlink |= overtime
        created_overtimes = self.env['hr.attendance.overtime'].sudo().create(overtime_vals_list)
        employees_worked_hours_to_compute = (affected_employees.ids +
                                             created_overtimes.employee_id.ids +
                                             overtime_to_unlink.employee_id.ids)
        overtime_to_unlink.sudo().unlink()
        to_recompute = self.search([('employee_id', 'in', employees_worked_hours_to_compute)])
        self.env.add_to_compute(self._fields['overtime_hours'],
                                to_recompute)
        self.env.add_to_compute(self._fields['validated_overtime_hours'],
                                to_recompute)
        self.env.add_to_compute(self._fields['expected_hours'],
                                to_recompute)

    
            
