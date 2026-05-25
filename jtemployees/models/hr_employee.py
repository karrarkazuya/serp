from odoo import models, fields, exceptions, api
from datetime import datetime, timedelta
from pytz import timezone
from odoo.tools import config


class hrEmployee(models.Model):
    _inherit = 'hr.employee'
    
    # Adding new fields
    full_name           = fields.Char(string='Full name (English)', tracking=True)
    full_name_arabic    = fields.Char(string='Full name (Arabic)', tracking=True)
    family_name         = fields.Char(string='Family name', tracking=True)
    mother_name         = fields.Char(string='Mother name', tracking=True)
    # Address
    province            = fields.Char(string='Province', tracking=True)
    region              = fields.Char(string='Region', tracking=True)
    alley               = fields.Char(string='Alley', tracking=True)
    house               = fields.Char(string='House', tracking=True)
    
    images                   = fields.One2many('jtemployees.images', 'employee_id', string='Documents', ondelete='cascade', required=True, tracking=True, domain=[('deleted', '=', False)])
    extra_allocations        = fields.One2many('jtemployees.extraallocations', 'employee_id', string='Extra Allocations', help="Extra allocations are money added to the payroll slip", ondelete='cascade', tracking=True)
    jt_grade                 = fields.Many2one('jtemployees.grades', string='Grade', tracking=True)
    jt_grade_group           = fields.Many2one('jtemployees.grades.groups', string='Level', tracking=True)
    
    jt_join_date               = fields.Date(string='Join Date', required=True, tracking=True)
    jt_migrate_date            = fields.Date(string='Migrate Date', help='The date the shortages calculations shall depend on, if empty then they shall depend on the join date', required=True, tracking=True)
    jt_fixed_salary            = fields.Boolean(string='Hide Salary')
    jt_fixed_salary_amount     = fields.Float(string='Salary Amount', groups='jtemployees.group_admin,jtemployees.group_hr_admin,jtemployees.group_hr_manager')
    jt_fixed_insurance_salary_amount     = fields.Float(string='Salary Insurance Amount', groups='jtemployees.group_admin,jtemployees.group_hr_admin,jtemployees.group_hr_manager')
    jt_current_points          = fields.Float(string='Current Evaluation Points', default=0, tracking=True)
    jt_has_overtime_limits     = fields.Boolean(string='Has Overtime Limitations', default=False, tracking=True)
    jt_has_location_checkin    = fields.Boolean(string='Has Location Check in', default=False, tracking=True)
    jt_ignore_shortages        = fields.Boolean(string='Has no absence shortages', default=False, help='When checked no shortages will be calculated for employee on payrolls', tracking=True)
    jt_location_area           = fields.Many2one('jtemployees.location.areas', string='Location Area (check in)', tracking=True)
    jt_location_area_checkout  = fields.Many2one('jtemployees.location.areas', string='Location Area (check out)', tracking=True)
    jt_request_approval        = fields.Many2one('hr.employee', string='Requests approval', help="The employee's approval for leave or time requests, if not set then it will be the manager of the employee", tracking=True)
    jt_fingerprint_id          = fields.Integer('Fingerprint ID', tracking=True)

    jt_can_set_sub_schedule    = fields.Boolean(string='Manage Subs', default=False, help='Can set schedule/location for sub employees', tracking=True)
    jt_calendar_group_id       = fields.Many2one('jtemployees.calendar.groups', string='Working schedule groups', help="The employee will only be able to set working schedules based on the group", tracking=True)

    jt_overtime_amount_hourly  = fields.Float(string='Overtime Hourly Amount', default=0, tracking=True)
    jt_overtime_max_hours      = fields.Float(string='Overtime Max Hours per Day', default=0, tracking=True)
    jt_timeoff                 = fields.Float(string='Timeoff', default=0, tracking=True)
    jt_timeoff_readable        = fields.Char(string='Timeoff', compute='_compute_readable_period')
    jt_leavedays               = fields.Float(string='Leave Days', default=0, tracking=True)
    jt_panned_days             = fields.One2many('jtemployees.planned.days', 'employee_id', string='Planned schedule', help="Planned schedule to automatically change schedule based on days", ondelete='cascade')
    jt_repeating_schedules     = fields.One2many('jtemployees.planned.rschedules', 'employee_id', string='Repeating schedule', help="Schedules that are being repeated", ondelete='cascade')
    jt_reassign_at             = fields.Date(string='Reasigned at', tracking=True)
    jt_separate_insu_salary   = fields.Boolean(string='Separate insurance salary', compute='_compute_separate_insu_salary')
    
    
    def _compute_separate_insu_salary(self):
        hr_separate_insu_salary = config.get('hr_separate_insu_salary')
        if hr_separate_insu_salary:
            self.jt_separate_insu_salary = True
        else:
            self.jt_separate_insu_salary = False
    
    @api.depends("jt_timeoff")
    def _compute_readable_period(self):
        for record in self:
            hours = int(record.jt_timeoff // 60)
            mins = int(round(record.jt_timeoff % 60))
            formatted_time = f"{hours:02d}:{mins:02d}"
            record.jt_timeoff_readable = formatted_time
            
            
    
    def create(self, vals_list):
        item = super().create(vals_list)
        self.env['jtemployees.subs'].sudo().sync(item)
        return item
            
    
    def write(self, vals):
        if 'resource_calendar_id' in vals:
            if not vals['resource_calendar_id']:
                return True
            
            for emp in self:
                self.env['jtemployees.planned.days'].sudo().reset_for_employee(
                    employee_id=emp.id, 
                    requested_working_schedule=vals['resource_calendar_id']
                )

        result = super(hrEmployee, self).write(vals)
        
        for emp in self:
            if emp.jt_grade and emp.jt_grade_group and emp.jt_grade.id not in emp.jt_grade_group.grades.ids:
                raise exceptions.ValidationError(f"{emp.name} employee's grade must exist in the grade group")
            
            if 'department_id' in vals and emp.department_id:
                emp.department_id.sync_contact_types()
        
        self.env['jtemployees.subs'].sudo().sync(self)
        
        return result
    
    # because the normal wirte conflicts with the subs->reflect on children changes
    def update_working_schedule(self, resource_calendar_id):
        return super(hrEmployee, self).write({"resource_calendar_id": resource_calendar_id})
    
    def write_of_sub(self, vals):
        
        if 'resource_calendar_id' in vals:
            for emp in self:
                self.env['jtemployees.planned.days'].sudo().reset_for_employee(employee_id=emp.id, requested_working_schedule=vals['resource_calendar_id'])

        # Call super() for each record individually
        super(hrEmployee, self).write(vals)
        
        return True
    
    def attendance_info(self):
        payroll_slip = self.env['jtemployees.payrolls.slips'].sudo()
        for employee in self:
            # getting work schedule
            calendar = employee.resource_calendar_id
            
            hour_per_day = calendar.hours_per_day
            
            
            working_days_of_week = []
            shortages_details = []
            expected_hours = 0
            actual_hours = 0
            shortage_hours = 0
            over_time = 0
            leave_days_requested = 0
            time_off_requested = 0
            
            salary_start_date = employee.company_id.jt_salary_start_date
            
            last_slip = self.env['jtemployees.payrolls.slips'].sudo().search([
                ('employee', '=', employee.id), ('deleted', '=', False), ('approved', '=', True)
            ], limit=1, order="id DESC")
            join_date_time = employee.jt_migrate_date
            if not join_date_time:
                join_date_time = employee.jt_join_date
            date_selected_from = datetime.strptime(join_date_time.strftime('%Y-%m-%d'), '%Y-%m-%d')
            if last_slip:
                date_selected_from = datetime.strptime(str(last_slip.date_selected_to), '%Y-%m-%d')
                
            if date_selected_from < salary_start_date:
                date_selected_from = salary_start_date
            
            date_selected_to = datetime.now()
            
            # to solve an issue where users request leaves before payrolls period
            leave_requests_from_factor = date_selected_from - timedelta(days=30)
            
            attendance_requests_days = self.env['jtemployees.requests'].search([('deleted', '=', False),
                                                                                ('employee_id', '=', employee.id),
                                                            ('date_from', '>=', leave_requests_from_factor),
                                                            ('request_type', 'in', ["admin_leave","paid_leave","unpaid_leave", "remote_working_leave", "field_work_leave", "official_mission_leave", "external_training_leave", "client_visit_leave", "government_errand_leave"]),
                                                            ('manager_approved', '=', 'approved'),
                                                            ('hr_approved', '=', 'approved')
                                                            ])
            
            attendance_requests_times = self.env['jtemployees.requests'].search([('deleted', '=', False),
                                                                                 ('employee_id', '=', employee.id),
                                                            ('datetime_from', '>=', date_selected_from),
                                                            ('datetime_to', '<=', date_selected_to),
                                                            ('request_type', 'in', ["admin_time_off","paid_time_off","unpaid_time_off","over_time","field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"]),
                                                            ('manager_approved', '=', 'approved'),
                                                            ('hr_approved', '=', 'approved')
                                                            ])
            
            # getting work attendance
            attendances = self.env['hr.attendance'].search([('employee_id', '=', employee.id),
                                                            ('check_in', '>=', date_selected_from.strftime('%Y-%m-%d')),
                                                            ('check_out', '<=', date_selected_to.strftime('%Y-%m-%d')),])
            
            # getting extra shortages
            extra_shortages = self.env['jtemployees.extrashortages'].search([('deleted', '=', False),('employee_ids', 'in', [employee.id]),
                                                            ('date', '>=', date_selected_from),('date', '<=', date_selected_to),])
            
            # holidays
            holidays = self.env['jtemployees.holiday'].sudo().search([('deleted', '=', False),('date', '>=', date_selected_from),('date', '<=', date_selected_to),])
            holidays_dates = []
            for holiday in holidays:
                if employee.department_id.id in holiday.departments.ids:
                    holidays_dates.append(holiday.date)
                
            # Initialize the current date to the start date
            current_date = date_selected_from
                        
                     
            # We calculate the total shortage for the given period
            while current_date < date_selected_to:
                
                if current_date.date() == date_selected_to.date():
                    break
                
                calculate_shortage = True
                passed_day = self.env['jtemployees.passed.days'].sudo().search([('employee_id', '=', employee.id), ('date', '=', current_date.date())], limit=1)
                if passed_day and passed_day.is_day_off:
                    calculate_shortage = False
                else:
                    working_days_of_week = []
                    
                    work_schedule = False
                    # we try to get the working schedule from attendnance checkin
                    for attendance in attendances:
                        user_tz = "Asia/Baghdad"
                        checkin = attendance.check_in + timedelta(minutes=30)
                        check_in_local = fields.Datetime.context_timestamp(
                            self.with_context(tz=user_tz),
                            checkin
                        )
                        if check_in_local.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d'):
                            work_schedule = attendance.jt_work_schedule
                            break
                        
                    if not work_schedule:
                        work_schedule = calendar
                            
                    # Get working days to use in the shortage checking
                    for schedual_day in work_schedule.attendance_ids:
                        if schedual_day.day_period != "break" and schedual_day.dayofweek not in working_days_of_week:
                            working_days_of_week.append(schedual_day.dayofweek)
                            
                    calculate_shortage = str(current_date.weekday()) in working_days_of_week
                    
                if calculate_shortage:
                    
                    # we get the shortage
                    expected_hours += hour_per_day
                    expected_shortage = 0
                    shortage_made = {}
                    
                    # is holiday
                    if current_date.date() in holidays_dates:
                        expected_shortage = 0
                    # handle leave days
                    elif payroll_slip.has_leave_day(attendance_requests_days, current_date):
                        expected_shortage = 0
                        leave_days_requested = leave_days_requested + 1
                    else:
                        expected_shortage = hour_per_day
                        
                        shortage_made = {
                            "hours": expected_shortage,
                            "reason": "Absence",
                            "date": current_date
                        }
                        
                        # if has attendance then we use the check in and check out
                        for attendance in attendances:
                            user_tz = "Asia/Baghdad"
                            checkin = attendance.check_in + timedelta(minutes=30)
                            check_in_local = fields.Datetime.context_timestamp(
                                self.with_context(tz=user_tz),
                                checkin
                            )
                            
                            check_out_local = fields.Datetime.context_timestamp(
                                self.with_context(tz=user_tz),
                                attendance.check_out
                            )
                            if check_in_local.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d'):
                                current_date_checkin = check_in_local.strftime('%Y-%m-%d')
                                current_date_checkout = check_out_local.strftime('%Y-%m-%d')
                                if attendance.jt_worked_hours:
                                    working_hours = attendance.jt_worked_hours
                                    expected_shortage = attendance.jt_shortage_hours
                                else:
                                    working_hours = payroll_slip.working_for_attendance(work_schedule, attendance)
                                    expected_shortage = expected_shortage - working_hours
                                if expected_shortage > 0:
                                    shortage_made = {
                                        "hours": expected_shortage,
                                        "reason": "Shortage",
                                        "date": current_date
                                    }
                                break
                        
                        # if has extra shortages then we relay on the hours from the extra shortages
                        for extra_shortage in extra_shortages:
                            if extra_shortage.date.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d'):
                                expected_shortage = extra_shortage.hours
                                if expected_shortage > 0:
                                    shortage_made = {
                                        "hours": expected_shortage,
                                        "reason": "Shortage by HR",
                                        "date": current_date
                                    }
                                break
                        
                        total_timeoff = 0
                        for timeoff in ["admin_time_off", "paid_time_off", "unpaid_time_off", "field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"]:
                            timeoff_calculated  = payroll_slip.time_off_calculate(attendance_requests_times, timeoff, current_date, work_schedule)
                            if timeoff_calculated > 0:
                                total_timeoff = total_timeoff + timeoff_calculated
                        
                        day_over_time = payroll_slip.time_calculate(attendance_requests_times, 'over_time', current_date, work_schedule)
                        
                        if employee.jt_has_overtime_limits and day_over_time > employee.jt_overtime_max_hours:
                            day_over_time = employee.jt_overtime_max_hours
                        over_time += day_over_time
                        
                        expected_shortage -= total_timeoff
                        
                        time_off_requested = time_off_requested + total_timeoff
                        
                        if expected_shortage > 0.02:
                            shortage_made['hours'] = expected_shortage
                            shortages_details.append(shortage_made)
                        else:
                            expected_shortage = 0
                    
                        shortage_hours += expected_shortage
                current_date += timedelta(days=1)
                if shortage_hours < 0:
                    shortage_hours = 0
            actual_hours = expected_hours - shortage_hours
            
            if employee.jt_ignore_shortages:
                shortages_details = 0
                shortages_details = []
                shortage_hours = 0
            
            return {
                "info_calculation_time": date_selected_from,
                "over_time_hours": over_time,
                "time_off_requested": time_off_requested,
                "leave_days_requested": leave_days_requested,
                "hour_per_day": hour_per_day,
                "shortage_hours": shortage_hours,
                "shortages_details": shortages_details,
                "expected_hours": expected_hours,
                "actual_hours": actual_hours
            }
        return True
    
    def working_schedule(self):
        result = []
        for record in self:
            schedule_id = record.resource_calendar_id
            if not schedule_id:
                return []
            date = datetime.now().date()
            result.append(self.working_schedule_element(date, schedule_id, record.department_id))
            
            for working_schedule in record.jt_panned_days:
                schedule_id = working_schedule.schedule_id
                date = working_schedule.date
                result.append(self.working_schedule_element(date, schedule_id, record.department_id))
                if len(result) == 7:
                    break
        return result
    
    def working_schedule_element(self, date, schedule, department):
        day_name = date.strftime("%A").lower()
        allowed_days = []
        if schedule.jt_has_friday:
            allowed_days.append("friday")
        if schedule.jt_has_monday:
            allowed_days.append("monday")
        if schedule.jt_has_saturday:
            allowed_days.append("saturday")
        if schedule.jt_has_sunday:
            allowed_days.append("sunday")
        if schedule.jt_has_thursday:
            allowed_days.append("thursday")
        if schedule.jt_has_tuesday:
            allowed_days.append("tuesday")
        if schedule.jt_has_wednesday:
            allowed_days.append("wednesday")
            
        is_day_off = False
        if day_name not in allowed_days:
            is_day_off = True
            
        holidays = self.env['jtemployees.holiday'].sudo().search([('deleted', '=', False),('date', '>=', date),('date', '<=', date),])
        for holiday in holidays:
            if department.id in holiday.departments.ids:
                is_day_off = True
                
        schedule_name = ""
        if schedule.name:
            schedule_name = schedule.name
        return {
            "name": schedule_name,
            "date": date,
            "day_name": day_name,
            "hours_per_day": schedule.hours_per_day,
            "start_time": schedule.jt_start_time,
            "end_time": schedule.jt_end_time,
            "is_day_off": is_day_off
        }
                
        

